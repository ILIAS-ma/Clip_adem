<?php

namespace Tests\Feature\Campaigns;

use App\Enums\CampaignStatus;
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

        $this->actingAs($this->clipper)
            ->get(route('campaigns.show', $this->campaign))
            ->assertOk()
            ->assertSee('Cette campagne est terminée')
            ->assertSee('Classement final')
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
    public function the_catalogue_labels_an_ended_campaign_as_finished(): void
    {
        $this->end();

        $this->actingAs($this->clipper)
            ->get(route('campaigns.index'))
            ->assertOk()
            ->assertSee('Terminée');
    }

    #[Test]
    public function an_ended_campaign_stays_listed_after_the_open_ones_so_results_can_be_found(): void
    {
        $this->end();
        $open = Campaign::factory()->withRate(Platform::TikTok)->create(['status' => CampaignStatus::Active, 'title' => 'Campagne encore ouverte']);

        $this->actingAs($this->clipper);

        // Les campagnes en cours d'abord ; la terminée suit, étiquetée, avec ses résultats.
        Livewire::test(CampaignCatalogue::class)
            ->assertSeeInOrder(['Campagne encore ouverte', $this->campaign->title, 'Terminée'])
            ->assertSee('résultats à consulter');
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
