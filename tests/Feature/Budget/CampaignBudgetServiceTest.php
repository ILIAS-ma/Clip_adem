<?php

namespace Tests\Feature\Budget;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Models\BudgetTransaction;
use App\Models\Campaign;
use App\Models\Clip;
use App\Support\Budget\CreditOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampaignBudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CampaignBudgetService $budget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->budget = app(CampaignBudgetService::class);
    }

    /** Campagne de 100 €, 1 € pour 1000 vues sur TikTok. */
    protected function campaign(array $attributes = []): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create(array_merge([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 10_000,
            ], $attributes));
    }

    protected function clip(Campaign $campaign, array $attributes = []): Clip
    {
        return Clip::factory()->create(array_merge([
            'campaign_id' => $campaign->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
        ], $attributes));
    }

    #[Test]
    public function it_credits_views_at_the_platform_rate(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign);

        // 10 000 vues à 1 € / 1000 vues = 10 €.
        $result = $this->budget->creditViews($clip, 10_000, 'clip:1:snapshot:1');

        $this->assertSame(CreditOutcome::Credited, $result->outcome);
        $this->assertSame(1_000, $result->creditedCents);
        $this->assertSame(10_000, $result->creditedViews);

        $this->assertSame(1_000, $clip->fresh()->earned_cents);
        $this->assertSame(10_000, $clip->fresh()->paid_views);
        $this->assertSame(1_000, $campaign->fresh()->spent_cents);
        $this->assertSame(9_000, $campaign->fresh()->remainingCents());
    }

    #[Test]
    public function it_only_credits_the_delta_between_two_snapshots(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign);

        $this->budget->creditViews($clip, 10_000, 'clip:1:snapshot:1');
        $second = $this->budget->creditViews($clip->fresh(), 25_000, 'clip:1:snapshot:2');

        // Seules les 15 000 nouvelles vues sont payées.
        $this->assertSame(1_500, $second->creditedCents);
        $this->assertSame(2_500, $campaign->fresh()->spent_cents);
    }

    #[Test]
    public function it_never_pays_twice_for_the_same_idempotency_key(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign);

        $this->budget->creditViews($clip, 10_000, 'clip:1:snapshot:1');
        $replay = $this->budget->creditViews($clip->fresh(), 10_000, 'clip:1:snapshot:1');

        $this->assertSame(CreditOutcome::AlreadyProcessed, $replay->outcome);
        $this->assertSame(0, $replay->creditedCents);
        $this->assertSame(1_000, $campaign->fresh()->spent_cents);
        $this->assertSame(1, BudgetTransaction::count());
    }

    #[Test]
    public function it_caps_the_last_clip_at_the_exact_remaining_budget(): void
    {
        // Budget 100 €, déjà 97 € consommés : il reste exactement 3 €.
        $campaign = $this->campaign();
        $campaign->forceFill(['spent_cents' => 9_700])->save();

        $clip = $this->clip($campaign);

        // 70 000 vues valent 70 € : très au-dessus du reliquat.
        $result = $this->budget->creditViews($clip, 70_000, 'clip:1:snapshot:1');

        $this->assertSame(CreditOutcome::Capped, $result->outcome);
        $this->assertSame(300, $result->creditedCents);

        $campaign->refresh();
        $this->assertSame(10_000, $campaign->spent_cents);
        $this->assertSame(0, $campaign->remainingCents());
        $this->assertSame(CampaignStatus::Exhausted, $campaign->status);
        $this->assertNotNull($campaign->exhausted_at);
    }

    #[Test]
    public function a_capped_clip_only_marks_the_views_it_was_actually_paid_for(): void
    {
        // Le piège : marquer les 70 000 vues comme payées alors que seules
        // 3 000 l'ont été perdrait définitivement les 67 000 restantes si du
        // budget se libérait ensuite.
        $campaign = $this->campaign();
        $campaign->forceFill(['spent_cents' => 9_700])->save();

        // Le module clippeur écrit views_total avant d'appeler le moteur.
        $clip = $this->clip($campaign, ['views_total' => 70_000]);
        $this->budget->creditViews($clip, 70_000, 'clip:1:snapshot:1');

        $clip->refresh();
        $this->assertSame(3_000, $clip->paid_views);
        $this->assertSame(67_000, $clip->unpaidViews());
    }

    #[Test]
    public function it_refuses_to_credit_once_the_campaign_is_exhausted(): void
    {
        $campaign = $this->campaign();
        $campaign->forceFill(['spent_cents' => 10_000])->save();

        $result = $this->budget->creditViews($this->clip($campaign), 50_000, 'clip:1:snapshot:1');

        $this->assertSame(CreditOutcome::CampaignClosed, $result->outcome);
        $this->assertSame(0, $result->creditedCents);
        $this->assertSame(10_000, $campaign->fresh()->spent_cents);
    }

    #[Test]
    public function it_never_debits_when_the_view_count_drops(): void
    {
        // TikTok et Instagram révisent régulièrement leurs compteurs à la baisse.
        $campaign = $this->campaign();
        $clip = $this->clip($campaign);

        $this->budget->creditViews($clip, 10_000, 'clip:1:snapshot:1');
        $result = $this->budget->creditViews($clip->fresh(), 8_000, 'clip:1:snapshot:2');

        $this->assertSame(CreditOutcome::NothingToCredit, $result->outcome);
        $this->assertSame(1_000, $campaign->fresh()->spent_cents);
        $this->assertSame(10_000, $clip->fresh()->paid_views);
    }

    #[Test]
    public function it_ignores_clips_that_are_not_approved(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign, ['status' => ClipStatus::PendingReview]);

        $result = $this->budget->creditViews($clip, 10_000, 'clip:1:snapshot:1');

        $this->assertSame(CreditOutcome::ClipNotPayable, $result->outcome);
        $this->assertSame(0, $campaign->fresh()->spent_cents);
    }

    #[Test]
    public function it_ignores_clips_below_the_minimum_view_threshold(): void
    {
        $campaign = $this->campaign(['min_views_per_clip' => 5_000]);
        $clip = $this->clip($campaign);

        $result = $this->budget->creditViews($clip, 4_999, 'clip:1:snapshot:1');

        $this->assertSame(CreditOutcome::BelowThreshold, $result->outcome);
        $this->assertSame(0, $campaign->fresh()->spent_cents);
    }

    #[Test]
    public function it_ignores_platforms_without_an_active_rate(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign, ['platform' => Platform::YouTube]);

        $result = $this->budget->creditViews($clip, 10_000, 'clip:1:snapshot:1');

        $this->assertSame(CreditOutcome::NoRate, $result->outcome);
        $this->assertSame(0, $campaign->fresh()->spent_cents);
    }

    #[Test]
    public function it_enforces_the_per_clip_cap(): void
    {
        $campaign = $this->campaign(['max_payout_per_clip_cents' => 500]);
        $clip = $this->clip($campaign);

        $result = $this->budget->creditViews($clip, 50_000, 'clip:1:snapshot:1');

        $this->assertSame(CreditOutcome::Capped, $result->outcome);
        $this->assertSame(500, $result->creditedCents);
        $this->assertSame(500, $campaign->fresh()->spent_cents);

        // Le clip a atteint son plafond : plus rien ne lui est dû ensuite.
        $again = $this->budget->creditViews($clip->fresh(), 90_000, 'clip:1:snapshot:2');
        $this->assertSame(CreditOutcome::NoBudgetLeft, $again->outcome);
    }

    #[Test]
    public function it_enforces_the_per_clipper_cap_across_their_clips(): void
    {
        $campaign = $this->campaign(['max_payout_per_clipper_cents' => 1_500]);

        $first = $this->clip($campaign);
        $second = $this->clip($campaign, ['user_id' => $first->user_id]);

        $this->budget->creditViews($first, 10_000, 'clip:1:snapshot:1');   // 10 €
        $result = $this->budget->creditViews($second, 10_000, 'clip:2:snapshot:1');

        // Le second clip ne touche que les 5 € restants sous le plafond.
        $this->assertSame(CreditOutcome::Capped, $result->outcome);
        $this->assertSame(500, $result->creditedCents);
        $this->assertSame(1_500, $campaign->fresh()->spent_cents);
    }

    #[Test]
    public function reversing_a_clip_gives_the_budget_back_and_reopens_the_campaign(): void
    {
        $campaign = $this->campaign();
        $campaign->forceFill(['spent_cents' => 6_000])->save();

        $clip = $this->clip($campaign);
        $this->budget->creditViews($clip, 40_000, 'clip:1:snapshot:1');

        $campaign->refresh();
        $this->assertSame(CampaignStatus::Exhausted, $campaign->status);

        $reversal = $this->budget->reverseClip($clip->fresh(), 'Vues achetées');

        $this->assertSame(4_000, $reversal->refundedCents);
        $this->assertTrue($reversal->campaignReactivated);

        $campaign->refresh();
        $this->assertSame(6_000, $campaign->spent_cents);
        $this->assertSame(CampaignStatus::Active, $campaign->status);
        $this->assertNull($campaign->exhausted_at);

        $clip->refresh();
        $this->assertSame(0, $clip->earned_cents);
        $this->assertSame(0, $clip->paid_views);
    }

    #[Test]
    public function the_ledger_always_matches_the_denormalised_total(): void
    {
        $campaign = $this->campaign();
        $clips = Clip::factory()->count(3)->create([
            'campaign_id' => $campaign->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
        ]);

        foreach ($clips as $index => $clip) {
            $this->budget->creditViews($clip, 12_345, "clip:{$clip->id}:snapshot:{$index}");
        }

        $this->budget->reverseClip($clips->first()->fresh(), 'Test');

        $ledger = (int) BudgetTransaction::where('campaign_id', $campaign->getKey())->sum('amount_cents');

        $this->assertSame($campaign->fresh()->spent_cents, $ledger);
        $this->assertSame(
            (int) Clip::where('campaign_id', $campaign->getKey())->sum('earned_cents'),
            $ledger,
        );
    }

    #[Test]
    public function a_quote_writes_nothing(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign);

        $quote = $this->budget->quote($clip, 10_000);

        $this->assertSame(1_000, $quote->payableCents);
        $this->assertSame(0, $campaign->fresh()->spent_cents);
        $this->assertSame(0, $clip->fresh()->earned_cents);
        $this->assertSame(0, BudgetTransaction::count());
    }
}
