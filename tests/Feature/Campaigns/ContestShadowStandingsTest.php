<?php

namespace Tests\Feature\Campaigns;

use App\Contracts\SocialProvider;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Campaigns\ContestReport;
use App\Services\Campaigns\ContestService;
use App\Services\Campaigns\ContestShadowStandings;
use App\Services\Clips\ParticipationService;
use App\Services\Social\FakeSocialProvider;
use App\Services\Social\SocialProviderManager;
use App\Support\Social\RecentPost;
use App\Support\Social\RecentPostPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le classement qui compterait aussi les vidéos non soumises : une lecture réservée au
 * super-admin, qui n'attribue rien et ne change pas le classement officiel.
 */
class ContestShadowStandingsTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;

    protected User $submitter;

    protected User $holdingBack;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Cache::flush();
        config()->set('services.tiktok.client_key', null);

        $this->campaign = Campaign::factory()->contest([8_000, 5_000], [3_000])->create([
            'title' => 'DJIBRIL94 x LIGUE1',
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subHours(30),
            'ends_at' => now()->addDays(2),
            'contest_post_min_views' => 100,
            'required_hashtags' => null,
        ]);

        $this->submitter = $this->clipper('Yss', 'Yassin El Kallati');
        $this->holdingBack = $this->clipper('Rkn', 'Augustin Cha');
    }

    protected function clipper(string $pseudo, string $name): User
    {
        $user = User::factory()->create(['role' => UserRole::Clipper, 'pseudo' => $pseudo, 'name' => $name, 'email_verified_at' => now(), 'country' => 'FR', 'paypal_email' => $pseudo.'@example.com']);
        $account = SocialAccount::factory()->create(['user_id' => $user->id, 'platform' => Platform::TikTok, 'handle' => strtolower($pseudo)]);
        app(ParticipationService::class)->join($this->campaign, $user, $account);

        return $user;
    }

    protected function submittedClip(User $user, int $views, int $hoursAgo = 3): Clip
    {
        return Clip::factory()->create([
            'campaign_id' => $this->campaign->id, 'user_id' => $user->id, 'platform' => Platform::TikTok,
            'social_account_id' => SocialAccount::where('user_id', $user->id)->value('id'),
            'status' => ClipStatus::Approved, 'views_total' => $views, 'earned_cents' => 0,
            'posted_at' => now()->subHours($hoursAgo), 'submitted_at' => now()->subHours(2),
        ]);
    }

    /** TikTok, tel que chaque compte le montre : une liste de vidéos par pseudo. */
    protected function tiktokShows(array $videosByHandle): void
    {
        $provider = new class(Platform::TikTok, $videosByHandle) extends FakeSocialProvider
        {
            public function __construct(Platform $platform, protected array $byHandle)
            {
                parent::__construct($platform);
            }

            public function recentPosts(SocialAccount $account, int $limit = 20, ?string $cursor = null): RecentPostPage
            {
                return new RecentPostPage(collect($this->byHandle[$account->handle] ?? [])->map(fn (array $v) => new RecentPost(
                    externalPostId: $v['id'], url: null, thumbnailUrl: null, caption: $v['caption'], views: $v['views'], postedAt: $v['at'],
                ))->values(), null);
            }
        };

        $this->app->instance(SocialProviderManager::class, new class($provider) extends SocialProviderManager
        {
            public function __construct(protected SocialProvider $stub) {}

            public function for(Platform $platform): SocialProvider
            {
                return $this->stub;
            }
        });
    }

    protected function superAdmin(): User
    {
        return User::factory()->create(['role' => UserRole::SuperAdmin, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
    }

    // ------------------------------------------------------------------

    #[Test]
    public function unsubmitted_videos_can_put_someone_in_front_without_changing_the_official_ranking(): void
    {
        $this->submittedClip($this->submitter, 50_000);
        $this->submittedClip($this->submitter, 30_000);
        $this->submittedClip($this->holdingBack, 400);

        $this->tiktokShows(['rkn' => [
            ['id' => '7001', 'caption' => '#djibril94 le but #ligue1', 'views' => 90_000, 'at' => now()->subHours(5)],
            ['id' => '7002', 'caption' => 'mon clip djibril94 en entier', 'views' => 20_000, 'at' => now()->subHours(4)],
            ['id' => '7003', 'caption' => 'un clip sans rapport', 'views' => 500_000, 'at' => now()->subHours(4)],            // pas de la campagne
            ['id' => '7004', 'caption' => '#djibril94 vieux', 'views' => 700_000, 'at' => now()->subDays(9)],                  // avant le début
        ]]);

        $result = app(ContestShadowStandings::class)->compute($this->campaign);

        // Classement officiel : seuls les clips soumis comptent.
        $official = app(ContestService::class)->standings($this->campaign);
        $this->assertSame('Yassin El Kallati', $official->first()->name);

        // Classement complet : Rkn double Yss grâce à deux vidéos non soumises.
        $first = $result['views'][0];
        $this->assertSame('Augustin Cha', $first['name']);
        $this->assertSame(110_400, $first['total_views']);
        $this->assertSame(110_000, $first['un_views']);
        $this->assertSame(2, $first['un_clips']);
        $this->assertSame('80,00 €', $first['reward']);
        $this->assertSame('Yassin El Kallati', $result['views'][1]['name']);
        $this->assertSame('50,00 €', $result['views'][1]['reward']);
    }

    #[Test]
    public function videos_already_submitted_are_not_counted_twice(): void
    {
        $clip = $this->submittedClip($this->holdingBack, 5_000);
        $clip->update(['external_post_id' => '7001']);

        $this->tiktokShows(['rkn' => [
            ['id' => '7001', 'caption' => '#djibril94', 'views' => 5_000, 'at' => now()->subHours(3)],
        ]]);

        $result = app(ContestShadowStandings::class)->compute($this->campaign);

        $this->assertSame(5_000, $result['views'][0]['total_views']);
        $this->assertSame(0, $result['views'][0]['un_views']);
    }

    #[Test]
    public function the_posts_board_and_the_total_gains_include_what_is_not_submitted_yet(): void
    {
        $this->submittedClip($this->submitter, 500);
        $this->tiktokShows(['rkn' => array_map(fn ($i) => [
            'id' => (string) (7100 + $i), 'caption' => '#djibril94 n°'.$i, 'views' => 300, 'at' => now()->subHours($i + 1),
        ], range(1, 4))]);

        $result = app(ContestShadowStandings::class)->compute($this->campaign);

        $this->assertSame('Augustin Cha', $result['posts'][0]['name']);   // 4 clips à 300 vues, seuil 100
        $this->assertSame(4, $result['posts'][0]['total_posts']);
        $this->assertSame('30,00 €', $result['posts'][0]['reward']);

        $gain = collect($result['gains'])->firstWhere('name', 'Augustin Cha');
        $this->assertSame(8_000 + 3_000, $gain['cents']);
        $this->assertSame(['vues : 80,00 €', 'clips : 30,00 €'], $gain['parts']);
    }

    #[Test]
    public function the_command_computes_and_caches_it_for_running_contests_only(): void
    {
        $this->submittedClip($this->submitter, 1_000);
        $this->tiktokShows([]);

        $this->artisan('contests:shadow-standings')->assertSuccessful();

        $cached = ContestShadowStandings::cached($this->campaign);
        $this->assertSame('Yassin El Kallati', $cached['views'][0]['name']);

        Cache::flush();
        $this->campaign->update(['ends_at' => now()->subHour()]);
        Artisan::call('contests:shadow-standings');

        $this->assertNull(ContestShadowStandings::cached($this->campaign));
    }

    #[Test]
    public function only_the_super_admin_sees_it_in_the_standings_window(): void
    {
        $this->submittedClip($this->submitter, 1_000);
        $this->tiktokShows(['rkn' => [['id' => '7001', 'caption' => '#djibril94', 'views' => 9_000, 'at' => now()->subHours(2)]]]);
        app(ContestShadowStandings::class)->refresh($this->campaign);

        $this->actingAs($this->superAdmin());
        Livewire::test(EditCampaign::class, ['record' => $this->campaign->getRouteKey()])
            ->mountAction('standings')
            ->assertSuccessful();

        $render = fn (bool $show) => view('filament.campaigns.contest-standings', [
            'campaign' => $this->campaign->fresh(),
            'report' => app(ContestReport::class)->for($this->campaign->fresh()),
            'showShadow' => $show,
            'shadow' => $show ? ContestShadowStandings::cached($this->campaign) : null,
        ])->render();

        $this->assertStringContainsString('Classement avec les vidéos non soumises', $render(true));
        $this->assertStringContainsString('Augustin Cha', $render(true));
        $this->assertStringNotContainsString('Classement avec les vidéos non soumises', $render(false));
    }

    #[Test]
    public function a_moderator_never_gets_the_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Moderator, 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']));
        app(ContestShadowStandings::class)->refresh($this->campaign);

        $html = Livewire::test(EditCampaign::class, ['record' => $this->campaign->getRouteKey()])
            ->mountAction('standings')
            ->html();

        $this->assertStringNotContainsString('Classement avec les vidéos non soumises', $html);
    }
}
