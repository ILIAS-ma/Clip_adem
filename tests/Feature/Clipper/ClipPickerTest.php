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
use App\Services\Clips\ClipComplianceChecker;
use App\Services\Clips\ParticipationService;
use App\Services\Social\TikTokProvider;
use App\Support\Social\PostMetrics;
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

        $component->call('save', $this->keys(array_slice($videos, 0, 3)), [])
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
        $component->call('save', $this->keys([$first]), []);

        $again = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $videos = $again->get('groups')[0]['videos'];
        $this->assertSame($first['id'], $videos[0]['id']);
        $this->assertTrue($videos[0]['submitted']);
        $this->assertFalse($videos[1]['submitted']);
        $again->assertSee('Soumis');

        // Et on ne la soumet pas deux fois.
        $again->call('save', $this->keys([$videos[0]]), [])
            ->assertNoRedirect()
            ->assertSet('pickError', 'Une vidéo n’est plus disponible. Actualisez la liste.');
        $this->assertSame(1, Clip::count());
    }

    #[Test]
    public function an_unpaid_clip_is_withdrawn_by_unticking_it(): void
    {
        $component = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $videos = $component->get('groups')[0]['videos'];
        $component->call('save', $this->keys(array_slice($videos, 0, 2)), []);
        $this->assertSame(2, Clip::count());

        $again = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $this->assertTrue($again->get('groups')[0]['videos'][0]['removable']);

        // Une vidéo retirée, une autre ajoutée, en un seul enregistrement.
        $again->call('save', $this->keys([$videos[2]]), $this->keys([$videos[0]]))
            ->assertRedirect(route('campaigns.show', $this->campaign));

        $this->assertSame(2, Clip::count());
        $this->assertNull(Clip::where('external_post_id', $videos[0]['id'])->first());
        $this->assertNotNull(Clip::where('external_post_id', $videos[2]['id'])->first());

        // Et la vidéo retirée redevient libre.
        $free = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos')
            ->get('groups')[0]['videos'][0];
        $this->assertFalse($free['submitted']);
    }

    #[Test]
    public function a_clip_that_already_earned_money_cannot_be_withdrawn(): void
    {
        $component = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $video = $component->get('groups')[0]['videos'][0];
        $component->call('save', $this->keys([$video]), []);

        // Un gain est une écriture du grand livre : le clip ne s'efface plus.
        Clip::firstOrFail()->forceFill(['earned_cents' => 250])->save();

        $again = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $this->assertFalse($again->get('groups')[0]['videos'][0]['removable']);

        $again->call('save', [], $this->keys([$video]))
            ->assertNoRedirect()
            ->assertSet('pickError', 'Ce clip a déjà rapporté de l\'argent : il ne peut plus être retiré.');

        $this->assertSame(1, Clip::count());
    }

    #[Test]
    public function someone_elses_clip_cannot_be_withdrawn(): void
    {
        $component = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $video = $component->get('groups')[0]['videos'][0];
        $clip = Clip::factory()->create([
            'campaign_id' => $this->campaign->id,
            'platform' => Platform::TikTok,
            'external_post_id' => $video['id'],
            'earned_cents' => 0,
        ]);

        $again = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $this->assertFalse($again->get('groups')[0]['videos'][0]['removable']);
        $again->call('save', [], $this->keys([$video]));

        $this->assertNotNull($clip->fresh());
    }

    #[Test]
    public function the_minimum_duration_rule_no_longer_exists(): void
    {
        $clip = Clip::factory()->create(['duration_seconds' => 4]);
        $metrics = new PostMetrics(
            externalPostId: $clip->external_post_id,
            views: 10,
            durationSeconds: 4,
            ownerExternalId: null,
        );

        app(ClipComplianceChecker::class)->check($clip, $metrics);

        $labels = array_column($clip->fresh()->compliance['checks'] ?? [], 'label');
        $this->assertNotContains("Durée d'au moins 10 secondes", $labels);
    }

    #[Test]
    public function a_clipper_who_joined_with_a_dead_account_can_still_submit_with_his_current_one(): void
    {
        /*
         * Le cas signalé : il a rejoint la campagne avec un ancien compte TikTok,
         * devenu inactif ; son compte actuel est lié et actif mais n'a jamais rejoint.
         * La grille restait vide et rien n'expliquait pourquoi.
         */
        $clipper = User::factory()->create([
            'role' => UserRole::Clipper, 'email_verified_at' => now(), 'pseudo' => 'replay.vision',
            'country' => 'FR', 'paypal_email' => 'r@paypal.test',
        ]);
        $dead = SocialAccount::factory()->create(['user_id' => $clipper->id, 'platform' => Platform::TikTok, 'handle' => 'ancien.compte']);
        app(ParticipationService::class)->join($this->campaign, $clipper, $dead);
        $dead->forceFill(['is_active' => false])->save();

        $current = SocialAccount::factory()->create(['user_id' => $clipper->id, 'platform' => Platform::TikTok, 'handle' => 'compte.actuel']);

        // La page propose de soumettre, au lieu d'un bouton qui mène à une grille vide.
        $this->actingAs($clipper)
            ->get(route('campaigns.show', $this->campaign))
            ->assertOk()
            ->assertSee('Soumettre mes vidéos');

        $component = Livewire::actingAs($clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $groups = $component->get('groups');
        $this->assertCount(1, $groups);
        $this->assertSame($current->id, $groups[0]['account_id']);

        $video = $groups[0]['videos'][0];
        $component->call('save', [$current->id.':'.$video['id']], [])
            ->assertRedirect(route('campaigns.show', $this->campaign));

        // Le compte actuel a rejoint, et le clip est rattaché à lui.
        $this->assertNotNull(app(ParticipationService::class)->existing($this->campaign, $current));
        $clip = Clip::firstOrFail();
        $this->assertSame($current->id, $clip->social_account_id);
        $this->assertSame($clipper->id, $clip->user_id);
    }

    #[Test]
    public function a_campaign_that_does_not_accept_the_platform_offers_no_account(): void
    {
        $youtubeOnly = Campaign::factory()->withRate(Platform::YouTube)->create(['status' => CampaignStatus::Active]);

        $groups = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $youtubeOnly])
            ->call('loadVideos')
            ->get('groups');

        $this->assertSame([], $groups);
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
            ->call('save', [$this->account->id.':7000000000000000001'], [])
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
            ->call('save', [$other->id.':123'], [])
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
