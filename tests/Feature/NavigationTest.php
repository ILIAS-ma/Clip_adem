<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Creator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La navigation, désormais derrière un bouton à toutes les tailles.
 *
 * Les huit rubriques s'étalaient en barre dès le grand écran. Une navigation
 * complète affichée en permanence se relit à chaque page, et grandit avec le
 * produit : chaque rubrique ajoutée serrait les autres.
 *
 * Ce qui compte n'est pas la forme du bouton mais ce qui reste atteignable :
 * aucun lien ne doit disparaître au passage, et le solde doit rester lisible
 * sans ouvrir quoi que ce soit.
 */
class NavigationTest extends TestCase
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
    public function every_clipper_section_is_still_reachable(): void
    {
        $response = $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful();

        foreach ([
            'dashboard', 'campaigns.index', 'clips.index', 'accounts.index',
            'earnings.index', 'referrals.index', 'leaderboard.index', 'achievements.index',
        ] as $route) {
            $response->assertSee(route($route, absolute: false), escape: false);
        }
    }

    #[Test]
    public function the_account_menu_survives_the_move(): void
    {
        // Ces entrées vivaient dans un menu déroulant séparé, supprimé avec la
        // barre : les perdre enfermerait quelqu'un dans sa session.
        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee(route('profile.edit', absolute: false), escape: false)
            ->assertSee(route('payout-method.edit', absolute: false), escape: false)
            ->assertSee(route('logout', absolute: false), escape: false);
    }

    #[Test]
    public function the_balance_stays_visible_without_opening_anything(): void
    {
        /*
         * C'est l'information qu'un clippeur vient chercher. L'enfouir sous un
         * bouton lui ferait ouvrir le menu à chaque visite — exactement ce que
         * le menu devait éviter.
         */
        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('nav-burger', escape: false)
            ->assertSee('0,00 €');
    }

    #[Test]
    public function a_creator_never_sees_clipper_sections(): void
    {
        // La barre s'adapte au rôle : des liens morts vaudraient moins que rien.
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
}
