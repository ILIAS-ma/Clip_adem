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
            'name' => 'Lina Dupont',
            'email' => 'lina@example.test',
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
                'paypal_email' => 'x@paypal.test',
            ])
            ->assertSessionHasErrors('pseudo');
    }

    #[Test]
    public function staff_are_sent_to_their_panel_instead_of_the_clipper_space(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->get('/dashboard')->assertRedirect('/admin');
        $this->actingAs($admin)->get('/campagnes')->assertRedirect('/admin');
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
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/campagnes')->assertRedirect(route('login'));
    }
}
