<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\GoogleAuthProvider;
use App\Support\Auth\GoogleProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Connexion Google.
 *
 * Le point sensible tient en une règle : **on ne rattache jamais un compte
 * existant sur une adresse que Google n'a pas vérifiée**. Sans elle, il
 * suffirait de créer un compte Google déclarant l'adresse d'un administrateur
 * pour prendre sa place. Trois tests l'entourent.
 */
class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'un-client-id',
            'services.google.client_secret' => 'un-secret',
        ]);
    }

    /** Simule le retour de Google avec un profil donné. */
    protected function returningFromGoogle(GoogleProfile $profile)
    {
        $this->mock(GoogleAuthProvider::class, function ($mock) use ($profile) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('redirectUrl')->andReturn('https://accounts.google.com/o/oauth2/v2/auth');
            $mock->shouldReceive('profileFrom')->andReturn($profile);
        });

        return $this->withSession(['google.state' => 'un-etat'])
            ->get(route('google.callback', ['state' => 'un-etat', 'code' => 'un-code']));
    }

    protected function profile(array $overrides = []): GoogleProfile
    {
        return new GoogleProfile(
            googleId: $overrides['googleId'] ?? 'google-123',
            email: $overrides['email'] ?? 'maya@gmail.test',
            emailVerified: $overrides['emailVerified'] ?? true,
            name: $overrides['name'] ?? 'Maya Bernard',
            avatarUrl: 'https://lh3.googleusercontent.com/photo',
        );
    }

    #[Test]
    public function a_new_visitor_gets_an_account_and_is_logged_in(): void
    {
        $this->returningFromGoogle($this->profile())->assertRedirect('/dashboard');

        $user = User::where('email', 'maya@gmail.test')->sole();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame(UserRole::Clipper, $user->role);
        // Google a déjà vérifié l'adresse : la revérifier n'apporterait qu'un
        // e-mail de plus.
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password, 'Un compte né de Google n’a pas de mot de passe.');
    }

    #[Test]
    public function coming_back_reuses_the_same_account(): void
    {
        $existing = User::factory()->create([
            'role' => UserRole::Clipper,
            'email' => 'maya@gmail.test',
        ]);
        $existing->forceFill(['google_id' => 'google-123'])->save();

        $this->returningFromGoogle($this->profile());

        $this->assertAuthenticatedAs($existing->fresh());
        $this->assertSame(1, User::count(), 'Aucun doublon ne doit être créé.');
    }

    #[Test]
    public function a_verified_address_links_an_existing_password_account(): void
    {
        $existing = User::factory()->create([
            'role' => UserRole::Clipper,
            'email' => 'maya@gmail.test',
        ]);

        $this->returningFromGoogle($this->profile());

        $this->assertSame('google-123', $existing->fresh()->google_id);
        $this->assertAuthenticatedAs($existing->fresh());
    }

    #[Test]
    public function an_unverified_address_never_takes_over_an_existing_account(): void
    {
        // Le scénario à empêcher : créer un compte Google déclarant l'adresse
        // d'un administrateur, et prendre sa place.
        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'email' => 'admin@clip.test',
        ]);

        $this->returningFromGoogle($this->profile([
            'email' => 'admin@clip.test',
            'emailVerified' => false,
        ]))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNull($admin->fresh()->google_id);
    }

    #[Test]
    public function an_unverified_address_creates_nothing_either(): void
    {
        $this->returningFromGoogle($this->profile(['emailVerified' => false]));

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    #[Test]
    public function a_banned_account_cannot_come_in_through_google(): void
    {
        $banned = User::factory()->create([
            'role' => UserRole::Clipper,
            'email' => 'maya@gmail.test',
            'is_banned' => true,
        ]);
        $banned->forceFill(['google_id' => 'google-123'])->save();

        $this->returningFromGoogle($this->profile())->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[Test]
    public function a_forged_state_is_refused(): void
    {
        // Sans ce contrôle, un tiers pourrait faire rattacher son propre compte
        // Google à la session de la victime.
        $this->mock(GoogleAuthProvider::class, function ($mock) {
            $mock->shouldReceive('profileFrom')->never();
        });

        $this->withSession(['google.state' => 'le-vrai-etat'])
            ->get(route('google.callback', ['state' => 'un-etat-forge', 'code' => 'un-code']))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[Test]
    public function the_chosen_role_survives_the_round_trip(): void
    {
        // Le rôle est choisi avant de partir chez Google et ne revient pas dans
        // le retour d'appel : sans la session, tout le monde reviendrait
        // clippeur.
        $this->mock(GoogleAuthProvider::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('redirectUrl')->andReturn('https://accounts.google.com/');
            $mock->shouldReceive('profileFrom')->andReturn($this->profile());
        });

        $this->get(route('google.redirect', ['profil' => UserRole::Creator->value]));

        $this->withSession([
            'google.state' => 'un-etat',
            'google.role' => UserRole::Creator->value,
        ])->get(route('google.callback', ['state' => 'un-etat', 'code' => 'un-code']));

        $this->assertSame(UserRole::Creator, User::where('email', 'maya@gmail.test')->sole()->role);
    }

    #[Test]
    public function the_button_stays_hidden_while_google_is_not_configured(): void
    {
        // Un bouton qui mène à une erreur est pire que pas de bouton.
        config(['services.google.client_id' => null]);

        $this->get(route('login'))->assertSuccessful()->assertDontSee('Se connecter avec Google');
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
