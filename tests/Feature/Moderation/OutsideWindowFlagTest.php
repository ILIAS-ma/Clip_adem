<?php

namespace Tests\Feature\Moderation;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ModerationAction;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Resources\Clips\Pages\ListClips;
use App\Filament\Widgets\FlaggedClippers;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Campaigns\ContestReport;
use App\Services\Social\ClipSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Qui a essayé de faire compter des vidéos publiées avant la campagne : les clips sont
 * refusés, les auteurs signalés, et l'équipe le voit dans l'administration.
 */
class OutsideWindowFlagTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;

    protected User $cheater;

    protected User $honest;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('services.tiktok.client_key', null);
        config()->set('clipping.posting_window.enforced_from', now()->subDays(30)->toDateTimeString());

        $this->campaign = Campaign::factory()->contest([9_000, 6_000], [3_000])->create([
            'title' => 'Campagne Momo Rocma',
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subHours(10),
            'ends_at' => now()->addDays(5),
        ]);

        $this->cheater = $this->clipper('7fluxyy');
        $this->honest = $this->clipper('replay.zone');

        $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]));
    }

    protected function clipper(string $pseudo): User
    {
        return User::factory()->create(['role' => UserRole::Clipper, 'pseudo' => $pseudo, 'name' => 'Vrai '.$pseudo]);
    }

    protected function clip(User $user, int $views, int $hoursAgo, array $attributes = []): Clip
    {
        return Clip::factory()->create(array_merge([
            'campaign_id' => $this->campaign->id,
            'user_id' => $user->id,
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => $views,
            'earned_cents' => 0,
            'posted_at' => now()->subHours($hoursAgo),
            'submitted_at' => now()->subHour(),
            'compliance_status' => 'passed',
        ], $attributes));
    }

    // ------------------------------------------------------------------
    // Le refus et le signalement
    // ------------------------------------------------------------------

    #[Test]
    public function the_first_reading_flags_the_author_as_well_as_refusing_the_clip(): void
    {
        $old = $this->clip($this->cheater, 0, 72, ['compliance_status' => 'pending', 'social_account_id' => SocialAccount::factory()->create(['user_id' => $this->cheater->id, 'platform' => Platform::TikTok])->id]);

        app(ClipSyncService::class)->syncPlatform(Platform::TikTok);

        $this->assertSame(ClipStatus::Rejected, $old->fresh()->status);
        $this->assertTrue(ModerationLog::where('action', ModerationAction::ClipOutsideWindow)->where('subject_id', $old->id)->exists());
        $this->assertTrue(User::flaggedForCheating()->whereKey($this->cheater->id)->exists());
    }

    #[Test]
    public function the_command_only_lists_by_default_and_changes_nothing(): void
    {
        $old = $this->clip($this->cheater, 5_000, 72);

        $this->artisan('clips:enforce-window')
            ->expectsOutputToContain('Rien n\'a été modifié')
            ->assertSuccessful();

        $this->assertSame(ClipStatus::Approved, $old->fresh()->status);
        $this->assertSame(0, ModerationLog::where('action', ModerationAction::ClipOutsideWindow)->count());
    }

    #[Test]
    public function the_command_refuses_and_flags_only_the_clips_outside_the_campaign(): void
    {
        $old1 = $this->clip($this->cheater, 5_000, 72);
        $old2 = $this->clip($this->cheater, 8_000, 30);
        $fresh = $this->clip($this->cheater, 900, 2);
        $honestClip = $this->clip($this->honest, 4_000, 3);

        $this->artisan('clips:enforce-window', ['--force' => true])->assertSuccessful();

        $this->assertSame(ClipStatus::Rejected, $old1->fresh()->status);
        $this->assertSame(ClipStatus::Rejected, $old2->fresh()->status);
        $this->assertStringContainsString('avant le début de la campagne', (string) $old1->fresh()->rejection_reason);

        // Les clips dans la période, y compris ceux du même clippeur, restent.
        $this->assertSame(ClipStatus::Approved, $fresh->fresh()->status);
        $this->assertSame(ClipStatus::Approved, $honestClip->fresh()->status);

        $this->assertSame(2, ModerationLog::where('action', ModerationAction::ClipOutsideWindow)->count());
        $this->assertTrue(User::flaggedForCheating()->whereKey($this->cheater->id)->exists());
        $this->assertFalse(User::flaggedForCheating()->whereKey($this->honest->id)->exists());
    }

    #[Test]
    public function the_command_never_touches_a_campaign_that_started_before_the_rule(): void
    {
        $old = Campaign::factory()->contest()->create([
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->addDay(),
        ]);
        $clip = Clip::factory()->create([
            'campaign_id' => $old->id, 'user_id' => $this->honest->id, 'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved, 'posted_at' => now()->subDays(60),
        ]);

        Artisan::call('clips:enforce-window', ['--force' => true]);

        $this->assertSame(ClipStatus::Approved, $clip->fresh()->status);
    }

    #[Test]
    public function running_the_command_twice_flags_nobody_twice(): void
    {
        $this->clip($this->cheater, 5_000, 72);

        Artisan::call('clips:enforce-window', ['--force' => true]);
        Artisan::call('clips:enforce-window', ['--force' => true]);

        $this->assertSame(1, ModerationLog::where('action', ModerationAction::ClipOutsideWindow)->count());
    }

    // ------------------------------------------------------------------
    // Ce que l'équipe voit
    // ------------------------------------------------------------------

    #[Test]
    public function the_report_lists_the_ranking_the_stats_and_the_flagged(): void
    {
        $this->clip($this->honest, 90_000, 3);
        $this->clip($this->honest, 40_000, 4);
        $this->clip($this->cheater, 5_000, 72);
        Artisan::call('clips:enforce-window', ['--force' => true]);

        $report = app(ContestReport::class)->for($this->campaign);

        $this->assertSame(1, $report['stats']['participants']);
        $this->assertSame(2, $report['stats']['clips']);
        $this->assertSame(130_000, $report['stats']['views']);
        $this->assertSame(1, $report['stats']['refused']);

        $views = collect($report['boards'])->firstWhere('metric.value', 'views');
        $this->assertSame($this->honest->id, $views['podium'][0]->user_id);
        $this->assertSame('90,00 €', $views['podium'][0]->reward);

        $this->assertCount(1, $report['flagged']);
        $this->assertSame($this->cheater->id, $report['flagged'][0]->user_id);
        $this->assertSame(1, $report['flagged'][0]->count);
    }

    #[Test]
    public function the_admin_ranking_screen_shows_the_podium_the_figures_and_who_is_flagged(): void
    {
        $this->clip($this->honest, 90_000, 3);
        $this->clip($this->cheater, 5_000, 72);
        Artisan::call('clips:enforce-window', ['--force' => true]);

        // La modale s'ouvre sans erreur, et son gabarit affiche tout ce que l'équipe attend.
        Livewire::test(EditCampaign::class, ['record' => $this->campaign->getRouteKey()])
            ->mountAction('standings')
            ->assertSuccessful();

        $html = view('filament.campaigns.contest-standings', [
            'campaign' => $this->campaign->fresh(),
            'report' => app(ContestReport::class)->for($this->campaign->fresh()),
        ])->render();

        foreach (['Clips comptés', 'Clips écartés', 'Vrai replay.zone', '90,00 €', '1 clippeur signalé', 'Vrai 7fluxyy'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
    }

    #[Test]
    public function the_dashboard_card_only_exists_when_someone_is_flagged(): void
    {
        $this->assertFalse(FlaggedClippers::canView());

        $this->clip($this->cheater, 5_000, 72);
        Artisan::call('clips:enforce-window', ['--force' => true]);

        $this->assertTrue(FlaggedClippers::canView());

        Livewire::test(FlaggedClippers::class)
            ->assertSee('Clippeurs signalés')
            ->assertSee('Vrai 7fluxyy')
            ->assertSee('Campagne Momo Rocma');
    }

    #[Test]
    public function the_clips_list_can_be_filtered_to_the_flagged_ones(): void
    {
        $bad = $this->clip($this->cheater, 5_000, 72);
        $fine = $this->clip($this->honest, 4_000, 3);
        Artisan::call('clips:enforce-window', ['--force' => true]);

        Livewire::test(ListClips::class)
            ->set('activeTab', 'all')
            ->filterTable('outside_window')
            ->assertCanSeeTableRecords([$bad])
            ->assertCanNotSeeTableRecords([$fine]);
    }
}
