<?php

namespace Tests\Feature\Clipper;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClipperAccessTest extends TestCase
{
    use RefreshDatabase;

    /** Un clippeur pleinement opérationnel : vérifié et profil complet. */
    protected function clipper(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'clippeur'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
        ], $attributes));
    }

    #[Test]
    public function registration_creates_a_clipper_and_asks_for_verification(): void
    {
        Event::fake([Registered::class]);

        $this->post('/register', [
            'first_name' => 'Lina',
            'last_name' => 'Dupont',
            'email' => 'lina@example.test',
            'phone' => '0610000005',
            'phone_country' => 'FR',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
        ])->assertRedirect();

        $user = User::where('email', 'lina@example.test')->sole();

        // Le rôle est posé explicitement, jamais laissé au défaut de schéma.
        $this->assertSame(UserRole::Clipper, $user->role);
        $this->assertNull($user->email_verified_at);

        Event::assertDispatched(Registered::class);
    }

    #[Test]
    public function an_unverified_clipper_cannot_reach_the_campaigns(): void
    {
        $user = $this->clipper(['email_verified_at' => null]);

        $this->actingAs($user)->get('/campagnes')->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function an_incomplete_profile_is_pushed_to_the_completion_form(): void
    {
        // Découvrir qu'il manque une adresse PayPal après 200 000 vues est la
        // meilleure façon de perdre un clippeur : on bloque en amont.
        $user = $this->clipper(['paypal_email' => null]);

        $this->actingAs($user)->get('/campagnes')->assertRedirect(route('profile.complete'));
        $this->actingAs($user)->get(route('profile.complete'))->assertSuccessful();
    }

    #[Test]
    public function completing_the_profile_unlocks_the_space(): void
    {
        $user = $this->clipper(['pseudo' => null, 'country' => null, 'paypal_email' => null]);

        $this->actingAs($user)->patch(route('profile.complete.update'), [
            'pseudo' => 'lina.clips',
            'country' => 'fr',
            'payout_method' => 'paypal',
            'paypal_email' => 'lina@paypal.test',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertSame('lina.clips', $user->pseudo);
        $this->assertSame('FR', $user->country, 'Le code pays doit être normalisé en majuscules.');
        $this->assertNotNull($user->profile_completed_at);

        $this->actingAs($user)->get('/campagnes')->assertSuccessful();
    }

    #[Test]
    public function a_pseudo_is_unique_across_clippers(): void
    {
        $this->clipper(['pseudo' => 'lina.clips']);
        $other = $this->clipper(['pseudo' => null]);

        $this->actingAs($other)
            ->patch(route('profile.complete.update'), [
                'pseudo' => 'lina.clips',
                'country' => 'FR',
                'payout_method' => 'paypal',
                'paypal_email' => 'x@paypal.test',
            ])
            ->assertSessionHasErrors('pseudo');
    }

    #[Test]
    public function an_admin_is_also_a_clipper(): void
    {
        // Un administrateur garde le back-office et entre dans l'espace
        // clippeur comme n'importe qui : profil d'abord, puis tout le reste.
        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'email_verified_at' => now(),
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('profile.complete'));

        $admin->forceFill(['pseudo' => 'le.boss', 'country' => 'FR', 'paypal_email' => 'boss@paypal.test'])->save();

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Administration');
        $this->actingAs($admin)->get('/campagnes')->assertOk();

        // Et la bascule existe dans l'autre sens.
        $this->actingAs($admin)->get('/admin')->assertSee('Espace clippeur');
    }

    #[Test]
    public function a_moderator_is_also_a_clipper(): void
    {
        $moderator = User::factory()->create([
            'role' => UserRole::Moderator,
            'email_verified_at' => now(),
            'pseudo' => 'la.modo',
            'country' => 'FR',
            'paypal_email' => 'modo@paypal.test',
        ]);

        $this->actingAs($moderator)->get('/campagnes')->assertOk();
    }

    #[Test]
    public function a_creator_still_stays_out_of_the_clipper_space(): void
    {
        $creator = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($creator)->get('/dashboard')->assertRedirect('/createur');
    }

    #[Test]
    public function a_clipper_gets_no_way_into_the_admin(): void
    {
        $this->actingAs($this->clipper())->get('/dashboard')->assertDontSee('Administration');
    }

    #[Test]
    public function a_banned_clipper_is_logged_out_on_their_next_request(): void
    {
        // Le bannissement est décidé côté modération : une session déjà ouverte
        // ne doit pas survivre jusqu'à son expiration.
        $user = $this->clipper(['is_banned' => true, 'ban_reason' => 'Vues achetées']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[Test]
    public function guests_are_sent_to_the_login_page(): void
    {
        // La connexion vit à la racine : c'est là qu'atterrit un visiteur.
        $this->get('/dashboard')->assertRedirect('/');
        $this->get('/campagnes')->assertRedirect('/');

        $this->get('/')->assertSuccessful()->assertSee('Se connecter');
    }
}
