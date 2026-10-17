<?php

namespace Tests\Feature\Budget;

use App\Contracts\CampaignBudgetService;
use App\Enums\BudgetTransactionType;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\BudgetTransaction;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use App\Notifications\BonusGranted;
use App\Services\Budget\BonusService;
use App\Services\Moderation\ClipModerationService;
use App\Support\Budget\CreditOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BonusTest extends TestCase
{
    use RefreshDatabase;

    protected CampaignBudgetService $budget;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->budget = app(CampaignBudgetService::class);
    }

    protected function campaign(array $attributes = []): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 10_000,
                ...$attributes,
            ]);
    }

    protected function clip(Campaign $campaign, ?User $clipper = null, ClipStatus $status = ClipStatus::PendingReview): Clip
    {
        return Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => ($clipper ?? User::factory()->create(['role' => UserRole::Clipper]))->getKey(),
            'platform' => Platform::TikTok,
            'status' => $status,
        ]);
    }

    protected function assertLedgerConsistent(Campaign $campaign): void
    {
        $campaign->refresh();

        $this->assertSame((int) BudgetTransaction::where('campaign_id', $campaign->id)->sum('amount_cents'), $campaign->spent_cents);
        $this->assertSame((int) Clip::where('campaign_id', $campaign->id)->sum('earned_cents'), $campaign->spent_cents);
        $this->assertSame(0, Artisan::call('budget:audit'));
    }

    #[Test]
    public function a_bonus_is_paid_from_the_budget_and_lands_in_the_balance(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign, status: ClipStatus::Approved);

        $result = $this->budget->grantBonus($clip, 1_500, 'test:bonus:1', kind: 'manual');

        $this->assertSame(CreditOutcome::Credited, $result->outcome);
        $this->assertSame(BudgetTransactionType::Bonus, $result->transaction->type);
        $this->assertSame(1_500, $clip->fresh()->bonus_cents);
        $this->assertSame(1_500, $clip->user->availableBalanceCents());
        $this->assertSame(8_500, $campaign->fresh()->remainingCents());
        $this->assertLedgerConsistent($campaign);

        // Rejeu : même clé, aucun second débit.
        $this->assertSame(CreditOutcome::AlreadyProcessed, $this->budget->grantBonus($clip, 1_500, 'test:bonus:1')->outcome);
        $this->assertSame(1_500, $campaign->fresh()->spent_cents);
    }

    #[Test]
    public function a_bonus_is_all_or_nothing(): void
    {
        $campaign = $this->campaign(['budget_total_cents' => 1_000]);
        $clip = $this->clip($campaign, status: ClipStatus::Approved);

        $result = $this->budget->grantBonus($clip, 1_500, 'test:bonus:big');

        $this->assertSame(CreditOutcome::NoBudgetLeft, $result->outcome);
        $this->assertSame(0, $campaign->fresh()->spent_cents);
    }

    #[Test]
    public function an_unvalidated_clip_gets_no_bonus(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign);

        $this->assertSame(CreditOutcome::ClipNotPayable, $this->budget->grantBonus($clip, 500, 'test:bonus:pending')->outcome);
    }

    #[Test]
    public function a_bonus_does_not_eat_into_the_per_clip_cap(): void
    {
        $campaign = $this->campaign(['max_payout_per_clip_cents' => 2_000]);
        $clip = $this->clip($campaign, status: ClipStatus::Approved);

        $this->budget->grantBonus($clip, 1_500, 'test:bonus:cap');
        // 2 000 € de vues brutes à 1 €/1000 : plafonnées à 20 €, prime en sus.
        $this->budget->creditViews($clip->fresh(), 2_000_000, "clip:{$clip->id}:snapshot:1");

        $clip->refresh();
        $this->assertSame(3_500, $clip->earned_cents);
        $this->assertSame(1_500, $clip->bonus_cents);
        $this->assertLedgerConsistent($campaign);
    }

    #[Test]
    public function invalidating_a_clip_takes_its_bonus_back(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign, status: ClipStatus::Approved);

        $this->budget->grantBonus($clip, 1_500, 'test:bonus:fraud');
        $this->budget->creditViews($clip->fresh(), 1_000_000, "clip:{$clip->id}:snapshot:1");

        app(ClipModerationService::class)->invalidate($clip->fresh(), 'Vues achetées');

        $clip->refresh();
        $this->assertSame(0, $clip->earned_cents);
        $this->assertSame(0, $clip->bonus_cents);
        $this->assertSame(0, $campaign->fresh()->spent_cents);
        $this->assertLedgerConsistent($campaign);
    }

    #[Test]
    public function the_launch_bonus_goes_to_the_first_clippers_once_each(): void
    {
        $campaign = $this->campaign(['launch_bonus_cents' => 500, 'launch_bonus_slots' => 2]);
        $moderation = app(ClipModerationService::class);

        [$first, $second, $third] = User::factory()->count(3)->create(['role' => UserRole::Clipper]);

        $moderation->approve($this->clip($campaign, $first));
        // Un deuxième clip du même clippeur ne vaut pas une deuxième prime.
        $moderation->approve($this->clip($campaign, $first));
        $moderation->approve($this->clip($campaign, $second));
        // Plus de place pour le troisième.
        $moderation->approve($this->clip($campaign, $third));

        $this->assertSame(500, $first->availableBalanceCents());
        $this->assertSame(500, $second->availableBalanceCents());
        $this->assertSame(0, $third->availableBalanceCents());
        $this->assertSame(0, $campaign->fresh()->launchBonusSlotsLeft());
        $this->assertLedgerConsistent($campaign);

        Notification::assertSentTo($first, BonusGranted::class);
        Notification::assertNotSentTo($third, BonusGranted::class);
    }

    #[Test]
    public function the_launch_bonus_waits_for_the_minimum_clip_count(): void
    {
        $campaign = $this->campaign([
            'launch_bonus_cents' => 500,
            'launch_bonus_slots' => 5,
            'min_clips_per_clipper' => 2,
        ]);
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);
        $moderation = app(ClipModerationService::class);

        $moderation->approve($this->clip($campaign, $clipper));
        $this->assertSame(0, $clipper->availableBalanceCents());

        $moderation->approve($this->clip($campaign, $clipper));
        $this->assertSame(500, $clipper->availableBalanceCents());
    }

    #[Test]
    public function a_manual_bonus_is_logged_and_celebrated_on_the_dashboard(): void
    {
        // La vraie notification en base, pas le faux : c'est elle que le
        // tableau de bord lit.
        Notification::swap(new ChannelManager($this->app));

        $campaign = $this->campaign();
        $clipper = User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya',
            'country' => 'FR',
            'paypal_email' => 'maya@paypal.test',
            'profile_completed_at' => now(),
        ]);
        $clip = $this->clip($campaign, $clipper, ClipStatus::Approved);
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        app(BonusService::class)->grantManual($clip, 2_000, 'Clip exemplaire', $admin);

        $this->assertDatabaseHas('moderation_logs', ['action' => 'bonus_granted', 'user_id' => $admin->id]);

        $this->actingAs($clipper)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Prime débloquée')
            ->assertSee('+20,00 €');
    }
}
