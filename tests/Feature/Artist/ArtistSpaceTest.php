<?php

namespace Tests\Feature\Artist;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Artist;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArtistSpaceTest extends TestCase
{
    use RefreshDatabase;

    protected function artistUser(array $artistAttributes = []): User
    {
        $user = User::factory()->create([
            'role' => UserRole::Artist,
            'email_verified_at' => now(),
        ]);

        Artist::factory()->create(array_merge([
            'user_id' => $user->getKey(),
            'is_active' => true,
        ], $artistAttributes));

        return $user->fresh();
    }

    protected function clipperUser(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'clip'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
        ]);
    }

    /** Une campagne créditée, pour que les statistiques aient de la matière. */
    protected function campaignWithClip(Artist $artist, int $views = 200_000): Campaign
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'artist_id' => $artist->getKey(),
                'status' => CampaignStatus::Active,
                'budget_total_cents' => 100_000,
            ]);

        $clip = Clip::factory()->create([
            'campaign_id' => $campaign->getKey(),
            'user_id' => $this->clipperUser()->getKey(),
            'platform' => Platform::TikTok,
            'status' => ClipStatus::Approved,
            'views_total' => $views,
        ]);

        app(CampaignBudgetService::class)->creditViews($clip, $views, "clip:{$clip->id}:snapshot:1");

        return $campaign->fresh();
    }

    // ------------------------------------------------------------------
    // Inscription et aiguillage
    // ------------------------------------------------------------------

    #[Test]
    public function registration_can_create_an_artist_account(): void
    {
        $this->post('/register', [
            'name' => 'Nayra Diallo',
            'email' => 'nayra@exemple.test',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'role' => 'artist',
        ])->assertRedirect('/artiste');

        $this->assertSame(UserRole::Artist, User::where('email', 'nayra@exemple.test')->sole()->role);
    }

    #[Test]
    public function public_registration_cannot_grant_a_back_office_role(): void
    {
        // Le rôle vient d'un formulaire : sans liste blanche, il suffirait de
        // trafiquer la requête pour s'ouvrir le back-office et les paiements.
        $this->post('/register', [
            'name' => 'Pirate',
            'email' => 'pirate@exemple.test',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'role' => 'super_admin',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'pirate@exemple.test']);
    }

    #[Test]
    public function each_role_lands_in_its_own_space(): void
    {
        $this->actingAs($this->artistUser())->get('/')->assertRedirect('/artiste');
        $this->actingAs($this->clipperUser())->get('/')->assertRedirect('/dashboard');
    }

    #[Test]
    public function an_artist_who_opens_the_clipper_space_is_sent_home(): void
    {
        // Une redirection plutôt qu'un 403 : c'est une erreur d'adresse, pas
        // une intrusion.
        $this->actingAs($this->artistUser())->get('/dashboard')->assertRedirect('/artiste');
        $this->actingAs($this->artistUser())->get('/campagnes')->assertRedirect('/artiste');
    }

    #[Test]
    public function a_clipper_cannot_open_the_artist_space(): void
    {
        $this->actingAs($this->clipperUser())->get('/artiste')->assertRedirect('/dashboard');
    }

    #[Test]
    public function an_artist_is_not_staff_and_stays_out_of_the_panel(): void
    {
        // Le rôle est venu s'ajouter après coup : sans liste blanche explicite,
        // « tout sauf clippeur » lui aurait ouvert le back-office.
        $artist = $this->artistUser();

        $this->assertFalse($artist->isStaff());
        $this->actingAs($artist)->get('/admin')->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Fiche artiste
    // ------------------------------------------------------------------

    #[Test]
    public function an_artist_without_a_profile_is_pushed_to_create_one(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Artist,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get('/artiste')->assertRedirect(route('artist.profile.create'));
    }

    #[Test]
    public function creating_the_profile_leaves_it_pending_validation(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Artist,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post(route('artist.profile.store'), [
            'name' => 'NAYRA',
            'bio' => 'Rap/afro.',
        ])->assertRedirect(route('artist.dashboard'));

        $artist = $user->fresh()->artist;
        $this->assertSame('NAYRA', $artist->name);
        $this->assertSame('nayra', $artist->slug);
        // Sans validation d'un administrateur, n'importe qui apparaîtrait au
        // catalogue sous le nom qu'il veut.
        $this->assertFalse($artist->is_active);
    }

    #[Test]
    public function two_artists_cannot_share_a_stage_name(): void
    {
        $this->artistUser(['name' => 'NAYRA']);

        $newcomer = User::factory()->create([
            'role' => UserRole::Artist,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($newcomer)
            ->post(route('artist.profile.store'), ['name' => 'NAYRA'])
            ->assertSessionHasErrors('name');
    }

    #[Test]
    public function an_artist_can_update_their_own_profile(): void
    {
        $user = $this->artistUser(['name' => 'Ancien nom']);

        $this->actingAs($user)->patch(route('artist.profile.update'), [
            'name' => 'Nouveau nom',
            'tiktok_handle' => 'nayra.officiel',
        ])->assertRedirect(route('artist.profile.edit'));

        $this->assertSame('Nouveau nom', $user->fresh()->artist->name);
    }

    // ------------------------------------------------------------------
    // Statistiques
    // ------------------------------------------------------------------

    #[Test]
    public function the_dashboard_shows_the_real_cost_per_thousand_views(): void
    {
        $user = $this->artistUser();
        $this->campaignWithClip($user->artist, views: 200_000); // 200 € pour 200 000 vues

        $this->actingAs($user)
            ->get('/artiste')
            ->assertSuccessful()
            ->assertSee('Coût réel / 1000 vues')
            // 20 000 centimes pour 200 000 vues = 100 centimes / 1000 vues.
            ->assertSee('1,00 €');
    }

    #[Test]
    public function the_campaign_page_lists_the_clips_and_their_cost(): void
    {
        $user = $this->artistUser();
        $campaign = $this->campaignWithClip($user->artist);

        $this->actingAs($user)
            ->get(route('artist.campaigns.show', $campaign))
            ->assertSuccessful()
            ->assertSee($campaign->title)
            ->assertSee('Les clips');
    }

    #[Test]
    public function an_artist_never_sees_another_artists_campaign(): void
    {
        $mine = $this->artistUser();
        $other = $this->artistUser();
        $foreign = $this->campaignWithClip($other->artist);

        $this->actingAs($mine)
            ->get(route('artist.campaigns.show', $foreign))
            ->assertNotFound();
    }

    #[Test]
    public function an_artist_never_sees_a_clippers_contact_details(): void
    {
        // Un artiste suit des résultats, il n'a pas à disposer des coordonnées
        // ni de l'identité civile des clippeurs qui travaillent pour lui.
        $user = $this->artistUser();
        $campaign = $this->campaignWithClip($user->artist);
        $clipper = $campaign->clips()->sole()->user;

        $response = $this->actingAs($user)->get(route('artist.campaigns.show', $campaign));

        $response->assertSee($clipper->pseudo);
        $response->assertDontSee($clipper->email);
        $response->assertDontSee($clipper->paypal_email);
    }

    #[Test]
    public function an_artist_cannot_reach_the_payouts_or_the_ledger(): void
    {
        $user = $this->artistUser();

        $this->actingAs($user)->get('/revenus')->assertRedirect('/artiste');
        $this->actingAs($user)->get('/mes-comptes')->assertRedirect('/artiste');
    }
}
