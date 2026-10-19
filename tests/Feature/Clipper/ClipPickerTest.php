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
 * Soumettre ses clips depuis la grille de ses vidéos TikTok : on en coche une
 * ou plusieurs, on valide. Plus de lien à coller.
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
        $this->clipper = User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'lina.clips',
            'country' => 'FR',
            'paypal_email' => 'lina@paypal.test',
        ]);
        $this->account = SocialAccount::factory()->create(['user_id' => $this->clipper->id, 'platform' => Platform::TikTok, 'handle' => 'lina.clips']);

        app(ParticipationService::class)->join($this->campaign, $this->clipper, $this->account);
    }

    /** @param  list<array<string, mixed>>  $videos */
    protected function keys(array $videos): array
    {
        return array_map(fn (array $video) => $this->account->id.':'.$video['id'], $videos);
    }

    #[Test]
    public function several_videos_are_submitted_in_one_go(): void
    {
        $component = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $videos = $component->get('groups')[0]['videos'];
        $this->assertCount(24, $videos);

        $component->call('submitSelected', $this->keys(array_slice($videos, 0, 3)))
            ->assertRedirect(route('campaigns.show', $this->campaign));

        $this->assertSame(3, Clip::count());
        $clip = Clip::where('external_post_id', $videos[0]['id'])->firstOrFail();
        $this->assertSame($this->account->id, $clip->social_account_id);
        $this->assertSame(ClipStatus::Approved, $clip->status);
        $this->assertStringContainsString('/video/'.$videos[0]['id'], $clip->url);
        // La vignette est là tout de suite, sans attendre le premier relevé.
        $this->assertSame($videos[0]['thumbnail'], $clip->thumbnail_url);
    }

    #[Test]
    public function submitted_videos_stay_in_the_grid_marked_as_such(): void
    {
        $component = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $first = $component->get('groups')[0]['videos'][0];
        $component->call('submitSelected', $this->keys([$first]));

        $again = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $videos = $again->get('groups')[0]['videos'];
        $this->assertSame($first['id'], $videos[0]['id']);
        $this->assertTrue($videos[0]['submitted']);
        $this->assertFalse($videos[1]['submitted']);
        $again->assertSee('Soumis');

        // Et on ne la soumet pas deux fois.
        $again->call('submitSelected', $this->keys([$videos[0]]))
            ->assertNoRedirect()
            ->assertSet('pickError', 'Une vidéo n’est plus disponible. Actualisez la liste.');
        $this->assertSame(1, Clip::count());
    }

    #[Test]
    public function the_whole_history_is_reachable_by_scrolling(): void
    {
        $component = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $this->assertTrue($component->get('groups')[0]['has_more']);

        $component->call('loadMore');

        // Les 30 vidéos du compte, sur deux pages TikTok.
        $this->assertCount(30, $component->get('groups')[0]['videos']);
        $this->assertFalse($component->get('groups')[0]['has_more']);
    }

    #[Test]
    public function a_video_outside_the_offered_list_is_refused(): void
    {
        Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos')
            ->call('submitSelected', [$this->account->id.':7000000000000000001'])
            ->assertSet('pickError', 'Une vidéo n’est plus disponible. Actualisez la liste.');

        $this->assertSame(0, Clip::count());
    }

    #[Test]
    public function someone_elses_account_cannot_be_used(): void
    {
        $other = SocialAccount::factory()->create(['platform' => Platform::TikTok]);

        Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos')
            ->call('submitSelected', [$other->id.':123'])
            ->assertNotFound();
    }

    #[Test]
    public function the_campaign_page_offers_the_grid_and_no_link_field(): void
    {
        $this->actingAs($this->clipper)
            ->get(route('campaigns.show', $this->campaign))
            ->assertOk()
            ->assertSee('Soumettre mes vidéos')
            ->assertDontSee('Ou collez le lien');
    }

    #[Test]
    public function tiktok_videos_are_read_page_by_page_from_the_video_list_api(): void
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
