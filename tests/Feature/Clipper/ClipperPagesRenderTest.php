<?php

namespace Tests\Feature\Clipper;

use App\Enums\CampaignStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Livewire\CampaignCatalogue;
use App\Livewire\JoinCampaign;
use App\Models\Campaign;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test de fumée de l'espace clippeur.
 *
 * Les vues sont du Blade : sans ce test, une erreur de template ne se verrait
 * qu'en ouvrant la page à la main.
 */
class ClipperPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'lina.clips',
            'country' => 'FR',
            'paypal_email' => 'lina@paypal.test',
        ]);
    }

    protected function campaign(array $attributes = []): Campaign
    {
        return Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 75)
            ->create(array_merge([
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 120_000,
                'requires_approval' => false,
                'brief' => 'Utiliser le refrain, mentionner @créateur.',
                'required_hashtags' => ['#nayra', '#clip'],
                'max_payout_per_clip_cents' => 20_000,
            ], $attributes));
    }

    #[Test]
    public function the_dashboard_and_the_catalogue_render(): void
    {
        $this->campaign();
        $clipper = $this->clipper();

        $this->actingAs($clipper)->get('/dashboard')->assertSuccessful();
        $this->actingAs($clipper)->get('/campagnes')->assertSuccessful();
        $this->actingAs($clipper)->get(route('earnings.index'))->assertSuccessful();
    }

    #[Test]
    public function the_payout_method_page_renders_both_choices(): void
    {
        $this->actingAs($this->clipper())
            ->get(route('payout-method.edit'))
            ->assertSuccessful()
            ->assertSee('Moyen de paiement')
            ->assertSee('PayPal')
            ->assertSee('Virement bancaire');
    }

    #[Test]
    public function a_clipper_without_a_destination_never_reaches_the_earnings_page(): void
    {
        // Le garde-fou attrape le manque bien avant la page Revenus : découvrir
        // qu'on ne peut pas être payé après 200 000 vues fait perdre un clippeur.
        $clipper = $this->clipper();
        $clipper->forceFill(['paypal_email' => null])->save();

        $this->assertNull($clipper->payoutDestination());
        $this->assertContains('payout', $clipper->missingProfileFields());

        $this->actingAs($clipper)
            ->get(route('earnings.index'))
            ->assertRedirect(route('profile.complete'));
    }

    #[Test]
    public function the_campaign_page_renders_for_a_brand_new_clipper(): void
    {
        $campaign = $this->campaign();

        $this->actingAs($this->clipper())
            ->get(route('campaigns.show', $campaign))
            ->assertSuccessful()
            ->assertSee($campaign->title)
            // Les plafonds sont annoncés d'emblée, pas découverts après coup.
            ->assertSee('Plafond par clip');
    }

    #[Test]
    public function an_exhausted_campaign_stays_visible_but_announces_itself(): void
    {
        $campaign = $this->campaign();
        $campaign->forceFill([
            'spent_cents' => 120_000,
            'status' => CampaignStatus::Exhausted,
        ])->save();

        $this->actingAs($this->clipper())
            ->get(route('campaigns.show', $campaign))
            ->assertSuccessful()
            ->assertSee('Budget épuisé');
    }

    #[Test]
    public function a_draft_campaign_is_not_reachable(): void
    {
        $campaign = $this->campaign(['status' => CampaignStatus::Draft]);

        $this->actingAs($this->clipper())
            ->get(route('campaigns.show', $campaign))
            ->assertNotFound();
    }

    #[Test]
    public function the_catalogue_filters_by_platform(): void
    {
        $tiktok = $this->campaign(['title' => 'Campagne TikTok']);

        $youtube = Campaign::factory()
            ->withRate(Platform::YouTube, ratePer1kCents: 200)
            ->create(['status' => CampaignStatus::Active, 'title' => 'Campagne YouTube']);

        Livewire::actingAs($this->clipper())
            ->test(CampaignCatalogue::class)
            ->assertSee('Campagne TikTok')
            ->assertSee('Campagne YouTube')
            ->set('platform', Platform::YouTube->value)
            ->assertSee('Campagne YouTube')
            ->assertDontSee('Campagne TikTok');
    }

    #[Test]
    public function the_catalogue_filters_by_minimum_rate(): void
    {
        $this->campaign(['title' => 'Petit cachet']); // 0,75 € / 1000

        Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 300)
            ->create(['status' => CampaignStatus::Active, 'title' => 'Gros cachet']);

        Livewire::actingAs($this->clipper())
            ->test(CampaignCatalogue::class)
            ->set('minRate', '2')
            ->assertSee('Gros cachet')
            ->assertDontSee('Petit cachet');
    }

    #[Test]
    public function finished_campaigns_move_to_the_past_section_instead_of_the_main_list(): void
    {
        $this->campaign(['title' => 'Campagne ouverte']);
        $this->campaign(['title' => 'Campagne terminée', 'status' => CampaignStatus::Completed]);

        $page = Livewire::actingAs($this->clipper())
            ->test(CampaignCatalogue::class)
            ->assertSee('Campagne ouverte')
            ->assertSee('Campagnes passées');

        // Jamais mêlée aux campagnes ouvertes, mais toujours consultable dans sa propre section.
        $this->assertFalse($page->viewData('campaigns')->contains('title', 'Campagne terminée'));
        $this->assertTrue($page->viewData('past')->contains('title', 'Campagne terminée'));

        // Le filtre « uniquement les campagnes ouvertes » ne la fait pas disparaître.
        $page->set('onlyOpen', false)->assertSee('Campagne terminée');
    }

    #[Test]
    public function joining_from_the_page_creates_the_participation(): void
    {
        $campaign = $this->campaign();
        $clipper = $this->clipper();
        $account = SocialAccount::factory()->create([
            'user_id' => $clipper->getKey(),
            'platform' => Platform::TikTok,
        ]);

        Livewire::actingAs($clipper)
            ->test(JoinCampaign::class, ['campaign' => $campaign])
            ->set('socialAccountId', $account->getKey())
            ->call('join')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('campaign_participations', [
            'campaign_id' => $campaign->getKey(),
            'social_account_id' => $account->getKey(),
        ]);
    }
}
