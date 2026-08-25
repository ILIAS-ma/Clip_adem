<?php

namespace Tests\Feature\Clipper;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\BudgetTransaction;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ClipViewSnapshot;
use App\Models\SocialAccount;
use App\Models\SocialSyncRun;
use App\Models\User;
use App\Services\Social\ClipSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClipSyncTest extends TestCase
{
    use RefreshDatabase;

    protected ClipSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sync = app(ClipSyncService::class);
    }

    protected function campaign(array $attributes = []): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create(array_merge([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 500_000,
                'required_hashtags' => ['#nayra'],
            ], $attributes));
    }

    protected function clip(Campaign $campaign, array $attributes = []): Clip
    {
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);
        $account = SocialAccount::factory()->create([
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
        ]);

        return Clip::factory()->create(array_merge([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $clipper->getKey(),
            'social_account_id' => $account->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'posted_at' => now()->subDays(2),
            'views_total' => 0,
        ], $attributes));
    }

    #[Test]
    public function a_sync_records_a_snapshot_and_credits_the_budget(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign);

        $run = $this->sync->syncPlatform(Platform::TikTok);

        $this->assertSame(1, $run->clips_synced);
        $this->assertNotNull($run->finished_at);

        $clip->refresh();
        $this->assertGreaterThan(0, $clip->views_total);
        $this->assertGreaterThan(0, $clip->earned_cents);
        $this->assertNotNull($clip->last_synced_at);

        $this->assertSame(1, ClipViewSnapshot::where('clip_id', $clip->getKey())->count());
        $this->assertSame($clip->earned_cents, (int) BudgetTransaction::sum('amount_cents'));
    }

    #[Test]
    public function the_first_sync_runs_the_compliance_check(): void
    {
        // C'est le premier relevé qui livre enfin la légende et la durée
        // réelles : avant, la conformité n'est pas vérifiable.
        $campaign = $this->campaign();
        $clip = $this->clip($campaign, ['compliance_status' => 'pending']);

        $this->sync->syncPlatform(Platform::TikTok);

        $clip->refresh();
        $this->assertContains($clip->compliance_status, ['passed', 'failed']);
        $this->assertNotEmpty($clip->compliance['checks']);
        $this->assertNotNull($clip->caption);
    }

    #[Test]
    public function running_the_sync_twice_never_pays_twice_for_the_same_views(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign);

        $this->sync->syncPlatform(Platform::TikTok);
        $afterFirst = $clip->fresh()->earned_cents;

        // Le clip vient d'être relevé : la cadence dégressive le juge pas encore dû.
        $second = $this->sync->syncPlatform(Platform::TikTok);

        $this->assertSame(0, $second->clips_synced);
        $this->assertSame($afterFirst, $clip->fresh()->earned_cents);
    }

    #[Test]
    public function a_second_snapshot_only_credits_the_new_views(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign);

        $this->sync->syncPlatform(Platform::TikTok);
        $first = $clip->fresh();

        // On force l'éligibilité en reculant le dernier relevé.
        $first->forceFill(['last_synced_at' => now()->subDays(2)])->save();

        $this->sync->syncPlatform(Platform::TikTok);
        $second = $clip->fresh();

        $this->assertGreaterThanOrEqual($first->earned_cents, $second->earned_cents);
        $this->assertSame(
            $second->earned_cents,
            (int) BudgetTransaction::where('clip_id', $clip->getKey())->sum('amount_cents'),
        );
    }

    #[Test]
    public function an_account_needing_reconnection_is_skipped(): void
    {
        // Interroger un compte mort ne rapporte que des 401 et consomme le
        // quota qui manquera aux comptes valides.
        $campaign = $this->campaign();
        $clip = $this->clip($campaign);

        $clip->socialAccount->forceFill(['needs_reconnect' => true])->save();

        $run = $this->sync->syncPlatform(Platform::TikTok);

        $this->assertSame(0, $run->clips_synced);
        $this->assertSame(0, $clip->fresh()->earned_cents);
    }

    #[Test]
    public function a_clip_of_an_exhausted_campaign_keeps_counting_views_without_being_paid(): void
    {
        $campaign = $this->campaign(['budget_total_cents' => 100_000]);
        $campaign->forceFill([
            'spent_cents' => 100_000,
            'status' => CampaignStatus::Exhausted,
        ])->save();

        $clip = $this->clip($campaign);

        $this->sync->syncPlatform(Platform::TikTok);

        $clip->refresh();
        $this->assertGreaterThan(0, $clip->views_total, 'Les vues doivent continuer d\'être comptées.');
        $this->assertSame(0, $clip->earned_cents, 'Aucune vue ne doit être payée sur un budget épuisé.');
        $this->assertSame(100_000, $campaign->fresh()->spent_cents);
    }

    #[Test]
    public function a_clip_awaiting_moderation_is_synced_but_not_paid(): void
    {
        $campaign = $this->campaign();
        $clip = $this->clip($campaign, ['status' => ClipStatus::PendingReview]);

        $this->sync->syncPlatform(Platform::TikTok);

        $clip->refresh();
        $this->assertGreaterThan(0, $clip->views_total);
        $this->assertSame(0, $clip->earned_cents);
    }

    #[Test]
    public function clips_older_than_the_cutoff_are_left_alone(): void
    {
        $campaign = $this->campaign();
        $this->clip($campaign, ['posted_at' => now()->subDays(45)]);

        $this->assertCount(0, $this->sync->dueClips(Platform::TikTok));
    }

    #[Test]
    public function a_fresh_clip_is_due_more_often_than_an_old_one(): void
    {
        $campaign = $this->campaign();

        $fresh = $this->clip($campaign, [
            'posted_at' => now()->subDay(),
            'last_synced_at' => now()->subHours(4),
        ]);
        $old = $this->clip($campaign, [
            'posted_at' => now()->subDays(20),
            'last_synced_at' => now()->subHours(4),
        ]);

        $due = $this->sync->dueClips(Platform::TikTok)->pluck('id');

        $this->assertTrue($due->contains($fresh->getKey()), 'Un clip récent est relevé toutes les 3 h.');
        $this->assertFalse($due->contains($old->getKey()), 'Un clip ancien attend 24 h entre deux relevés.');
    }

    #[Test]
    public function every_run_is_journalled(): void
    {
        // Sans ce journal, un dépassement de quota se diagnostique à l'aveugle.
        $this->sync->syncPlatform(Platform::YouTube);

        $run = SocialSyncRun::where('platform', Platform::YouTube)->sole();
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
        $this->assertFalse($run->rate_limited);
    }

    #[Test]
    public function the_artisan_command_runs_every_platform(): void
    {
        $this->artisan('clips:sync')->assertSuccessful();

        $this->assertSame(3, SocialSyncRun::count());
    }
}
