<?php

namespace Tests\Feature\Clipper;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ce que le tableau de bord met en avant.
 *
 * La page empilait sept blocs de poids égal. Pour répondre à « combien
 * puis-je retirer ? » — la raison pour laquelle un clippeur ouvre le site —
 * il fallait lire quatre étiquettes et trouver la bonne.
 *
 * Ces tests portent sur la hiérarchie, pas sur l'esthétique : qu'un chiffre
 * domine, et que la marche restante soit dite plutôt que laissée à calculer.
 */
class DashboardLayoutTest extends TestCase
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
    public function the_balance_is_the_headline_figure(): void
    {
        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Solde retirable')
            ->assertSee('hero-balance', escape: false);
    }

    #[Test]
    public function an_empty_balance_says_how_much_is_still_missing(): void
    {
        /*
         * « Minimum 20 € » obligeait à faire la soustraction soi-même. Un
         * clippeur qui ne sait pas où il en est conclut souvent que le site
         * ne compte rien — c'est la question qui revient le plus.
         */
        $minimum = (int) config('clipping.payouts.minimum_cents');

        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Encore')
            ->assertSee(Money::euros($minimum));
    }

    #[Test]
    public function the_supporting_figures_are_still_there(): void
    {
        // La hiérarchie ne doit rien supprimer : ces chiffres expliquent le
        // solde, les retirer rendrait le principal inexplicable.
        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('Vues cumulées')
            ->assertSee('Gains validés')
            ->assertSee('Comptes liés');
    }

    #[Test]
    public function the_withdraw_link_points_at_the_earnings_page(): void
    {
        // Le bouton n'apparaît qu'au-dessus du minimum ; la cible, elle, doit
        // exister dans tous les cas — une route renommée casserait la page.
        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful();

        $this->assertSame('/revenus', route('earnings.index', absolute: false));
    }
}
