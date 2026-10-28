<?php

namespace Tests\Feature\Moderation;

use App\Contracts\SocialProvider;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\ModerationAction;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Filament\Resources\Clips\Pages\ListClips;
use App\Filament\Widgets\FlaggedClippers;
use App\Livewire\SubmitClip;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Clips\ParticipationService;
use App\Services\Moderation\ReuploadDetector;
use App\Services\Social\FakeSocialProvider;
use App\Services\Social\SocialProviderManager;
use App\Support\Social\PostMetrics;
use App\Support\Social\RecentPost;
use App\Support\Social\RecentPostPage;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Une vidéo republiée reçoit un nouvel identifiant et une nouvelle date : la plateforme ne la
 * relie pas à l'originale. Ces tests montrent d'abord que la copie est acceptée à la
 * soumission (la faille), puis qu'elle est signalée à l'équipe sans être refusée.
 */
class ReuploadDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;

    protected User $clipper;

    protected SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('services.tiktok.client_key', null);
        config()->set('clipping.posting_window.enforced_from', now()->subDays(30)->toDateTimeString());

        $this->campaign = Campaign::factory()->contest([10_000])->create([
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subHours(10),
            'ends_at' => now()->addDays(5),
        ]);

        $this->clipper = User::factory()->create([
            'role' => UserRole::Clipper, 'email_verified_at' => now(), 'pseudo' => 'lea.clips', 'name' => 'Léa Martin',
            'country' => 'FR', 'paypal_email' => 'lea@example.com',
        ]);
        $this->account = SocialAccount::factory()->create(['user_id' => $this->clipper->id, 'platform' => Platform::TikTok, 'handle' => 'lea.clips']);
        app(ParticipationService::class)->join($this->campaign, $this->clipper, $this->account);
    }

    /** Un compte TikTok dont on décide des vidéos. */
    protected function accountHas(array $videos): void
    {
        $provider = new class(Platform::TikTok, $videos) extends FakeSocialProvider
        {
            public function __construct(Platform $platform, protected array $videos)
            {
                parent::__construct($platform);
            }

            public function recentPosts(SocialAccount $account, int $limit = 20, ?string $cursor = null): RecentPostPage
            {
                return new RecentPostPage(collect($this->videos)->map(fn (array $v) => new RecentPost(
                    externalPostId: $v['id'], url: "https://www.tiktok.com/@lea.clips/video/{$v['id']}", thumbnailUrl: null,
                    caption: $v['caption'], views: 1000, postedAt: $v['posted_at'],
                ))->values(), null);
            }

            public function fetchPosts(SocialAccount $account, array $externalIds): Collection
            {
                return collect($this->videos)->whereIn('id', $externalIds)->map(fn (array $v) => new PostMetrics(
                    externalPostId: $v['id'], views: 1000, caption: $v['caption'], durationSeconds: $v['duration'],
                    postedAt: $v['posted_at'], ownerExternalId: $account->external_account_id,
                ))->values();
            }
        };

        $manager = new class($provider) extends SocialProviderManager
        {
            public function __construct(protected SocialProvider $stub) {}

            public function for(Platform $platform): SocialProvider
            {
                return $this->stub;
            }
        };

        $this->app->instance(SocialProviderManager::class, $manager);
    }

    protected function video(string $id, string $caption, int $duration, CarbonInterface $at): array
    {
        return ['id' => $id, 'caption' => $caption, 'duration' => $duration, 'posted_at' => $at];
    }

    protected function submitted(string $id, CarbonInterface $at, string $caption): Clip
    {
        return Clip::factory()->create([
            'campaign_id' => $this->campaign->id, 'user_id' => $this->clipper->id, 'social_account_id' => $this->account->id,
            'platform' => Platform::TikTok, 'external_post_id' => $id, 'status' => ClipStatus::Approved,
            'posted_at' => $at, 'submitted_at' => now(), 'caption' => $caption, 'compliance' => null,
        ]);
    }

    // ------------------------------------------------------------------
    // La faille, puis le signalement
    // ------------------------------------------------------------------

    #[Test]
    public function a_republished_copy_is_accepted_at_submission_then_flagged_for_review(): void
    {
        $caption = 'Quand ma mère découvre le prix du mariage #momorocma';
        $this->accountHas([
            $this->video('9001', $caption, 23, now()->subDays(8)),   // l'original, publié bien avant la campagne
            $this->video('9002', $caption, 23, now()->subHours(2)),  // la copie, publiée hier soir
        ]);

        $page = Livewire::actingAs($this->clipper)->test(SubmitClip::class, ['campaign' => $this->campaign])->call('loadVideos');
        $videos = collect($page->get('groups')[0]['videos']);

        // L'original est bloqué par la règle de date, la copie ne l'est pas : c'est la faille.
        $this->assertFalse($videos->firstWhere('id', '9001')['eligible']);
        $this->assertTrue($videos->firstWhere('id', '9002')['eligible']);

        $page->call('save', [$this->account->id.':9002'], [])->assertRedirect(route('campaigns.show', $this->campaign));
        $copy = Clip::where('external_post_id', '9002')->sole();
        $this->assertSame(ClipStatus::Approved, $copy->status);

        // Le contrôle des reprises la repère, sans la refuser.
        Artisan::call('clips:detect-reuploads');

        $this->assertSame(ClipStatus::Approved, $copy->fresh()->status);
        $log = ModerationLog::where('action', ModerationAction::ClipSuspectedReupload)->sole();
        $this->assertSame($copy->id, $log->subject_id);
        $this->assertSame('9001', $log->meta['original_post_id']);
        $this->assertStringContainsString('Même durée (23 s)', $log->reason);
    }

    #[Test]
    public function the_command_lists_what_it_found_and_never_rereads_a_clip_it_already_checked(): void
    {
        $caption = 'Il a dit non à la bague de fiançailles #fyp';
        $this->accountHas([
            $this->video('9001', $caption, 31, now()->subDays(9)),
            $this->video('9002', $caption, 31, now()->subHours(3)),
        ]);
        $this->submitted('9002', now()->subHours(3), $caption);

        $this->artisan('clips:detect-reuploads')
            ->expectsOutputToContain('1 reprise(s) probable(s) signalée(s)')
            ->assertSuccessful();

        $this->artisan('clips:detect-reuploads')
            ->expectsOutputToContain('Aucun clip à contrôler')
            ->assertSuccessful();

        $this->assertSame(1, ModerationLog::where('action', ModerationAction::ClipSuspectedReupload)->count());
    }

    #[Test]
    public function different_videos_are_not_flagged(): void
    {
        $this->accountHas([
            $this->video('9001', 'Mon premier clip sur le mariage de ma cousine', 23, now()->subDays(8)),
            $this->video('9002', 'Les coulisses du tournage avec toute léquipe', 23, now()->subHours(2)),   // même durée, autre texte
            $this->video('9003', 'Mon premier clip sur le mariage de ma cousine', 40, now()->subHours(1)),  // même texte, autre durée
        ]);
        $this->submitted('9002', now()->subHours(2), 'x');
        $this->submitted('9003', now()->subHours(1), 'x');

        Artisan::call('clips:detect-reuploads');

        $this->assertSame(0, ModerationLog::where('action', ModerationAction::ClipSuspectedReupload)->count());
    }

    #[Test]
    public function a_template_caption_repeated_on_many_videos_is_not_a_reupload(): void
    {
        // La même légende sur chaque vidéo, des durées qui se recoupent : du hasard, pas une reprise.
        $videos = [];
        foreach (range(1, 12) as $i) {
            $videos[] = $this->video((string) (9000 + $i), 'Momo Rocma le meilleur #iplant', 20 + ($i % 3), now()->subDays(13 - $i));
        }
        $this->accountHas($videos);
        $this->submitted('9012', now()->subDay(), 'Momo Rocma le meilleur #iplant');

        Artisan::call('clips:detect-reuploads');

        $this->assertSame(0, ModerationLog::where('action', ModerationAction::ClipSuspectedReupload)->count());
    }

    #[Test]
    public function a_caption_made_only_of_hashtags_proves_nothing(): void
    {
        $this->accountHas([
            $this->video('9001', '#fyp #pourtoi #momorocma', 12, now()->subDays(8)),
            $this->video('9002', '#fyp #pourtoi #momorocma', 12, now()->subHours(2)),
        ]);
        $this->submitted('9002', now()->subHours(2), '#fyp #pourtoi #momorocma');

        Artisan::call('clips:detect-reuploads');

        $this->assertSame(0, ModerationLog::where('action', ModerationAction::ClipSuspectedReupload)->count());
    }

    // ------------------------------------------------------------------
    // Le détecteur seul
    // ------------------------------------------------------------------

    #[Test]
    public function the_detector_keeps_the_oldest_lookalike_and_ignores_newer_videos(): void
    {
        $detector = new ReuploadDetector;
        $caption = 'Une très longue légende écrite à la main';
        $post = $this->video('c', $caption, 20, now());

        $match = $detector->findOriginal($post, [
            $this->video('b', $caption, 20, now()->subDays(2)),
            $this->video('a', $caption.' !', 20, now()->subDays(5)),
            $this->video('z', $caption, 20, now()->addDay()),  // plus récente : pas un original
        ]);

        $this->assertSame('a', $match['post']['id']);
        $this->assertGreaterThanOrEqual(ReuploadDetector::MIN_SIMILARITY, $match['similarity']);
        $this->assertNull($detector->findOriginal($post, [$this->video('z', $caption, 20, now()->addDay())]));
    }

    #[Test]
    public function the_detector_ignores_hashtags_mentions_and_punctuation(): void
    {
        $this->assertSame('mon histoire de mariage', ReuploadDetector::text('Mon histoire de mariage !! #fyp #momorocma @Momo 🔱'));
    }

    // ------------------------------------------------------------------
    // Ce que l'équipe voit
    // ------------------------------------------------------------------

    #[Test]
    public function the_flagged_clipper_shows_up_on_the_dashboard_and_the_clip_filter(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]));

        $copy = $this->submitted('9002', now()->subHours(2), 'x');
        $fine = $this->submitted('9003', now()->subHours(1), 'y');
        ModerationLog::record(ModerationAction::ClipSuspectedReupload, $copy, null, 'Même durée (23 s)');

        $this->assertTrue(FlaggedClippers::canView());
        Livewire::test(FlaggedClippers::class)
            ->assertSee('Reprises à vérifier')
            ->assertSee('Léa Martin');

        Livewire::test(ListClips::class)
            ->set('activeTab', 'all')
            ->filterTable('suspected_reupload')
            ->assertCanSeeTableRecords([$copy])
            ->assertCanNotSeeTableRecords([$fine]);
    }
}
