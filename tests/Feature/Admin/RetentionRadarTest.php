<?php

namespace Tests\Feature\Admin;

use App\Enums\BudgetTransactionType;
use App\Enums\ClipStatus;
use App\Enums\UserRole;
use App\Filament\Pages\ClippersToWin;
use App\Models\BudgetTransaction;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use App\Services\Clippers\RetentionRadar;
use App\Support\Clippers\RetentionSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RetentionRadarTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaign = Campaign::factory()->create();
    }

    protected function clipper(): User
    {
        return User::factory()->create(['role' => UserRole::Clipper]);
    }

    protected function clips(User $clipper, int $count, int $daysAgo, ClipStatus $status = ClipStatus::Approved): void
    {
        Clip::factory()->count($count)->create([
            'campaign_id' => $this->campaign->id,
            'user_id' => $clipper->id,
            'status' => $status,
            'submitted_at' => now()->subDays($daysAgo),
        ]);
    }

    /** Une ligne de grand livre écrite directement : seul son montant et sa date comptent ici. */
    protected function earned(User $clipper, int $cents, int $daysAgo): void
    {
        $clip = Clip::factory()->create(['campaign_id' => $this->campaign->id, 'user_id' => $clipper->id, 'submitted_at' => now()->subDays(90)]);

        $row = BudgetTransaction::create([
            'campaign_id' => $this->campaign->id,
            'clip_id' => $clip->id,
            'user_id' => $clipper->id,
            'type' => BudgetTransactionType::Credit,
            'amount_cents' => $cents,
            'views_delta' => $cents * 10,
            'balance_after_cents' => 0,
            'idempotency_key' => 'test:'.fake()->uuid(),
        ]);
        $row->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
    }

    #[Test]
    public function it_flags_the_three_kinds_of_clippers_about_to_leave(): void
    {
        $busyButBroke = $this->clipper();
        $this->clips($busyButBroke, 4, daysAgo: 5, status: ClipStatus::PendingReview);

        $dropping = $this->clipper();
        $this->clips($dropping, 1, daysAgo: 3);
        $this->earned($dropping, 5_000, daysAgo: 45);
        $this->earned($dropping, 500, daysAgo: 10);

        $gone = $this->clipper();
        $this->clips($gone, 2, daysAgo: 40);

        $healthy = $this->clipper();
        $this->clips($healthy, 3, daysAgo: 2);
        $this->earned($healthy, 3_000, daysAgo: 2);

        $banned = User::factory()->create(['role' => UserRole::Clipper, 'is_banned' => true]);
        $this->clips($banned, 5, daysAgo: 2, status: ClipStatus::PendingReview);

        $signals = app(RetentionRadar::class)->detect();

        $this->assertSame(RetentionSignal::AT_RISK, $signals[$busyButBroke->id]->level);
        $this->assertSame(RetentionSignal::DROPPING, $signals[$dropping->id]->level);
        $this->assertSame(RetentionSignal::INACTIVE, $signals[$gone->id]->level);
        $this->assertFalse($signals->has($healthy->id));
        $this->assertFalse($signals->has($banned->id));
    }

    #[Test]
    public function a_never_paid_newcomer_who_stopped_is_not_inactive(): void
    {
        $tourist = $this->clipper();
        $this->clips($tourist, 1, daysAgo: 60, status: ClipStatus::Rejected);

        $this->assertFalse(app(RetentionRadar::class)->detect()->has($tourist->id));
    }

    #[Test]
    public function staff_can_relaunch_a_clipper_through_the_messaging(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $clipper = $this->clipper();
        $this->clips($clipper, 4, daysAgo: 5, status: ClipStatus::PendingReview);

        $this->actingAs($admin)->get(ClippersToWin::getUrl())->assertSuccessful();

        Livewire::actingAs($admin)
            ->test(ClippersToWin::class)
            ->assertCanSeeTableRecords([$clipper])
            ->callTableAction('message', $clipper, ['body' => 'On regarde ensemble ?'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('messages', ['body' => 'On regarde ensemble ?', 'from_staff' => true]);
    }
}
