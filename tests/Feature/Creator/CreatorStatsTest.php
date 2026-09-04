<?php

namespace Tests\Feature\Creator;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Creator;
use App\Models\User;
use App\Services\Creators\CreatorStatsService;
use App\Services\Moderation\ClipModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le résumé lu par un créateur.
 *
 * Tout doit venir du grand livre : c'est la seule table qui redescend d'elle-même
 * quand des vues achetées sont invalidées. Lire `campaigns.spent_cents` afficherait
 * au créateur une dépense que la plateforme a déjà annulée.
 */
class CreatorStatsTest extends TestCase
{
    use RefreshDatabase;

    protected CreatorStatsService $stats;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stats = app(CreatorStatsService::class);
    }

    protected function creator(): Creator
    {
        return Creator::factory()->create(['is_active' => true]);
    }

    protected function campaign(Creator $creator, int $budgetCents = 100_000): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'creator_id' => $creator->getKey(),
                'status' => CampaignStatus::Active,
                'budget_total_cents' => $budgetCents,
            ]);
    }

    protected function credit(Campaign $campaign, int $views, ?User $clipper = null): Clip
    {
        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper?->getKey() ?? User::factory()->create(['role' => UserRole::Clipper])->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => $views,
        ]);

        app(CampaignBudgetService::class)->creditViews($clip, $views, "clip:{$clip->id}:snapshot:1");

        return $clip->fresh();
    }

    #[Test]
    public function the_summary_answers_the_three_questions_a_creator_asks(): void
    {
        $creator = $this->creator();
        $this->credit($this->campaign($creator), 200_000); // 200 € pour 200 000 vues

        $summary = $this->stats->summary($creator);

        $this->assertSame(200_000, $summary['views']);
        $this->assertSame(20_000, $summary['spent_cents']);
        $this->assertSame(100_000, $summary['engaged_cents']);
        $this->assertSame(80_000, $summary['remaining_cents']);
        // 20 000 centimes pour 200 000 vues = 100 centimes / 1000 vues.
        $this->assertSame(100, $summary['real_cpm_cents']);
        $this->assertSame(1, $summary['clips']);
        $this->assertSame(1, $summary['clippers']);
    }

    #[Test]
    public function a_draft_campaign_is_not_counted_as_engaged_budget(): void
    {
        // Un budget pas encore lancé n'est pas un budget promis.
        $creator = $this->creator();
        $this->campaign($creator)->forceFill(['status' => CampaignStatus::Draft])->save();

        $this->assertSame(0, $this->stats->summary($creator)['engaged_cents']);
    }

    #[Test]
    public function invalidated_views_disappear_from_the_creator_figures(): void
    {
        $creator = $this->creator();
        $clip = $this->credit($this->campaign($creator), 200_000);

        app(ClipModerationService::class)->invalidate($clip, 'Vues achetées');

        $summary = $this->stats->summary($creator);

        $this->assertSame(0, $summary['views'], 'Des vues achetées ne doivent plus apparaître au créateur.');
        $this->assertSame(0, $summary['spent_cents']);
        $this->assertNull($summary['real_cpm_cents']);
    }

    #[Test]
    public function a_creator_never_sees_another_creators_numbers(): void
    {
        $mine = $this->creator();
        $other = $this->creator();

        $this->credit($this->campaign($other), 200_000);

        $this->assertSame(0, $this->stats->summary($mine)['views']);
    }

    #[Test]
    public function the_daily_series_fills_the_quiet_days_with_zero(): void
    {
        $creator = $this->creator();
        $this->credit($this->campaign($creator), 50_000); // 50 €

        $daily = $this->stats->daily($creator, 7);

        // Sans les jours vides, la courbe laisserait croire à une progression
        // continue entre deux pics éloignés.
        $this->assertCount(7, $daily);
        $this->assertSame(50_000, $daily[now()->format('Y-m-d')]['views']);
        $this->assertSame(5_000, $daily[now()->format('Y-m-d')]['cents']);
        $this->assertSame(0, $daily[now()->subDays(3)->format('Y-m-d')]['views']);
    }

    #[Test]
    public function top_clips_are_ranked_on_paid_views_not_raw_views(): void
    {
        $creator = $this->creator();
        // Budget serré : le second clip sera plafonné, donc payé sur moins de
        // vues que le premier malgré un compteur brut plus élevé.
        $campaign = $this->campaign($creator, budgetCents: 15_000);

        $modest = $this->credit($campaign, 100_000);   // 100 € payés
        $greedy = $this->credit($campaign, 300_000);   // plafonné à 50 €

        $top = $this->stats->topClips($creator);

        $this->assertSame($modest->getKey(), $top->first()->getKey());
        $this->assertGreaterThan($greedy->fresh()->paid_views, $top->first()->paid_views);
    }

    #[Test]
    public function the_headline_says_something_useful_at_every_stage(): void
    {
        $creator = $this->creator();

        $this->assertStringContainsString(
            'Aucune campagne',
            $this->stats->headline($this->stats->summary($creator)),
        );

        $this->campaign($creator);
        $this->assertStringContainsString(
            'premiers clips',
            $this->stats->headline($this->stats->summary($creator)),
        );

        $this->credit($creator->campaigns()->first(), 200_000);
        $this->assertStringContainsString(
            '200 000 vues',
            $this->stats->headline($this->stats->summary($creator)),
        );
    }
}
