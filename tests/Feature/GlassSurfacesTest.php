<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Où le verre dépoli a le droit d'aller.
 *
 * Sur iOS, le verre habille ce qui flotte au-dessus du contenu, et jamais le
 * contenu lui-même. Ce n'est pas un choix esthétique :
 *
 *  - `backdrop-filter` fait recomposer la zone floutée à chaque image. Sur une
 *    liste de vingt cartes, le défilement saccade — d'abord sur les téléphones,
 *    c'est-à-dire chez les clippeurs.
 *  - Un texte posé sur du translucide perd du contraste dès que ce qui défile
 *    dessous change de luminosité. Sur une carte de montant, c'est le chiffre
 *    qui devient illisible.
 *
 * Ces tests gardent la frontière : le mobilier en verre, ce qu'on lit en opaque.
 */
class GlassSurfacesTest extends TestCase
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
    public function the_navigation_bar_is_frosted(): void
    {
        $this->actingAs($this->clipper())
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertSee('glass glass-edge sticky', escape: false);
    }

    #[Test]
    public function content_cards_stay_opaque(): void
    {
        /*
         * La régression qu'on veut empêcher : appliquer `.glass` à `.card`
         * parce que « c'est plus joli ». Le tableau de bord en aligne une
         * dizaine ; le jour où une liste en compte cinquante, le défilement
         * devient hachré sur mobile, et personne ne fait le lien avec ce
         * changement-là.
         */
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertDoesNotMatchRegularExpression(
            '/\.card\s*\{[^}]*backdrop-filter/s',
            $css,
            'Les cartes de contenu doivent rester opaques.',
        );
    }

    #[Test]
    public function there_is_a_fallback_without_backdrop_filter(): void
    {
        // Firefox l'a longtemps désactivé par défaut. Sans repli, le fond
        // reste franchement translucide et le texte se lit par-dessus la page.
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('@supports not', $css);
        $this->assertMatchesRegularExpression('/@supports not.*backdrop-filter/s', $css);
    }

    #[Test]
    public function saturation_comes_before_the_blur(): void
    {
        /*
         * Sans `saturate`, le flou délave les couleurs qui passent dessous et
         * le verre vire au gris : on obtient un voile, pas un dépoli. C'est le
         * détail qui sépare les deux, et il se perd au premier « nettoyage »
         * de la déclaration.
         */
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/backdrop-filter:\s*saturate\([^)]+\)\s+blur\(/',
            $css,
        );
    }
}
