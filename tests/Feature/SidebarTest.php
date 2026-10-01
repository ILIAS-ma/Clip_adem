<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Creator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La colonne de navigation.
 *
 * Elle remplace le menu au bouton là où la place existe, pour une raison
 * précise : la rubrique courante reste visible en permanence. Avec un menu
 * fermé, savoir où l'on se trouve demande de l'ouvrir — ce qu'un menu devrait
 * justement éviter.
 *
 * Ces tests portent sur ce qui doit rester vrai quelle que soit l'apparence :
 * aucune rubrique perdue, la position signalée, et une seule liste pour la
 * colonne et le tiroir mobile.
 */
class SidebarTest extends TestCase
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
    public function every_section_appears_in_the_sidebar(): void
    {
        $html = $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->getContent();

        $this->assertStringContainsString('app-sidebar', $html);

        foreach ([
            'dashboard', 'campaigns.index', 'clips.index', 'accounts.index',
            'earnings.index', 'referrals.index', 'leaderboard.index', 'achievements.index',
        ] as $route) {
            $this->assertStringContainsString(route($route, absolute: false), $html);
        }
    }

    #[Test]
    public function the_current_section_is_marked(): void
    {
        /*
         * `aria-current` n'est pas une finition : c'est ce qui permet à un
         * lecteur d'écran d'annoncer la page courante. Le fond lime ne dit rien
         * à qui ne voit pas l'écran.
         */
        $this->actingAs($this->clipper())
            ->get(route('clips.index'))
            ->assertSuccessful()
            ->assertSee('aria-current="page"', escape: false)
            ->assertSee('is-active', escape: false);
    }

    #[Test]
    public function every_entry_carries_an_icon(): void
    {
        // Colonne repliée, l'icône est tout ce qui reste. Une rubrique sans
        // icône y deviendrait un carré vide.
        $html = $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->getContent();

        preg_match('#<aside[^>]*app-sidebar.*?</aside>#s', $html, $colonne);

        $this->assertNotEmpty($colonne, 'La colonne est introuvable.');
        $this->assertGreaterThanOrEqual(
            8,
            substr_count($colonne[0], '<path d="'),
            'Chaque rubrique doit porter son icône.',
        );
    }

    #[Test]
    public function the_mobile_drawer_shows_the_same_list(): void
    {
        /*
         * La colonne et le tiroir lisent la même source. Deux listes
         * finiraient par diverger, et c'est sur mobile que l'oubli se verrait
         * en dernier — personne ne teste le téléphone en premier.
         */
        $html = $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->getContent();

        foreach (['clips.index', 'referrals.index', 'achievements.index'] as $route) {
            $this->assertGreaterThanOrEqual(
                2,
                substr_count($html, route($route, absolute: false)),
                "« {$route} » doit figurer dans la colonne et dans le tiroir.",
            );
        }
    }

    #[Test]
    public function a_creator_never_sees_clipper_sections(): void
    {
        $creator = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        Creator::factory()->create(['user_id' => $creator->getKey(), 'is_active' => true]);

        $this->actingAs($creator)
            ->get(route('creator.dashboard'))
            ->assertSuccessful()
            ->assertDontSee(route('clips.index', absolute: false), escape: false)
            ->assertDontSee(route('earnings.index', absolute: false), escape: false);
    }

    #[Test]
    public function logging_out_is_still_reachable(): void
    {
        // Elle vivait dans le menu déroulant, puis dans le tiroir. À chaque
        // remaniement, c'est l'entrée qu'on oublie — et s'enfermer dans sa
        // session est le genre de panne qu'on ne découvre qu'en voulant partir.
        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee(route('logout', absolute: false), escape: false);
    }
}
