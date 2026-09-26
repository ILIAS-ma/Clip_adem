<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La navigation instantanée, et ce qu'il ne faut surtout pas lui confier.
 *
 * `wire:navigate` remplace le document sans rechargement : plus de clignotement
 * blanc, et le balayage de progression peut s'afficher. Appliqué largement,
 * c'est ce qui fait la différence entre un site qui saute et un site qui glisse.
 *
 * Mais deux liens ne doivent jamais le recevoir, et les deux casseraient en
 * silence : la déconnexion, qui passe par un formulaire, et le départ OAuth,
 * qui quitte le site pour TikTok — Livewire tenterait de récupérer la page de
 * consentement comme un fragment de notre application.
 */
class InstantNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(): User
    {
        return User::factory()->create([
            'role' => UserRole::Clipper,
            'email_verified_at' => now(),
            'pseudo' => 'maya.clips',
            'country' => 'FR',
            'paypal_email' => 'maya@paypal.test',
            'profile_completed_at' => now(),
        ]);
    }

    #[Test]
    public function the_main_sections_load_without_a_full_reload(): void
    {
        $html = $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->getContent();

        // Les rubriques du menu : ce sont les liens les plus empruntés.
        $this->assertGreaterThanOrEqual(
            8,
            substr_count($html, 'wire:navigate'),
            'La navigation du menu doit être instantanée.',
        );
    }

    #[Test]
    public function the_logout_link_is_left_alone(): void
    {
        /*
         * Il porte un `onclick` qui soumet un formulaire. Intercepter ce clic
         * empêcherait la déconnexion — et on ne s'en apercevrait qu'en
         * essayant de se déconnecter.
         */
        $html = $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->getContent();

        preg_match('#<a[^>]*'.preg_quote(route('logout', absolute: false), '#').'[^>]*>#', $html, $lien);

        $this->assertNotEmpty($lien, 'Le lien de déconnexion est introuvable.');
        $this->assertStringNotContainsString('wire:navigate', $lien[0]);
    }

    #[Test]
    public function the_oauth_departure_is_left_alone(): void
    {
        // Il quitte le site. Livewire chercherait à en faire un fragment de
        // page, et l'écran de consentement TikTok ne s'afficherait jamais.
        $html = $this->actingAs($this->clipper())
            ->get(route('accounts.index'))
            ->assertSuccessful()
            ->getContent();

        preg_match_all('#<a[^>]*/mes-comptes/[a-z]+/connexion[^>]*>#', $html, $liens);

        foreach ($liens[0] as $lien) {
            $this->assertStringNotContainsString(
                'wire:navigate',
                $lien,
                'Le départ OAuth ne doit pas passer par la navigation instantanée.',
            );
        }
    }

    #[Test]
    public function the_reveal_animation_is_rearmed_after_navigation(): void
    {
        /*
         * Sans cela, les éléments [data-reveal] du nouveau document restent
         * invisibles : l'observateur ne surveillait que ceux du document
         * remplacé. Une page entièrement vide, à cause de l'animation censée
         * la rendre vivante.
         */
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertMatchesRegularExpression(
            '#livewire:navigated.*armerAnimations#s',
            $script,
            'Les animations doivent être réarmées après une navigation.',
        );
    }
}
