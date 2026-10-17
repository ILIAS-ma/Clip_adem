<?php

namespace Tests\Feature\Reporting;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ReferralCommission;
use App\Models\Season;
use App\Models\User;
use App\Services\Reporting\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeasonLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(array $attributes = []): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => fake()->unique()->userName(),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
            'profile_completed_at' => now(),
            ...$attributes,
        ]);
    }

    /** Crédite des vues maintenant, via le moteur : 1 € pour 1000 vues. */
    protected function credit(User $clipper, int $views): Clip
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create(['status' => CampaignStatus::Active, 'budget_total_cents' => 1_000_000]);

        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
        ]);

        app(CampaignBudgetService::class)->creditViews($clip, $views, "clip:{$clip->id}:snapshot:".fake()->unique()->numberBetween());

        return $clip;
    }

    #[Test]
    public function a_season_only_counts_what_was_earned_between_its_dates(): void
    {
        $season = Season::factory()->create([
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-03-31',
        ]);

        $veteran = $this->clipper();
        $newcomer = $this->clipper();

        // Avant la saison : gros total, qui ne doit pas compter.
        $this->travelTo('2026-02-15 12:00');
        $this->credit($veteran, 500_000);

        $this->travelTo('2026-03-10 12:00');
        $this->credit($newcomer, 20_000);
        $this->credit($veteran, 5_000);

        // Dernier jour, 23 h 30 : encore dans la saison.
        $this->travelTo('2026-03-31 23:30');
        $this->credit($newcomer, 1_000);

        $this->travelTo('2026-04-01 00:30');
        $this->credit($veteran, 900_000);

        $board = app(ReportingService::class)->topClippersInSeason($season);

        $this->assertSame([$newcomer->id, $veteran->id], $board->pluck('id')->all());
        $this->assertSame(2_100, $board[0]->earned_cents);
        $this->assertSame(21_000, $board[0]->views);
        $this->assertSame(500, $board[1]->earned_cents);
    }

    #[Test]
    public function the_page_shows_the_running_season_and_lets_browse_past_ones(): void
    {
        $past = Season::factory()->ended()->create(['name' => 'Saison passée']);
        $current = Season::factory()->create(['name' => 'Saison actuelle', 'prize' => '200 € au premier']);
        Season::factory()->create(['name' => 'Saison future', 'starts_on' => today()->addMonth(), 'ends_on' => today()->addMonths(2)]);

        $viewer = $this->clipper();

        $this->actingAs($viewer)
            ->get(route('leaderboard.index'))
            ->assertSuccessful()
            ->assertSee('Saison actuelle')
            ->assertSee('200 € au premier')
            ->assertSee('jours restants')
            ->assertDontSee('Saison future');

        $this->actingAs($viewer)
            ->get(route('leaderboard.index', ['saison' => $past->id]))
            ->assertSuccessful()
            ->assertSee('terminée');

        $this->assertTrue($current->is(Season::current()));
    }

    #[Test]
    public function the_season_section_stays_hidden_without_any_season(): void
    {
        $this->actingAs($this->clipper())
            ->get(route('leaderboard.index'))
            ->assertSuccessful()
            ->assertDontSee('id="saison"', escape: false);
    }

    #[Test]
    public function top_referrers_sum_commissions_net_of_clawbacks(): void
    {
        $alice = $this->clipper(['name' => 'Alice Parrain']);
        $bob = $this->clipper(['name' => 'Bob Parrain']);
        $banned = $this->clipper(['is_banned' => true]);
        [$f1, $f2, $f3] = [$this->clipper(), $this->clipper(), $this->clipper()];

        $commission = fn (User $referrer, User $referred, int $cents) => ReferralCommission::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
            'amount_cents' => $cents,
            'base_cents' => abs($cents) * 20,
            'rate_bp' => 500,
        ]);

        $commission($alice, $f1, 300);
        $commission($alice, $f2, 200);
        $commission($bob, $f3, 900);
        $commission($bob, $f3, -800); // reprise après invalidation
        $commission($banned, $f1, 5_000);

        $board = app(ReportingService::class)->topReferrers();

        $this->assertSame([$alice->id, $bob->id], $board->pluck('id')->all());
        $this->assertSame(500, $board[0]->earned_cents);
        $this->assertSame(2, $board[0]->referrals);
        $this->assertSame(100, $board[1]->earned_cents);

        $this->actingAs($f1)
            ->get(route('leaderboard.index'))
            ->assertSee('Top parrains')
            ->assertSee('Alice Parrain');
    }
}
