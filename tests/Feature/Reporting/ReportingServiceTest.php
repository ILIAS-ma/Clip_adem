<?php

namespace Tests\Feature\Reporting;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\PayoutStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Creator;
use App\Models\Payout;
use App\Models\User;
use App\Services\Moderation\ClipModerationService;
use App\Services\Reporting\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReportingService $reporting;

    protected CampaignBudgetService $budget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reporting = app(ReportingService::class);
        $this->budget = app(CampaignBudgetService::class);
    }

    protected function campaign(?Creator $creator = null, int $budgetCents = 100_000): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'creator_id' => $creator?->getKey() ?? Creator::factory(),
                'status' => CampaignStatus::Active,
                'budget_total_cents' => $budgetCents,
            ]);
    }

    protected function credit(Campaign $campaign, int $views, ?User $clipper = null, Platform $platform = Platform::TikTok): Clip
    {
        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper?->getKey() ?? User::factory()->create(['role' => UserRole::Clipper])->getKey(),
            'platform' => $platform,
            'status' => ClipStatus::Approved,
            'views_total' => $views,
        ]);

        $this->budget->creditViews($clip, $views, "clip:{$clip->id}:snapshot:1");

        return $clip->fresh();
    }

    #[Test]
    public function global_stats_read_the_ledger_not_the_counters(): void
    {
        $campaign = $this->campaign();
        $this->credit($campaign, 30_000); // 30 €

        $stats = $this->reporting->globalStats();

        $this->assertSame(100_000, $stats['budget_engaged_cents']);
        $this->assertSame(3_000, $stats['spent_cents']);
        $this->assertSame(97_000, $stats['remaining_cents']);
        $this->assertSame(30_000, $stats['views']);
        $this->assertSame(1, $stats['active_campaigns']);
        // 3000 centimes pour 30 000 vues = 100 centimes / 1000 vues.
        $this->assertSame(100, $stats['real_cpm_cents']);
    }

    #[Test]
    public function draft_campaigns_do_not_count_as_engaged_budget(): void
    {
        $this->campaign()->forceFill(['status' => CampaignStatus::Draft])->save();

        $this->assertSame(0, $this->reporting->globalStats()['budget_engaged_cents']);
    }

    #[Test]
    public function the_debt_to_clippers_is_what_is_spent_minus_what_is_paid(): void
    {
        $campaign = $this->campaign();
        $this->credit($campaign, 50_000); // 50 € consommés

        Payout::factory()->create(['status' => PayoutStatus::Paid, 'amount_cents' => 2_000]);
        // Un retrait seulement demandé n'a pas encore quitté la plateforme.
        Payout::factory()->create(['status' => PayoutStatus::Requested, 'amount_cents' => 1_000]);

        $stats = $this->reporting->globalStats();

        $this->assertSame(5_000, $stats['spent_cents']);
        $this->assertSame(2_000, $stats['paid_cents']);
        $this->assertSame(3_000, $stats['owed_cents']);
    }

    #[Test]
    public function an_invalidated_clip_lowers_the_spend_everywhere(): void
    {
        $campaign = $this->campaign();
        $clip = $this->credit($campaign, 40_000);

        app(ClipModerationService::class)->invalidate($clip, 'Vues achetées');

        $stats = $this->reporting->globalStats();

        $this->assertSame(0, $stats['spent_cents']);
        $this->assertSame(0, $stats['owed_cents']);
    }

    #[Test]
    public function spend_per_creator_computes_the_real_cpm(): void
    {
        $creator = Creator::factory()->create(['name' => 'NAYRA']);
        $campaign = $this->campaign($creator);
        $this->credit($campaign, 200_000); // 200 €

        $rows = $this->reporting->spendPerCreator();

        $this->assertCount(1, $rows);
        $this->assertSame('NAYRA', $rows->first()->name);
        $this->assertSame(20_000, (int) $rows->first()->spent_cents);
        $this->assertSame(200_000, $rows->first()->views);
        $this->assertSame(100, $rows->first()->real_cpm_cents);
    }

    #[Test]
    public function creators_without_spending_are_left_out(): void
    {
        Creator::factory()->create(['name' => 'Sans campagne']);

        $this->assertCount(0, $this->reporting->spendPerCreator());
    }

    #[Test]
    public function top_clippers_are_ranked_by_earnings(): void
    {
        $campaign = $this->campaign();

        $big = User::factory()->create(['role' => UserRole::Clipper, 'name' => 'Lina']);
        $small = User::factory()->create(['role' => UserRole::Clipper, 'name' => 'Yanis']);

        $this->credit($campaign, 300_000, $big);
        $this->credit($campaign, 50_000, $small);

        $rows = $this->reporting->topClippers();

        $this->assertSame('Lina', $rows->first()->name);
        $this->assertSame(30_000, $rows->first()->earned_cents);
        $this->assertSame(300_000, $rows->first()->views);
    }

    #[Test]
    public function the_invalidation_rate_exposes_risky_profiles(): void
    {
        $campaign = $this->campaign();
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);

        $first = $this->credit($campaign, 20_000, $clipper);
        $this->credit($campaign, 20_000, $clipper);
        $this->credit($campaign, 20_000, $clipper);
        $this->credit($campaign, 20_000, $clipper);

        app(ClipModerationService::class)->invalidate($first, 'Vues achetées');

        $row = $this->reporting->topClippers()->firstWhere('id', $clipper->getKey());

        $this->assertSame(4, (int) $row->clips_count);
        $this->assertSame(25.0, $row->invalidation_rate);
    }

    #[Test]
    public function daily_spend_fills_the_quiet_days_with_zero(): void
    {
        // Sans les jours à zéro, la courbe laisserait croire à une
        // consommation continue entre deux dépenses éloignées.
        $campaign = $this->campaign();
        $this->credit($campaign, 10_000);

        $daily = $this->reporting->dailySpend(7);

        $this->assertCount(7, $daily);
        $this->assertSame(1_000, $daily->get(now()->format('Y-m-d')));
        $this->assertSame(0, $daily->get(now()->subDays(3)->format('Y-m-d')));
    }

    #[Test]
    public function spend_per_platform_splits_the_ledger(): void
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
            ]);

        $campaign->rates()->create([
            'platform' => Platform::YouTube,
            'rate_per_1k_cents' => 200,
            'is_enabled' => true,
        ]);

        $this->credit($campaign, 20_000, platform: Platform::TikTok);    // 20 €
        $this->credit($campaign, 20_000, platform: Platform::YouTube);   // 40 €

        $rows = $this->reporting->spendPerPlatform()->keyBy('platform');

        $this->assertSame(2_000, $rows[Platform::TikTok->value]->spent_cents);
        $this->assertSame(4_000, $rows[Platform::YouTube->value]->spent_cents);
        $this->assertSame(200, $rows[Platform::YouTube->value]->real_cpm_cents);
    }
}
