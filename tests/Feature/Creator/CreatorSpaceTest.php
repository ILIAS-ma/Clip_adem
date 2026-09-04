<?php

namespace Tests\Feature\Creator;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Creator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreatorSpaceTest extends TestCase
{
    use RefreshDatabase;

    protected function creatorUser(array $creatorAttributes = []): User
    {
        $user = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        Creator::factory()->create(array_merge([
            'user_id' => $user->getKey(),
            'is_active' => true,
        ], $creatorAttributes));

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
    protected function campaignWithClip(Creator $creator, int $views = 200_000): Campaign
    {
        $campaign = Campaign::factory()
            ->withRate(Platform::TikTok, ratePer1kCents: 100)
            ->create([
                'creator_id' => $creator->getKey(),
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
    public function registration_can_create_a_creator_account(): void
    {
        $this->post('/register', [
            'name' => 'Nayra Diallo',
            'email' => 'nayra@exemple.test',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'role' => 'creator',
        ])->assertRedirect('/createur');

        $this->assertSame(UserRole::Creator, User::where('email', 'nayra@exemple.test')->sole()->role);
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
        $this->actingAs($this->creatorUser())->get('/')->assertRedirect('/createur');
        $this->actingAs($this->clipperUser())->get('/')->assertRedirect('/dashboard');
    }

    #[Test]
    public function a_creator_who_opens_the_clipper_space_is_sent_home(): void
    {
        // Une redirection plutôt qu'un 403 : c'est une erreur d'adresse, pas
        // une intrusion.
        $this->actingAs($this->creatorUser())->get('/dashboard')->assertRedirect('/createur');
        $this->actingAs($this->creatorUser())->get('/campagnes')->assertRedirect('/createur');
    }

    #[Test]
    public function a_clipper_cannot_open_the_creator_space(): void
    {
        $this->actingAs($this->clipperUser())->get('/createur')->assertRedirect('/dashboard');
    }

    #[Test]
    public function a_creator_is_not_staff_and_stays_out_of_the_panel(): void
    {
        // Le rôle est venu s'ajouter après coup : sans liste blanche explicite,
        // « tout sauf clippeur » lui aurait ouvert le back-office.
        $creator = $this->creatorUser();

        $this->assertFalse($creator->isStaff());
        $this->actingAs($creator)->get('/admin')->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Fiche créateur
    // ------------------------------------------------------------------

    #[Test]
    public function a_creator_without_a_profile_is_pushed_to_create_one(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get('/createur')->assertRedirect(route('creator.profile.create'));
    }

    #[Test]
    public function creating_the_profile_leaves_it_pending_validation(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post(route('creator.profile.store'), [
            'name' => 'NAYRA',
            'bio' => 'Rap/afro.',
        ])->assertRedirect(route('creator.dashboard'));

        $creator = $user->fresh()->creator;
        $this->assertSame('NAYRA', $creator->name);
        $this->assertSame('nayra', $creator->slug);
        // Sans validation d'un administrateur, n'importe qui apparaîtrait au
        // catalogue sous le nom qu'il veut.
        $this->assertFalse($creator->is_active);
    }

    #[Test]
    public function two_creators_cannot_share_a_stage_name(): void
    {
        $this->creatorUser(['name' => 'NAYRA']);

        $newcomer = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($newcomer)
            ->post(route('creator.profile.store'), ['name' => 'NAYRA'])
            ->assertSessionHasErrors('name');
    }

    #[Test]
    public function a_creator_can_update_their_own_profile(): void
    {
        $user = $this->creatorUser(['name' => 'Ancien nom']);

        $this->actingAs($user)->patch(route('creator.profile.update'), [
            'name' => 'Nouveau nom',
            'tiktok_handle' => 'nayra.officiel',
        ])->assertRedirect(route('creator.profile.edit'));

        $this->assertSame('Nouveau nom', $user->fresh()->creator->name);
    }

    // ------------------------------------------------------------------
    // Statistiques
    // ------------------------------------------------------------------

    #[Test]
    public function the_dashboard_shows_the_real_cost_per_thousand_views(): void
    {
        $user = $this->creatorUser();
        $this->campaignWithClip($user->creator, views: 200_000); // 200 € pour 200 000 vues

        $this->actingAs($user)
            ->get('/createur')
            ->assertSuccessful()
            ->assertSee('Coût pour 1000 vues')
            // 20 000 centimes pour 200 000 vues = 100 centimes / 1000 vues.
            ->assertSee('1,00 €');
    }

    #[Test]
    public function the_campaign_page_lists_the_clips_and_their_cost(): void
    {
        $user = $this->creatorUser();
        $campaign = $this->campaignWithClip($user->creator);

        $this->actingAs($user)
            ->get(route('creator.campaigns.show', $campaign))
            ->assertSuccessful()
            ->assertSee($campaign->title)
            ->assertSee('Les clips');
    }

    #[Test]
    public function a_creator_never_sees_another_creators_campaign(): void
    {
        $mine = $this->creatorUser();
        $other = $this->creatorUser();
        $foreign = $this->campaignWithClip($other->creator);

        $this->actingAs($mine)
            ->get(route('creator.campaigns.show', $foreign))
            ->assertNotFound();
    }

    #[Test]
    public function a_creator_never_sees_a_clippers_contact_details(): void
    {
        // Un créateur suit des résultats, il n'a pas à disposer des coordonnées
        // ni de l'identité civile des clippeurs qui travaillent pour lui.
        $user = $this->creatorUser();
        $campaign = $this->campaignWithClip($user->creator);
        $clipper = $campaign->clips()->sole()->user;

        $response = $this->actingAs($user)->get(route('creator.campaigns.show', $campaign));

        $response->assertSee($clipper->pseudo);
        $response->assertDontSee($clipper->email);
        $response->assertDontSee($clipper->paypal_email);
    }

    #[Test]
    public function a_creator_cannot_reach_the_payouts_or_the_ledger(): void
    {
        $user = $this->creatorUser();

        $this->actingAs($user)->get('/revenus')->assertRedirect('/createur');
        $this->actingAs($user)->get('/mes-comptes')->assertRedirect('/createur');
    }
}
