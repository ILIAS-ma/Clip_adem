<?php

namespace Tests\Feature\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipperLevel;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\ParticipationRefused;
use App\Models\BudgetTransaction;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Clippers\ClipperProgressionService;
use App\Services\Clips\ParticipationService;
use App\Services\Moderation\ClipModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClipperProgressionTest extends TestCase
{
    use RefreshDatabase;

    protected ClipperProgressionService $progression;

    protected function setUp(): void
    {
        parent::setUp();

        $this->progression = app(ClipperProgressionService::class);
    }

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'clip'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
        ]);
    }

    protected function campaign(array $attributes = []): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create(array_merge([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 10_000_000,
                'requires_approval' => false,
            ], $attributes));
    }

    /** Crédite un clip et renvoie l'objet à jour. */
    protected function paidClip(User $clipper, Campaign $campaign, int $views): Clip
    {
        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => $views,
        ]);

        app(CampaignBudgetService::class)->creditViews($clip, $views, "clip:{$clip->id}:snapshot:1");
        $this->progression->forget($clipper);

        return $clip->fresh();
    }

    // ------------------------------------------------------------------
    // Formule
    // ------------------------------------------------------------------

    #[Test]
    public function a_brand_new_clipper_starts_at_the_first_level(): void
    {
        $progression = $this->progression->for($this->clipper());

        $this->assertSame(ClipperLevel::Beginner, $progression->level);
        $this->assertSame(0, $progression->careerXp);
        $this->assertFalse($progression->perksActive);
    }

    #[Test]
    public function experience_counts_paid_views_plus_regularity_bonuses(): void
    {
        $clipper = $this->clipper();
        $this->paidClip($clipper, $this->campaign(), 40_000);

        // 40 000 vues payées + 2 000 (clip validé) + 5 000 (campagne).
        $this->assertSame(47_000, $this->progression->for($clipper)->careerXp);
    }

    #[Test]
    public function only_paid_views_count_never_the_raw_counter(): void
    {
        // C'est ce qui empêche l'expérience de récompenser ce que le détecteur
        // de vues suspectes essaie d'attraper.
        $clipper = $this->clipper();
        $campaign = $this->campaign(['max_payout_per_clip_cents' => 1_000]); // 10 € = 10 000 vues

        $clip = $this->paidClip($clipper, $campaign, 500_000);

        $this->assertSame(500_000, $clip->views_total);
        $this->assertSame(10_000, $clip->paid_views);
        $this->assertSame(10_000, $this->progression->for($clipper)->paidViews);
    }

    #[Test]
    public function invalidating_a_clip_takes_its_experience_back(): void
    {
        $clipper = $this->clipper();
        $clip = $this->paidClip($clipper, $this->campaign(), 100_000);

        $this->assertSame(107_000, $this->progression->for($clipper)->careerXp);

        app(ClipModerationService::class)->invalidate($clip, 'Vues achetées');
        $this->progression->forget($clipper);

        // Vues payées remises à zéro par le moteur, plus le malus : un niveau
        // ne peut pas servir à blanchir des vues achetées.
        $this->assertSame(0, $this->progression->for($clipper)->careerXp);
    }

    #[Test]
    public function experience_never_goes_below_zero(): void
    {
        $clipper = $this->clipper();
        $campaign = $this->campaign();

        foreach (range(1, 3) as $i) {
            $clip = Clip::factory()->create([
                'campaign_id' => $campaign->getKey(),
                'user_id' => $clipper->getKey(),
                'platform' => Platform::TikTok,
                'status' => ClipStatus::Invalidated,
            ]);
        }

        $this->assertSame(0, $this->progression->for($clipper)->careerXp);
    }

    #[Test]
    public function levels_follow_the_configured_thresholds(): void
    {
        $this->assertSame(ClipperLevel::Beginner, ClipperLevel::fromXp(0));
        $this->assertSame(ClipperLevel::Beginner, ClipperLevel::fromXp(49_999));
        $this->assertSame(ClipperLevel::Confirmed, ClipperLevel::fromXp(50_000));
        $this->assertSame(ClipperLevel::Expert, ClipperLevel::fromXp(250_000));
        $this->assertSame(ClipperLevel::Elite, ClipperLevel::fromXp(1_000_000));
        $this->assertSame(ClipperLevel::Legend, ClipperLevel::fromXp(9_999_999));
    }

    #[Test]
    public function a_banned_clipper_loses_everything(): void
    {
        // Sans cela, le niveau deviendrait un actif qu'on revend avec le compte.
        $clipper = $this->clipper();
        $this->paidClip($clipper, $this->campaign(), 2_000_000);

        $this->assertSame(ClipperLevel::Elite, $this->progression->for($clipper)->level);

        $clipper->forceFill(['is_banned' => true])->save();
        $this->progression->forget($clipper);

        $this->assertSame(ClipperLevel::Beginner, $this->progression->for($clipper->fresh())->level);
    }

    // ------------------------------------------------------------------
    // Avantages
    // ------------------------------------------------------------------

    #[Test]
    public function perks_require_recent_activity_but_the_level_stays(): void
    {
        $clipper = $this->clipper();
        $this->paidClip($clipper, $this->campaign(), 300_000);

        $this->assertSame(ClipperLevel::Expert, $this->progression->for($clipper)->level);
        $this->assertTrue($this->progression->for($clipper)->perksActive);

        // Toute l'activité sort de la fenêtre glissante.
        BudgetTransaction::query()->update(['created_at' => now()->subMonths(6)]);
        $this->progression->forget($clipper);

        $stale = $this->progression->for($clipper);
        $this->assertSame(ClipperLevel::Expert, $stale->level, 'Le niveau est acquis, il ne redescend pas.');
        $this->assertFalse($stale->perksActive, 'Les avantages, eux, se maintiennent.');
        $this->assertSame(1.0, $stale->clipCapMultiplier());
        $this->assertSame(0, $stale->earlyAccessHours());
    }

    #[Test]
    public function a_high_level_raises_the_per_clip_cap(): void
    {
        $clipper = $this->clipper();

        // On amène le clippeur au niveau Élite sur une première campagne.
        $this->paidClip($clipper, $this->campaign(), 1_100_000);
        $this->assertSame(ClipperLevel::Elite, $this->progression->for($clipper)->level);

        // Sur une campagne plafonnée à 10 €, il peut en capter 20.
        $capped = $this->campaign(['max_payout_per_clip_cents' => 1_000]);
        $clip = $this->paidClip($clipper, $capped, 500_000);

        $this->assertSame(2_000, $clip->earned_cents);
    }

    #[Test]
    public function the_campaign_budget_still_wins_over_any_level(): void
    {
        // L'invariant central du projet : quoi qu'ouvre un niveau, le budget
        // total reste le plafond absolu.
        $clipper = $this->clipper();
        $this->paidClip($clipper, $this->campaign(), 1_100_000);

        $small = $this->campaign([
            'budget_total_cents' => 500,
            'max_payout_per_clip_cents' => 1_000,
        ]);

        $clip = $this->paidClip($clipper, $small, 500_000);

        $this->assertSame(500, $clip->earned_cents);
        $this->assertSame(500, $small->fresh()->spent_cents);
        $this->assertSame(CampaignStatus::Exhausted, $small->fresh()->status);
    }

    #[Test]
    public function a_high_level_can_join_a_campaign_before_it_opens(): void
    {
        $clipper = $this->clipper();
        $this->paidClip($clipper, $this->campaign(), 300_000); // Expert : 12 h d'avance

        $upcoming = $this->campaign(['starts_at' => now()->addHours(6)]);
        $account = SocialAccount::factory()->create([
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
        ]);

        $participations = app(ParticipationService::class);

        $this->assertTrue($participations->canJoinNow($upcoming, $clipper));
        $this->assertNotNull($participations->join($upcoming, $clipper, $account));
    }

    #[Test]
    public function a_beginner_waits_for_the_official_opening(): void
    {
        $clipper = $this->clipper();
        $upcoming = $this->campaign(['starts_at' => now()->addHours(6)]);
        $account = SocialAccount::factory()->create([
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
        ]);

        $participations = app(ParticipationService::class);

        $this->assertFalse($participations->canJoinNow($upcoming, $clipper));

        $this->expectException(ParticipationRefused::class);
        $this->expectExceptionMessage('ouvre le');

        $participations->join($upcoming, $clipper, $account);
    }

    #[Test]
    public function early_access_does_not_open_a_campaign_that_is_too_far_off(): void
    {
        $clipper = $this->clipper();
        $this->paidClip($clipper, $this->campaign(), 300_000); // 12 h d'avance

        $farOff = $this->campaign(['starts_at' => now()->addDays(3)]);

        $this->assertFalse(app(ParticipationService::class)->canJoinNow($farOff, $clipper));
    }

    #[Test]
    public function the_leaderboard_ranks_by_experience(): void
    {
        $campaign = $this->campaign();

        $big = $this->clipper();
        $small = $this->clipper();
        $this->paidClip($big, $campaign, 400_000);
        $this->paidClip($small, $campaign, 20_000);

        $board = $this->progression->leaderboard();

        $this->assertSame($big->getKey(), $board->first()->id);
        $this->assertSame(407_000, $board->first()->xp);
        // La formule SQL du classement doit rester alignée sur celle du service.
        $this->assertSame($this->progression->for($big)->careerXp, $board->first()->xp);
    }

    #[Test]
    public function the_leaderboard_leaves_out_banned_clippers(): void
    {
        $campaign = $this->campaign();
        $banned = $this->clipper();
        $this->paidClip($banned, $campaign, 400_000);
        $banned->forceFill(['is_banned' => true])->save();

        $this->assertFalse($this->progression->leaderboard()->contains('id', $banned->getKey()));
    }

    #[Test]
    public function the_dashboard_shows_the_level(): void
    {
        $clipper = $this->clipper();
        $this->paidClip($clipper, $this->campaign(), 300_000);

        $this->actingAs($clipper)
            ->get('/dashboard')
            ->assertSuccessful()
            ->assertSee('Votre niveau')
            ->assertSee('Expert')
            ->assertSee('Avantages actifs');
    }
}
