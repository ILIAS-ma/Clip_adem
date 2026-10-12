<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Social\TikTokProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Conditions d'utilisation et politique de confidentialité.
 *
 * Elles ne sont pas décoratives : TikTok et Google les lisent pour instruire
 * une demande d'application, et une page inaccessible ou muette sur les
 * données collectées fait rejeter la demande.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function both_pages_are_public(): void
    {
        // Les vérificateurs n'ont ni compte ni cookie : le moindre garde-fou
        // ici ferait échouer une revue d'application.
        $this->get(route('legal.terms'))->assertSuccessful();
        $this->get(route('legal.privacy'))->assertSuccessful();
    }

    #[Test]
    public function the_legal_notice_shows_the_publisher_and_the_host(): void
    {
        config([
            'legal.publisher.name' => 'Ilias Exemple',
            'legal.publisher.status' => 'Entrepreneur individuel',
            'legal.publisher.siret' => '123 456 789 00012',
            'legal.publisher.address' => '1 rue de l’Exemple, 75000 Paris',
            'legal.publisher.director' => 'Ilias Exemple',
        ]);

        $this->get(route('legal.notice'))
            ->assertSuccessful()
            ->assertSee('Ilias Exemple')
            ->assertSee('123 456 789 00012')
            ->assertSee('OVH SAS')
            ->assertSee('2 rue Kellermann');
    }

    #[Test]
    public function an_unfilled_publisher_is_never_invented(): void
    {
        // Pas d'identité de remplissage : la page le dit, et donne un contact.
        config(['legal.publisher.name' => null]);

        $this->get(route('legal.notice'))
            ->assertSuccessful()
            ->assertSee('en cours de mise à jour')
            ->assertDontSee('SIRET');
    }

    #[Test]
    public function every_public_entry_point_links_to_the_legal_notice(): void
    {
        foreach (['home', 'login', 'register'] as $route) {
            $this->get(route($route))->assertSee(route('legal.notice', absolute: false), escape: false);
        }
    }

    #[Test]
    public function they_stay_reachable_once_signed_in(): void
    {
        // Ni redirection vers un espace, ni garde d'onboarding : un utilisateur
        // connecté doit pouvoir relire ce qu'il a accepté.
        $clipper = User::factory()->create(['role' => UserRole::Clipper]);

        $this->actingAs($clipper)->get(route('legal.terms'))->assertSuccessful();
        $this->actingAs($clipper)->get(route('legal.privacy'))->assertSuccessful();
    }

    #[Test]
    public function the_privacy_policy_names_every_tiktok_scope_we_request(): void
    {
        /*
         * L'exigence explicite de TikTok : la politique doit dire quelles
         * données sont lues et pour quoi faire. Une politique générique fait
         * rejeter la demande.
         *
         * Le test lit la liste depuis le provider plutôt que de la recopier :
         * ajouter une portée sans documenter son usage doit casser ici.
         */
        $response = $this->get(route('legal.privacy'))->assertSuccessful();

        foreach (app(TikTokProvider::class)->requestedScopes() as $scope) {
            $response->assertSee($scope);
        }
    }

    #[Test]
    public function the_privacy_policy_says_what_we_never_read(): void
    {
        // Dire ce qu'on ne prend pas rassure autant que dire ce qu'on prend, et
        // c'est ce qu'un examinateur cherche.
        $this->get(route('legal.privacy'))
            ->assertSee('messages privés')
            ->assertSee('IBAN est chiffré', false);
    }

    #[Test]
    public function the_terms_warn_that_the_budget_runs_out(): void
    {
        // C'est la règle qui surprend le plus un clippeur, et la première
        // source de litige : elle doit être écrite noir sur blanc.
        $this->get(route('legal.terms'))
            ->assertSee('premier arrivé')
            ->assertSee('Acheter des vues');
    }

    #[Test]
    public function each_page_links_to_the_other(): void
    {
        // Un examinateur arrive sur l'une des deux et doit trouver l'autre.
        $this->get(route('legal.terms'))->assertSee(route('legal.privacy'), false);
        $this->get(route('legal.privacy'))->assertSee(route('legal.terms'), false);
    }
}
