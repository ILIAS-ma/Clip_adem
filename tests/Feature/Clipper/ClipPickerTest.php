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
        // six vidéos stables par compte.
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
        $this->assertCount(6, $videos);

        $component->call('pick', $this->account->id, $videos[0]['id'])
            ->assertRedirect(route('campaigns.show', $this->campaign));

        $clip = Clip::firstOrFail();
        $this->assertSame($videos[0]['id'], $clip->external_post_id);
        $this->assertSame($this->account->id, $clip->social_account_id);
        $this->assertSame(ClipStatus::PendingReview, $clip->status);
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
        $this->assertCount(5, $again->get('groups')[0]['videos']);
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
    public function tiktok_recent_videos_are_read_from_the_video_list_api(): void
    {
        Http::fake(['open.tiktokapis.com/v2/video/list/*' => Http::response(['data' => ['videos' => [[
            'id' => '7698100369829743905',
            'view_count' => 855,
            'title' => 'Mon clip',
            'cover_image_url' => 'https://p16.tiktokcdn.com/cover.jpg',
            'share_url' => 'https://www.tiktok.com/@vrai.pseudo/video/7698100369829743905?utm=x',
            'create_time' => 1760000000,
        ]]], 'error' => ['code' => 'ok']])]);

        $posts = app(TikTokProvider::class)->recentPosts($this->account);

        $this->assertCount(1, $posts);
        $this->assertSame('7698100369829743905', $posts[0]->externalPostId);
        $this->assertSame(855, $posts[0]->views);
        $this->assertSame('Mon clip', $posts[0]->caption);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'fields=') && $request['max_count'] === 12);
    }
}
