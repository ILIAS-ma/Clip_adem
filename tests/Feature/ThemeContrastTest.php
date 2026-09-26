<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'échelle sombre, et la distance entre ses marches.
 *
 * Le fond de page valait `#080908` — pratiquement du noir pur. Un aplat aussi
 * sombre paraît toujours plus dur qu'il ne l'est : rien n'y accroche le
 * regard, et les cartes, à neuf points au-dessus, s'en détachaient à peine.
 *
 * Ces tests ne jugent pas les couleurs choisies : ils vérifient qu'on n'y
 * revienne pas par mégarde, et que chaque marche reste distinguable de la
 * suivante.
 */
class ThemeContrastTest extends TestCase
{
    /** Luminance perçue d'un `#rrggbb`, sur 255. */
    protected function luminance(string $hex): float
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%2x%2x%2x');

        // Pondération ITU-R BT.601 : l'œil est bien plus sensible au vert
        // qu'au bleu, une moyenne simple dirait n'importe quoi.
        return 0.299 * $r + 0.587 * $g + 0.114 * $b;
    }

    /** Les valeurs déclarées dans la configuration Tailwind. */
    protected function ink(): array
    {
        $source = file_get_contents(base_path('tailwind.config.js'));

        preg_match('/ink:\s*\{(.+?)\}/s', $source, $bloc);
        preg_match_all("/(\d+):\s*'(#[0-9A-Fa-f]{6})'/", $bloc[1] ?? '', $paires, PREG_SET_ORDER);

        return collect($paires)->mapWithKeys(fn ($p) => [(int) $p[1] => $p[2]])->all();
    }

    #[Test]
    public function the_page_ground_is_not_pure_black(): void
    {
        /*
         * Le noir absolu n'existe pas dans une pièce éclairée : l'œil le lit
         * comme un trou, pas comme une surface. C'est ce qui faisait trouver
         * le site « beaucoup trop sombre » alors que les textes, eux, avaient
         * un contraste suffisant.
         */
        $fond = $this->ink()[950] ?? null;

        $this->assertNotNull($fond, 'La nuance ink-950 est introuvable.');
        $this->assertGreaterThan(
            12,
            $this->luminance($fond),
            'Le fond de page est trop proche du noir pur.',
        );
    }

    #[Test]
    public function a_card_stands_out_from_the_page(): void
    {
        // Sans cet écart, les cartes se devinent au lieu de se voir, et la
        // page paraît être un seul bloc.
        $ink = $this->ink();

        $ecart = $this->luminance($ink[800]) - $this->luminance($ink[950]);

        $this->assertGreaterThan(
            10,
            $ecart,
            'Les cartes ne se détachent pas assez du fond de page.',
        );
    }

    #[Test]
    public function the_scale_never_goes_backwards(): void
    {
        // Une marche plus claire que la précédente casserait toute la
        // hiérarchie des surfaces, sans qu'aucune page ne le signale.
        $ink = $this->ink();
        krsort($ink);

        $precedente = null;

        foreach ($ink as $niveau => $hex) {
            $actuelle = $this->luminance($hex);

            if ($precedente !== null) {
                $this->assertGreaterThan(
                    $precedente,
                    $actuelle,
                    "La nuance ink-{$niveau} n'est pas plus claire que la précédente.",
                );
            }

            $precedente = $actuelle;
        }
    }

    #[Test]
    public function body_text_keeps_a_readable_contrast(): void
    {
        /*
         * Relever le fond rapproche mécaniquement le texte secondaire du sien.
         * `ink-300` sert partout aux libellés : s'il passe sous ce seuil, la
         * page devient jolie et illisible.
         */
        $ink = $this->ink();

        $ecart = $this->luminance($ink[300]) - $this->luminance($ink[950]);

        $this->assertGreaterThan(120, $ecart, 'Le texte secondaire manque de contraste.');
    }
}
