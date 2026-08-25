<?php

namespace Tests\Feature\Moderation;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ModerationAction;
use App\Enums\ParticipationStatus;
use App\Enums\PayoutStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\Payout;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Moderation\ClipModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClipModerationTest extends TestCase
{
    use RefreshDatabase;

    protected ClipModerationService $moderation;

    protected CampaignBudgetService $budget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moderation = app(ClipModerationService::class);
        $this->budget = app(CampaignBudgetService::class);
    }

    protected function campaign(array $attributes = []): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create(array_merge([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 10_000,
            ], $attributes));
    }

    protected function paidClip(Campaign $campaign, int $views = 10_000, ?User $clipper = null): Clip
    {
        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper?->getKey() ?? User::factory()->create(['role' => UserRole::Clipper])->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => $views,
        ]);

        $this->budget->creditViews($clip, $views, "clip:{$clip->id}:snapshot:1");

        return $clip->fresh();
    }

    #[Test]
    public function invalidating_a_clip_gives_the_budget_back_and_logs_the_decision(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $campaign = $this->campaign();
        $clip = $this->paidClip($campaign);

        $this->assertSame(1_000, $campaign->fresh()->spent_cents);

        $this->moderation->invalidate($clip, 'Vues achetées', $admin);

        $clip->refresh();
        $this->assertSame(ClipStatus::Invalidated, $clip->status);
        $this->assertSame(0, $clip->earned_cents);
        $this->assertSame(0, $clip->paid_views);
        $this->assertSame(0, $campaign->fresh()->spent_cents);

        $log = ModerationLog::where('action', ModerationAction::ClipInvalidated)->sole();
        $this->assertSame($admin->getKey(), $log->user_id);
        $this->assertSame('Vues achetées', $log->reason);
        $this->assertSame(1_000, $log->meta['refunded_cents']);
    }

    #[Test]
    public function invalidating_reopens_an_exhausted_campaign(): void
    {
        $campaign = $this->campaign();
        $campaign->forceFill(['spent_cents' => 6_000])->save();

        $clip = $this->paidClip($campaign, views: 40_000);

        $this->assertSame(CampaignStatus::Exhausted, $campaign->fresh()->status);

        $this->moderation->invalidate($clip, 'Brief non respecté');

        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->exhausted_at);
    }

    #[Test]
    public function rejecting_an_unpaid_clip_never_touches_the_budget(): void
    {
        $campaign = $this->campaign();
        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'status' => ClipStatus::PendingReview,
            'platform' => Platform::TikTok,
        ]);

        $this->moderation->reject($clip, 'Hashtags manquants');

        $this->assertSame(ClipStatus::Rejected, $clip->fresh()->status);
        $this->assertSame('Hashtags manquants', $clip->fresh()->rejection_reason);
        $this->assertSame(0, $campaign->fresh()->spent_cents);
    }

    #[Test]
    public function approving_a_clip_clears_a_previous_rejection(): void
    {
        $clip = Clip::factory()->create([
            'status' => ClipStatus::Rejected,
            'rejection_reason' => 'Erreur de modération',
        ]);

        $this->moderation->approve($clip);

        $this->assertSame(ClipStatus::Approved, $clip->fresh()->status);
        $this->assertNull($clip->fresh()->rejection_reason);
    }

    #[Test]
    public function banning_a_clipper_freezes_their_pending_payouts(): void
    {
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);

        $pending = Payout::factory()->create([
            'user_id' => $clipper->getKey(),
            'status' => PayoutStatus::Requested,
        ]);
        $alreadyPaid = Payout::factory()->create([
            'user_id' => $clipper->getKey(),
            'status' => PayoutStatus::Paid,
        ]);

        $this->moderation->banClipper($clipper, 'Fraude avérée');

        $clipper->refresh();
        $this->assertTrue($clipper->is_banned);
        $this->assertNotNull($clipper->banned_at);

        $this->assertSame(PayoutStatus::Cancelled, $pending->fresh()->status);
        // Un virement déjà parti ne se rattrape pas.
        $this->assertSame(PayoutStatus::Paid, $alreadyPaid->fresh()->status);
    }

    #[Test]
    public function banning_marks_participations_as_banned(): void
    {
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);
        $account = SocialAccount::factory()->create(['user_id' => $clipper->getKey()]);

        $participation = CampaignParticipation::factory()->create([
            'campaign_id' => $this->campaign()->getKey(),
            'user_id' => $clipper->getKey(),
            'social_account_id' => $account->getKey(),
            'status' => ParticipationStatus::Approved,
        ]);

        $this->moderation->banClipper($clipper, 'Comptes multiples');

        $this->assertSame(ParticipationStatus::Banned, $participation->fresh()->status);
    }

    #[Test]
    public function banning_can_invalidate_every_clip_and_refund_each_campaign(): void
    {
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);
        $first = $this->campaign();
        $second = $this->campaign();

        $clipA = $this->paidClip($first, clipper: $clipper);
        $clipB = $this->paidClip($second, clipper: $clipper);

        $this->moderation->banClipper($clipper, 'Bot de vues', invalidateClips: true);

        $this->assertSame(0, $first->fresh()->spent_cents);
        $this->assertSame(0, $second->fresh()->spent_cents);
        $this->assertSame(ClipStatus::Invalidated, $clipA->fresh()->status);
        $this->assertSame(ClipStatus::Invalidated, $clipB->fresh()->status);

        $log = ModerationLog::where('action', ModerationAction::ClipperBanned)->sole();
        $this->assertSame(2_000, $log->meta['refunded_cents']);
    }

    #[Test]
    public function banning_without_invalidating_leaves_the_clips_paid(): void
    {
        // Un bannissement pour non-respect du brief ne remet pas forcément en
        // cause les vues déjà générées : le remboursement est un choix.
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);
        $campaign = $this->campaign();
        $clip = $this->paidClip($campaign, clipper: $clipper);

        $this->moderation->banClipper($clipper, 'Comportement toxique');

        $this->assertSame(1_000, $campaign->fresh()->spent_cents);
        $this->assertSame(ClipStatus::Approved, $clip->fresh()->status);
    }

    #[Test]
    public function unbanning_restores_participations_but_not_invalidated_clips(): void
    {
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);
        $account = SocialAccount::factory()->create(['user_id' => $clipper->getKey()]);
        $campaign = $this->campaign();

        $participation = CampaignParticipation::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'social_account_id' => $account->getKey(),
            'status' => ParticipationStatus::Approved,
        ]);

        $clip = $this->paidClip($campaign, clipper: $clipper);

        $this->moderation->banClipper($clipper, 'Erreur', invalidateClips: true);
        $this->moderation->unbanClipper($clipper);

        $clipper->refresh();
        $this->assertFalse($clipper->is_banned);
        $this->assertNull($clipper->ban_reason);
        $this->assertSame(ParticipationStatus::Pending, $participation->fresh()->status);

        // Les clips invalidés restent invalidés : chaque reprise est un acte
        // délibéré, tracé séparément.
        $this->assertSame(ClipStatus::Invalidated, $clip->fresh()->status);
    }
}
