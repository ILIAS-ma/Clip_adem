<?php

namespace Tests\Feature\Admin;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ModerationAction;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Filament\Resources\Clips\Pages\ListClips;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\User;
use App\Services\Moderation\ClipModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Valider à la main : l'onglet des refusés, la validation en lot, et le signalement qui s'efface. */
class ClipManualValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;

    protected User $clipper;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->campaign = Campaign::factory()->contest()->create(['status' => CampaignStatus::Active]);
        $this->clipper = User::factory()->create(['role' => UserRole::Clipper]);

        $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]));
    }

    protected function rejected(string $reason = 'Trop tôt'): Clip
    {
        return Clip::factory()->create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $this->clipper->id,
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Rejected,
            'rejection_reason' => $reason,
            'earned_cents' => 0,
        ]);
    }

    #[Test]
    public function the_rejected_tab_lists_only_the_rejected_clips_with_their_reason(): void
    {
        $rejected = $this->rejected('Publiée avant le début');
        $approved = Clip::factory()->create(['campaign_id' => $this->campaign->id, 'user_id' => $this->clipper->id, 'status' => ClipStatus::Approved]);

        Livewire::test(ListClips::class)
            ->set('activeTab', 'rejected')
            ->assertCanSeeTableRecords([$rejected])
            ->assertCanNotSeeTableRecords([$approved])
            ->assertSee('Publiée avant le début');
    }

    #[Test]
    public function several_rejected_clips_can_be_validated_in_one_go(): void
    {
        $clips = collect([$this->rejected(), $this->rejected(), $this->rejected()]);

        Livewire::test(ListClips::class)
            ->set('activeTab', 'rejected')
            ->callTableBulkAction('bulkApprove', $clips);

        $clips->each(function (Clip $clip) {
            $this->assertSame(ClipStatus::Approved, $clip->fresh()->status);
            $this->assertNull($clip->fresh()->rejection_reason);
        });
    }

    #[Test]
    public function validating_a_flagged_clip_lifts_the_flag_on_its_author(): void
    {
        $clip = $this->rejected();
        ModerationLog::record(ModerationAction::ClipOutsideWindow, $clip, null, 'Trop tôt');
        $this->assertTrue(User::flaggedForCheating()->whereKey($this->clipper->id)->exists());

        app(ClipModerationService::class)->approve($clip, auth()->user());

        $this->assertFalse(User::flaggedForCheating()->whereKey($this->clipper->id)->exists());
        $this->assertTrue(ModerationLog::where('action', ModerationAction::ClipApproved)->where('subject_id', $clip->id)->exists());
    }

    #[Test]
    public function a_bulk_refusal_skips_the_clips_that_are_already_paid(): void
    {
        $free = Clip::factory()->create(['campaign_id' => $this->campaign->id, 'user_id' => $this->clipper->id, 'status' => ClipStatus::Approved, 'earned_cents' => 0]);
        $paid = Clip::factory()->create(['campaign_id' => $this->campaign->id, 'user_id' => $this->clipper->id, 'status' => ClipStatus::Approved, 'earned_cents' => 500]);

        Livewire::test(ListClips::class)
            ->callTableBulkAction('bulkReject', [$free, $paid], ['reason' => 'Hors sujet']);

        $this->assertSame(ClipStatus::Rejected, $free->fresh()->status);
        $this->assertSame('Hors sujet', $free->fresh()->rejection_reason);
        $this->assertSame(ClipStatus::Approved, $paid->fresh()->status);
    }

    #[Test]
    public function the_clips_can_be_filtered_by_submission_date(): void
    {
        $today = Clip::factory()->create(['campaign_id' => $this->campaign->id, 'user_id' => $this->clipper->id, 'status' => ClipStatus::Approved, 'submitted_at' => now()]);
        $old = Clip::factory()->create(['campaign_id' => $this->campaign->id, 'user_id' => $this->clipper->id, 'status' => ClipStatus::Approved, 'submitted_at' => now()->subDays(5)]);

        Livewire::test(ListClips::class)
            ->filterTable('submitted_between', ['from' => now()->toDateString(), 'until' => now()->toDateString()])
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$old]);
    }
}
