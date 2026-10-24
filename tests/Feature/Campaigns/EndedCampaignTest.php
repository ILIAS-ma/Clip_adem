<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\ClipSubmissionRefused;
use App\Livewire\CampaignCatalogue;
use App\Livewire\SubmitClip;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Clips\ClipSubmissionService;
use App\Services\Clips\ParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Une campagne terminée se consulte — résultats, stats — mais ne se modifie plus :
 * ni soumission, ni retrait de clip.
 */
class EndedCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected User $clipper;

    protected SocialAccount $account;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.tiktok.client_key', null);

        $this->clipper = User::factory()->create([
            'role' => UserRole::Clipper, 'email_verified_at' => now(), 'pseudo' => 'lina.clips',
            'country' => 'FR', 'paypal_email' => 'lina@paypal.test',
        ]);
        $this->account = SocialAccount::factory()->create(['user_id' => $this->clipper->id, 'platform' => Platform::TikTok]);

        $this->campaign = Campaign::factory()->contest()->create([
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(5),
        ]);
        app(ParticipationService::class)->join($this->campaign, $this->clipper, $this->account);
    }

    protected function end(): void
    {
        $this->campaign->forceFill(['ends_at' => now()->subHour()])->save();
        $this->campaign->refresh();
    }

    #[Test]
    public function has_ended_follows_the_end_date_and_the_completed_status(): void
    {
        $this->assertFalse($this->campaign->hasEnded());

        $this->end();
        $this->assertTrue($this->campaign->hasEnded());

        $other = Campaign::factory()->contest()->create(['status' => CampaignStatus::Completed, 'ends_at' => null]);
        $this->assertTrue($other->hasEnded());
    }

    #[Test]
    public function the_page_says_the_campaign_is_over_and_what_is_still_possible(): void
    {
        $this->end();
        Clip::factory()->create(['campaign_id' => $this->campaign->id, 'platform' => Platform::TikTok, 'status' => ClipStatus::Approved, 'views_total' => 4321]);

        $this->actingAs($this->clipper)
            ->get(route('campaigns.show', $this->campaign))
            ->assertOk()
            ->assertSee('Cette campagne est terminée')
            ->assertSee('Classement final')
            ->assertSee('Bilan : 1 clip')
            ->assertSee('il n\'est plus possible de soumettre ni de retirer des clips', escape: false)
            ->assertSee('Mes clips')
            ->assertDontSee('Soumettre mes vidéos');
    }

    #[Test]
    public function a_running_campaign_shows_no_ended_banner(): void
    {
        $this->actingAs($this->clipper)
            ->get(route('campaigns.show', $this->campaign))
            ->assertOk()
            ->assertDontSee('Cette campagne est terminée')
            ->assertSee('Soumettre mes vidéos');
    }

    #[Test]
    public function the_catalogue_greys_ended_campaigns_into_a_past_section_with_their_totals(): void
    {
        $this->end();
        Campaign::factory()->withRate(Platform::TikTok)->create(['status' => CampaignStatus::Active, 'title' => 'Campagne encore ouverte']);

        $base = ['campaign_id' => $this->campaign->id, 'platform' => Platform::TikTok, 'status' => ClipStatus::Approved];
        Clip::factory()->create(array_merge($base, ['views_total' => 1000]));
        Clip::factory()->create(array_merge($base, ['views_total' => 2000]));
        // Un clip non validé ne compte pas, comme au classement.
        Clip::factory()->create(array_merge($base, ['views_total' => 90000, 'status' => ClipStatus::Rejected]));

        $this->actingAs($this->clipper);

        // Les campagnes en cours d'abord, puis la section des campagnes passées.
        $page = Livewire::test(CampaignCatalogue::class)
            ->assertSeeInOrder(['Campagne encore ouverte', 'Campagnes passées', $this->campaign->title, 'Terminée'])
            ->assertSee('3k');

        $past = $page->viewData('past');
        $this->assertSame(2, $past->first()->clips_total);
        $this->assertSame(3000, (int) $past->first()->views_sum);

        // Hors de la liste principale : une campagne terminée n'est plus proposée comme ouverte.
        $this->assertFalse($page->viewData('campaigns')->contains('id', $this->campaign->id));
    }

    #[Test]
    public function a_past_campaign_card_shows_its_cover_image_and_its_prize_pool(): void
    {
        $this->campaign->forceFill(['cover_path' => 'campaigns/momo-rocma-visuel.jpg'])->save();
        $this->end();

        $this->actingAs($this->clipper);

        Livewire::test(CampaignCatalogue::class)
            ->assertSee('Campagnes passées')
            ->assertSee('campaigns/momo-rocma-visuel.jpg', escape: false)
            ->assertSee($this->campaign->title)
            ->assertSee('de récompenses');
    }

    #[Test]
    public function the_past_section_stays_visible_whatever_the_open_filter_says(): void
    {
        $this->end();

        $this->actingAs($this->clipper);

        Livewire::test(CampaignCatalogue::class)
            ->set('onlyOpen', true)
            ->assertSee('Campagnes passées')
            ->assertSee($this->campaign->title);

        Livewire::test(CampaignCatalogue::class)
            ->set('onlyOpen', false)
            ->assertSee('Campagnes passées');
    }

    #[Test]
    public function the_past_section_respects_the_search(): void
    {
        $this->end();
        Campaign::factory()->contest()->create(['status' => CampaignStatus::Completed, 'title' => 'Autre vieille campagne', 'ends_at' => now()->subDays(20)]);

        $this->actingAs($this->clipper);

        Livewire::test(CampaignCatalogue::class)
            ->set('search', 'vieille')
            ->assertSee('Autre vieille campagne')
            ->assertDontSee($this->campaign->title);
    }

    #[Test]
    public function nothing_can_be_submitted_once_the_campaign_has_ended(): void
    {
        $component = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $video = $component->get('groups')[0]['videos'][0];

        $this->end();

        // Même en appelant l'action à la main, le serveur refuse.
        $component->call('save', [$this->account->id.':'.$video['id']], [])
            ->assertNoRedirect()
            ->assertSet('pickError', "Cette campagne n'accepte plus de nouveaux clips.");

        $this->assertSame(0, Clip::count());
    }

    #[Test]
    public function a_clip_cannot_be_withdrawn_once_the_ranking_is_final(): void
    {
        $component = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $video = $component->get('groups')[0]['videos'][0];
        $component->call('save', [$this->account->id.':'.$video['id']], []);

        $clip = Clip::firstOrFail();
        $this->end();

        $this->expectException(ClipSubmissionRefused::class);
        $this->expectExceptionMessage('La campagne est terminée');

        app(ClipSubmissionService::class)->withdraw($clip, $this->clipper);
    }

    #[Test]
    public function the_stats_stay_reachable_after_the_end(): void
    {
        $component = Livewire::actingAs($this->clipper)
            ->test(SubmitClip::class, ['campaign' => $this->campaign])
            ->call('loadVideos');

        $video = $component->get('groups')[0]['videos'][0];
        $component->call('save', [$this->account->id.':'.$video['id']], []);
        Clip::firstOrFail()->forceFill(['views_total' => 4321])->save();

        $this->end();

        $this->actingAs($this->clipper)
            ->get(route('campaigns.show', $this->campaign))
            ->assertOk()
            ->assertSee('Mes clips')
            ->assertSee('4k vues');
    }
}
