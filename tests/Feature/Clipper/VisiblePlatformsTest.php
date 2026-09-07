<?php

namespace Tests\Feature\Clipper;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ce que la page « Mes comptes » propose vraiment.
 *
 * Une plateforme sans identifiants tourne sur le fournisseur simulé. La
 * proposer avec une pastille « démonstration » dit la vérité, mais donne d'une
 * plateforme qui verse de l'argent réel l'image d'un prototype — et une option
 * qui ne mène à rien vaut moins que pas d'option du tout.
 */
class VisiblePlatformsTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'clip'.fake()->unique()->numberBetween(1, 99999),
            'country' => 'FR',
            'paypal_email' => fake()->unique()->safeEmail(),
            'profile_completed_at' => now(),
        ]);
    }

    #[Test]
    public function an_unconfigured_platform_is_not_offered(): void
    {
        // Aucune clé dans l'environnement de test : rien ne doit être proposé.
        config(['clipping.show_simulated_platforms' => false]);

        $this->actingAs($this->clipper())
            ->get(route('accounts.index'))
            ->assertSuccessful()
            ->assertDontSee('Démonstration')
            ->assertDontSee('Mode démonstration');
    }

    #[Test]
    public function a_configured_platform_is_offered_as_official(): void
    {
        config([
            'clipping.show_simulated_platforms' => false,
            'services.tiktok.client_key' => 'une-cle',
            'services.tiktok.client_secret' => 'un-secret',
        ]);

        $this->actingAs($this->clipper())
            ->get(route('accounts.index'))
            ->assertSuccessful()
            ->assertSee('TikTok')
            ->assertSee('Connexion officielle');
    }

    #[Test]
    public function the_simulated_ones_come_back_when_asked_for(): void
    {
        // Le parcours complet doit rester éprouvable sans la moindre clé.
        config(['clipping.show_simulated_platforms' => true]);

        $this->actingAs($this->clipper())
            ->get(route('accounts.index'))
            ->assertSuccessful()
            ->assertSee('Démonstration');
    }

    #[Test]
    public function the_suspended_controls_banner_is_hidden_from_clippers(): void
    {
        /*
         * L'avertissement s'adresse à qui peut agir dessus : un clippeur n'a
         * pas de `.env`, et lui montrer une consigne de développement fait
         * passer la plateforme pour inachevée.
         */
        config(['clipping.onboarding.require_admin_2fa' => false]);

        $this->actingAs($this->clipper())
            ->get(route('accounts.index'))
            ->assertSuccessful()
            ->assertDontSee('Contrôles suspendus');
    }

    #[Test]
    public function the_banner_still_warns_the_staff(): void
    {
        // Le garde-fou reste : sans lui, un contrôle désactivé « le temps de
        // voir l'interface » finit en production.
        config(['clipping.onboarding.require_admin_2fa' => false]);

        $admin = User::factory()->create([
            'role' => UserRole::SuperAdmin,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('profile.edit'))
            ->assertSuccessful()
            ->assertSee('Contrôles suspendus');
    }
}
