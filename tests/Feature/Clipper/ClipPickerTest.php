<?php

namespace Tests\Feature\Clipper;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Livewire\SubmitClip;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Clips\ParticipationService;
use App\Services\Social\TikTokProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Soumettre d'un toucher, depuis la liste de ses vidéos récentes : le chemin
 * du téléphone, sans lien à copier.
 */
class ClipPickerTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;

    protected User $clipper;

    protected SocialAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Sans clés TikTok, la plateforme tourne sur le fournisseur simulé :
        // trente vidéos stables par compte, paginées comme la vraie API.
        config()->set('services.tiktok.client_key', null);

        $this->campaign = Campaign::factory()->withRate(Platform::TikTok)->create(['status' => CampaignStatus::Active]);
        $this->clipper = User::factory()->create(['role' => UserRole::Clipper, 'email_verified_at' => now()]);
        $this->account = SocialAccount::factory()->create(['user_id' => $this->clipper->id, 'platform' => Platform::TikTok, 'handle' => 'lina.clips']);

        app(ParticipationService::class)->join($this->campaign, $this->clipper, $this->account);
    }

    #[Test]
    public function a_tap_on_a_recent_video_submits_it(): void
    {
        $component = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $videos = $component->get('groups')[0]['videos'];
        $this->assertCount(12, $videos);

        // On reste sur la liste, la vidéo soumise en moins, prêt pour la suivante.
        $component->call('pick', $this->account->id, $videos[0]['id'])
            ->assertNoRedirect()
            ->assertSet('pickedCount', 1)
            ->assertSee('1 clip soumis');

        $this->assertNotContains($videos[0]['id'], array_column($component->get('groups')[0]['videos'], 'id'));

        $clip = Clip::firstOrFail();
        $this->assertSame($videos[0]['id'], $clip->external_post_id);
        $this->assertSame($this->account->id, $clip->social_account_id);
        $this->assertSame(ClipStatus::Approved, $clip->status);
        $this->assertStringContainsString('/video/'.$videos[0]['id'], $clip->url);
    }

    #[Test]
    public function already_submitted_videos_are_not_offered_again(): void
    {
        $component = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $first = $component->get('groups')[0]['videos'][0]['id'];
        $component->call('pick', $this->account->id, $first);

        $again = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $this->assertNotContains($first, array_column($again->get('groups')[0]['videos'], 'id'));
        $this->assertCount(12, $again->get('groups')[0]['videos']);
    }

    #[Test]
    public function once_the_latest_twelve_are_submitted_older_videos_still_show_up(): void
    {
        /*
         * Le bug signalé : douze clips soumis, et la liste affirmait qu'il n'y
         * avait plus de vidéo publique alors que le compte en avait vingt de
         * plus. Elle ne lisait que la première page.
         */
        $component = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        foreach ($component->get('groups')[0]['videos'] as $video) {
            $component->call('pick', $this->account->id, $video['id']);
        }

        $this->assertSame(12, Clip::count());

        $again = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $this->assertCount(12, $again->get('groups')[0]['videos']);
        $this->assertTrue($again->get('groups')[0]['has_more']);

        $again->call('loadMore');

        // 30 vidéos en tout, 12 soumises : il en reste 18, toutes visibles.
        $this->assertCount(18, $again->get('groups')[0]['videos']);
        $this->assertFalse($again->get('groups')[0]['has_more']);
    }

    #[Test]
    public function a_video_outside_the_offered_list_is_refused(): void
    {
        Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos')
            ->call('pick', $this->account->id, '7000000000000000001')
            ->assertSet('pickError', 'Cette vidéo n’est plus dans la liste. Rechargez-la et réessayez.');

        $this->assertSame(0, Clip::count());
    }

    #[Test]
    public function someone_elses_account_cannot_be_used(): void
    {
        $other = SocialAccount::factory()->create(['platform' => Platform::TikTok]);

        Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos')
            ->call('pick', $other->id, '123')
            ->assertNotFound();
    }

    #[Test]
    public function pasting_a_link_still_works(): void
    {
        Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->set('url', '  https://www.tiktok.com/@lina.clips/video/7123456789012345678  ')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame('7123456789012345678', Clip::firstOrFail()->external_post_id);
    }

    #[Test]
    public function a_short_link_copied_from_the_phone_app_is_followed(): void
    {
        Http::fake([
            'vm.tiktok.com/*' => Http::response('', 301, ['Location' => 'https://www.tiktok.com/@lina.clips/video/7123456789012345678?_r=1']),
        ]);

        Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->set('url', 'https://vm.tiktok.com/ZNdAbCdEf/')
            ->call('submit')
            ->assertHasNoErrors();

        $clip = Clip::firstOrFail();
        $this->assertSame('7123456789012345678', $clip->external_post_id);
        $this->assertSame(ClipStatus::Approved, $clip->status);
    }

    #[Test]
    public function tiktok_recent_videos_are_read_from_the_video_list_api(): void
    {
        Http::fake(['open.tiktokapis.com/v2/video/list/*' => Http::response(['data' => ['cursor' => 1759990000000, 'has_more' => true, 'videos' => [[
            'id' => '7698100369829743905',
            'view_count' => 855,
            'title' => 'Mon clip',
            'cover_image_url' => 'https://p16.tiktokcdn.com/cover.jpg',
            'share_url' => 'https://www.tiktok.com/@vrai.pseudo/video/7698100369829743905?utm=x',
            'create_time' => 1760000000,
        ]]], 'error' => ['code' => 'ok']])]);

        $page = app(TikTokProvider::class)->recentPosts($this->account, 20, '1760000000000');

        $this->assertCount(1, $page->posts);
        $this->assertSame('7698100369829743905', $page->posts[0]->externalPostId);
        $this->assertSame(855, $page->posts[0]->views);
        $this->assertSame('Mon clip', $page->posts[0]->caption);
        $this->assertSame('1759990000000', $page->nextCursor);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'fields=')
            && $request['max_count'] === 20
            && $request['cursor'] === 1760000000000);
    }
}
