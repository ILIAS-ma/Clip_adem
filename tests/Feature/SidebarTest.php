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

    /**
     * Le contenu de la colonne seule.
     *
     * Découpage par position plutôt que par expression régulière : la balise
     * ouvrante porte un attribut Alpine qui contient une fonction fléchée,
     * donc un `>`. Tout motif du type `<aside[^>]*>` s'y arrête et ne trouve
     * jamais la colonne.
     */
    protected function colonne(string $url): string
    {
        $html = $this->get($url)->assertSuccessful()->getContent();

        $debut = strpos($html, 'app-sidebar');
        $this->assertNotFalse($debut, 'La colonne est introuvable.');

        $fin = strpos($html, '</aside>', $debut);
        $this->assertNotFalse($fin, 'La colonne n’est pas refermée.');

        return substr($html, $debut, $fin - $debut);
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
        $this->actingAs($this->clipper());

        $colonne = $this->colonne(route('dashboard'));

        $this->assertGreaterThanOrEqual(
            8,
            substr_count($colonne, '<path d="'),
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
    public function the_account_block_lives_in_the_sidebar_too(): void
    {
        /*
         * Profil, moyen de paiement et déconnexion n'existaient que dans le
         * tiroir mobile. Le tiroir est bien rendu sur toutes les tailles
         * d'écran — il est seulement masqué en CSS au-delà de `lg` — donc
         * chercher ces liens « quelque part dans la page » ne prouvait rien :
         * c'est ce que faisait le test précédent, et il passait pendant qu'un
         * utilisateur sur grand écran ne pouvait pas se déconnecter.
         *
         * D'où la recherche dans la colonne seule.
         */
        $this->actingAs($this->clipper());

        $colonne = $this->colonne(route('dashboard'));

        foreach (['profile.edit', 'payout-method.edit', 'logout'] as $route) {
            $this->assertStringContainsString(
                route($route, absolute: false),
                $colonne,
                "« {$route} » doit être atteignable sans réduire la fenêtre.",
            );
        }
    }

    #[Test]
    public function a_creator_sees_no_payout_method(): void
    {
        // Un créateur n'est jamais payé par la plateforme : il la paie.
        $creator = User::factory()->create([
            'role' => UserRole::Creator,
            'email_verified_at' => now(),
        ]);

        Creator::factory()->create(['user_id' => $creator->getKey(), 'is_active' => true]);

        $this->actingAs($creator);

        $colonne = $this->colonne(route('creator.dashboard'));

        $this->assertStringContainsString(route('profile.edit', absolute: false), $colonne);
        $this->assertStringNotContainsString(route('payout-method.edit', absolute: false), $colonne);
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

    #[Test]
    public function the_phone_page_is_reachable_from_the_profile(): void
    {
        /*
         * La page de vérification existait sans qu'aucun lien n'y mène :
         * atteignable seulement en tapant l'adresse. Un dispositif qu'on ne
         * trouve pas ne protège rien.
         */
        $this->actingAs($this->clipper())
            ->get(route('profile.edit'))
            ->assertSuccessful()
            ->assertSee(route('phone.verify', absolute: false), escape: false)
            ->assertSee('Vérifier mon numéro');
    }
}
