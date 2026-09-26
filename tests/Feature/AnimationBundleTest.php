<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ce que les animations ont le droit de coûter.
 *
 * GSAP et ScrollTrigger pèsent une quarantaine de kilo-octets compressés,
 * contre moins de deux pour tout le reste du script. Inclus d'office, ils
 * seraient téléchargés avant le premier affichage — sur des téléphones et des
 * réseaux qui n'ont rien demandé, et sans servir du tout à quelqu'un qui
 * préfère moins de mouvement.
 *
 * Ces tests gardent la structure : le script d'entrée reste léger, la
 * bibliothèque part dans un fragment séparé, et le mouvement réduit court-
 * circuite avant même de la demander.
 */
class AnimationBundleTest extends TestCase
{
    protected function app_js(): string
    {
        return file_get_contents(resource_path('js/app.js'));
    }

    #[Test]
    public function gsap_is_a_declared_dependency(): void
    {
        $package = json_decode(file_get_contents(base_path('package.json')), true);

        $this->assertArrayHasKey('gsap', $package['dependencies'] ?? []);
    }

    #[Test]
    public function the_library_is_not_pulled_into_the_entry_script(): void
    {
        /*
         * La régression qu'on veut empêcher : un `import gsap from 'gsap'` en
         * tête d'app.js, ajouté sans y penser. Le fragment séparé disparaît,
         * et quarante kilo-octets reviennent bloquer le premier affichage —
         * sans que rien ne le signale, puisque le site marche toujours.
         */
        $source = $this->app_js();

        $this->assertDoesNotMatchRegularExpression(
            "/^\s*import\s+.*from\s+'gsap/m",
            $source,
            'GSAP doit rester dans un fragment chargé à la demande.',
        );

        $this->assertStringContainsString(
            "await import('./animations')",
            $source,
            'Le module d’animations doit être importé dynamiquement.',
        );
    }

    #[Test]
    public function reduced_motion_short_circuits_before_the_download(): void
    {
        // Ne pas animer ne suffit pas : il ne faut pas non plus télécharger
        // de quoi animer. Le test vérifie l'ordre, pas seulement la présence.
        $source = $this->app_js();

        preg_match('/async function armerAnimations\(\).*?\}\n\}/s', $source, $fonction);

        $this->assertNotEmpty($fonction, 'La fonction d’armement est introuvable.');

        $garde = strpos($fonction[0], 'prefers-reduced-motion');
        $chargement = strpos($fonction[0], "import('./animations')");

        $this->assertNotFalse($garde, 'Le mouvement réduit n’est pas pris en compte.');
        $this->assertLessThan(
            $chargement,
            $garde,
            'Le garde doit précéder le chargement, sinon la bibliothèque est téléchargée pour rien.',
        );
    }

    #[Test]
    public function the_css_no_longer_animates_what_gsap_owns(): void
    {
        /*
         * L'entrée de page et la cascade des cartes sont passées à GSAP. Les
         * laisser aussi en CSS ferait jouer deux systèmes sur le même élément,
         * avec un saut visible au raccord.
         */
        $css = file_get_contents(resource_path('css/app.css'));

        foreach (['.js main', '.js .stat-card', '.js .hero-balance'] as $selecteur) {
            $this->assertDoesNotMatchRegularExpression(
                '/'.preg_quote($selecteur, '/').'\s*\{[^}]*animation:/s',
                $css,
                "« {$selecteur} » est animé à la fois en CSS et par GSAP.",
            );
        }
    }
}
