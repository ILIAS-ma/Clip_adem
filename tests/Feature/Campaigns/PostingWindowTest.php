<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ContestMetric;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Livewire\SubmitClip;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Creator;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Campaigns\ContestService;
use App\Services\Clips\ParticipationService;
use App\Services\Creators\CampaignAnalytics;
use App\Services\Social\ClipSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Une vidéo publiée avant le début d'une campagne n'y compte pas : elle est
 * refusée à la soumission, rejetée au premier relevé, et absente du classement.
 * Les campagnes antérieures à la règle ne bougent pas.
 */
class PostingWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.tiktok.client_key', null);
        // La règle vaut pour les campagnes qui commencent à partir de cette date.
        config()->set('clipping.posting_window.enforced_from', now()->subDays(30)->toDateTimeString());
    }

    protected function contest(array $attributes = []): Campaign
    {
        return Campaign::factory()->contest([10_000])->create(array_merge([
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subHours(10),
            'ends_at' => now()->addDays(5),
        ], $attributes));
    }

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper, 'email_verified_at' => now(), 'pseudo' => 'clippeur'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR', 'paypal_email' => fake()->unique()->safeEmail(),
        ]);
    }

    protected function clip(Campaign $campaign, User $user, array $attributes = []): Clip
    {
        return Clip::factory()->create(array_merge([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => 1000,
            'earned_cents' => 0,
            'posted_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'compliance_status' => 'passed',
        ], $attributes));
    }

    // ------------------------------------------------------------------
    // La règle
    // ------------------------------------------------------------------

    #[Test]
    public function the_rule_applies_only_to_campaigns_that_start_after_its_date(): void
    {
        $after = $this->contest(['starts_at' => now()->subDays(2)]);
        $before = $this->contest(['starts_at' => now()->subDays(40)]);
        $undated = $this->contest(['starts_at' => null]);

        $this->assertTrue($after->enforcesPostingWindow());
        $this->assertFalse($before->enforcesPostingWindow());
        $this->assertFalse($undated->enforcesPostingWindow());
    }

    #[Test]
    public function a_video_outside_the_campaign_is_explained_and_an_unknown_date_passes(): void
    {
        $campaign = $this->contest();

        $this->assertNull($campaign->postingWindowViolation(now()->subHours(2)));
        $this->assertNull($campaign->postingWindowViolation(null));

        $this->assertStringContainsString('avant le début de la campagne', (string) $campaign->postingWindowViolation(now()->subHours(20)));
        $this->assertStringContainsString('après la fin de la campagne', (string) $campaign->postingWindowViolation(now()->addDays(9)));
    }

    #[Test]
    public function an_older_campaign_never_refuses_anything(): void
    {
        $old = $this->contest(['starts_at' => now()->subDays(40), 'ends_at' => now()->addDay()]);

        $this->assertNull($old->postingWindowViolation(now()->subDays(60)));
    }

    // ------------------------------------------------------------------
    // La soumission
    // ------------------------------------------------------------------

    protected function pickerFor(Campaign $campaign, User $clipper)
    {
        $account = SocialAccount::factory()->create(['user_id' => $clipper->id, 'platform' => Platform::TikTok, 'handle' => 'lea.clips']);
        app(ParticipationService::class)->join($campaign, $clipper, $account);

        return [$account, Livewire::actingAs($clipper)->test(SubmitClip::class, ['campaign' => $campaign])->call('loadVideos')];
    }

    #[Test]
    public function the_grid_locks_videos_published_before_the_start_and_says_why(): void
    {
        $campaign = $this->contest();
        [, $page] = $this->pickerFor($campaign, $this->clipper());

        $videos = collect($page->get('groups')[0]['videos']);

        // Le fournisseur simulé publie une vidéo toutes les 7 h : 7 h et 14 h en arrière.
        // La campagne a commencé il y a 10 h : seule la première est dedans.
        $inside = $videos->first(fn ($v) => $v['eligible']);
        $outside = $videos->first(fn ($v) => ! $v['eligible']);

        $this->assertNotNull($inside);
        $this->assertNotNull($outside);
        $this->assertStringContainsString('avant le début', $outside['ineligible_reason']);
        $this->assertGreaterThan(5, $videos->where('eligible', false)->count());
        $page->assertSee('Publiée avant le début')
            ->assertSee('publiées à partir du');
    }

    #[Test]
    public function a_video_outside_the_window_cannot_be_submitted_even_by_hand(): void
    {
        $campaign = $this->contest();
        [$account, $page] = $this->pickerFor($campaign, $this->clipper());

        $outside = collect($page->get('groups')[0]['videos'])->first(fn ($v) => ! $v['eligible']);

        $page->call('save', [$account->id.':'.$outside['id']], [])
            ->assertNoRedirect();

        $this->assertStringContainsString('avant le début', (string) $page->get('pickError'));
        $this->assertSame(0, Clip::count());
    }

    #[Test]
    public function a_video_inside_the_window_is_submitted_normally(): void
    {
        $campaign = $this->contest();
        [$account, $page] = $this->pickerFor($campaign, $this->clipper());

        $inside = collect($page->get('groups')[0]['videos'])->first(fn ($v) => $v['eligible']);

        $page->call('save', [$account->id.':'.$inside['id']], [])
            ->assertRedirect(route('campaigns.show', $campaign));

        $this->assertSame(1, Clip::count());
    }

    #[Test]
    public function an_older_campaign_still_offers_the_whole_history(): void
    {
        $old = $this->contest(['starts_at' => now()->subDays(40), 'ends_at' => now()->addDays(2)]);
        [, $page] = $this->pickerFor($old, $this->clipper());

        $this->assertCount(0, collect($page->get('groups')[0]['videos'])->where('eligible', false));
        $page->assertDontSee('Publiée avant le début');
    }

    // ------------------------------------------------------------------
    // Le premier relevé
    // ------------------------------------------------------------------

    protected function syncedClip(Campaign $campaign, array $attributes): Clip
    {
        $clipper = $this->clipper();
        $account = SocialAccount::factory()->create(['user_id' => $clipper->id, 'platform' => Platform::TikTok]);

        return $this->clip($campaign, $clipper, array_merge([
            'social_account_id' => $account->id,
            'compliance_status' => 'pending',
            'views_total' => 0,
        ], $attributes));
    }

    #[Test]
    public function the_first_reading_rejects_a_clip_published_before_the_start(): void
    {
        $campaign = $this->contest();
        $old = $this->syncedClip($campaign, ['posted_at' => now()->subDays(3)]);
        $fresh = $this->syncedClip($campaign, ['posted_at' => now()->subHours(3)]);

        app(ClipSyncService::class)->syncPlatform(Platform::TikTok);

        $this->assertSame(ClipStatus::Rejected, $old->fresh()->status);
        $this->assertStringContainsString('avant le début de la campagne', (string) $old->fresh()->rejection_reason);
        $this->assertSame(ClipStatus::Approved, $fresh->fresh()->status);
    }

    #[Test]
    public function an_older_campaign_keeps_clips_whatever_their_date(): void
    {
        $old = $this->contest(['starts_at' => now()->subDays(40), 'ends_at' => now()->addDay()]);
        $clip = $this->syncedClip($old, ['posted_at' => now()->subDays(60)]);

        app(ClipSyncService::class)->syncPlatform(Platform::TikTok);

        $this->assertSame(ClipStatus::Approved, $clip->fresh()->status);
    }

    // ------------------------------------------------------------------
    // Le classement et les statistiques
    // ------------------------------------------------------------------

    #[Test]
    public function the_ranking_ignores_an_approved_clip_published_before_the_start(): void
    {
        $campaign = $this->contest();
        $cheater = $this->clipper();
        $honest = User::factory()->create(['role' => UserRole::Clipper, 'pseudo' => 'max.edits']);

        $this->clip($campaign, $cheater, ['views_total' => 900_000, 'posted_at' => now()->subDays(8)]);
        $this->clip($campaign, $honest, ['views_total' => 5_000, 'posted_at' => now()->subHours(2)]);

        $rows = app(ContestService::class)->standings($campaign, board: ContestMetric::Views);

        $this->assertSame([$honest->id], $rows->pluck('user_id')->all());
        $this->assertSame(5_000, $rows->first()->views);
    }

    #[Test]
    public function the_ranking_of_an_older_campaign_is_left_untouched(): void
    {
        $old = $this->contest(['starts_at' => now()->subDays(40), 'ends_at' => now()->addDay()]);
        $clipper = $this->clipper();
        $this->clip($old, $clipper, ['views_total' => 900_000, 'posted_at' => now()->subDays(60)]);

        $rows = app(ContestService::class)->standings($old, board: ContestMetric::Views);

        $this->assertSame(900_000, $rows->first()->views);
    }

    #[Test]
    public function the_creator_statistics_count_the_same_clips_as_the_ranking(): void
    {
        $creator = Creator::factory()->create();
        $campaign = $this->contest(['creator_id' => $creator->id]);
        $clipper = $this->clipper();

        $this->clip($campaign, $clipper, ['views_total' => 900_000, 'posted_at' => now()->subDays(8)]);
        $this->clip($campaign, $clipper, ['views_total' => 7_000, 'posted_at' => now()->subHours(2)]);

        $overview = app(CampaignAnalytics::class)->overview([$campaign->id]);

        $this->assertSame(1, $overview['clips']);
        $this->assertSame(7_000, $overview['views']);
    }
}
