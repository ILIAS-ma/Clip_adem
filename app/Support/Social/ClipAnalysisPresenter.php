<?php

namespace App\Support\Social;

use App\Models\Clip;

/**
 * Met le relevé d'un clip dans le format JSON attendu par l'interface de
 * soumission — le même contrat qu'un « agent d'analyse » externe aurait pu
 * produire, mais rempli avec ce que le circuit interne sait réellement :
 * l'API officielle du compte lié, jamais un scraping de l'URL soumise.
 *
 * Les likes, commentaires et partages ne sont pas persistés sur le clip
 * (seuls views_total et les champs de conformité le sont) : ils ne sont donc
 * exacts que juste après un relevé frais. En dehors de ça, à 0 — ce que le
 * contrat demande explicitement pour une donnée chiffrée introuvable.
 */
class ClipAnalysisPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function success(Clip $clip, ?PostMetrics $metrics = null): array
    {
        return [
            'statut' => 'success',
            'url_originale' => $clip->url,
            'auteur' => self::handle($clip),
            'titre_description' => $clip->caption ?? '',
            'hashtags' => self::hashtags($clip->caption),
            'statistiques' => [
                'vues' => $clip->views_total,
                'likes' => $metrics?->likes ?? 0,
                'commentaires' => $metrics?->comments ?? 0,
                'partages' => $metrics?->shares ?? 0,
            ],
            'duree_secondes' => $clip->duration_seconds ?? 0,
            'date_publication' => $clip->posted_at?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    /**
     * @return array{statut: string, message: string}
     */
    public static function error(string $message): array
    {
        return [
            'statut' => 'error',
            'message' => $message,
        ];
    }

    private static function handle(Clip $clip): string
    {
        $handle = $clip->socialAccount?->handle;

        if (blank($handle)) {
            return '';
        }

        return str_starts_with($handle, '@') ? $handle : '@'.$handle;
    }

    /**
     * Les hashtags apparaissent dans le texte de la légende plutôt que dans
     * un champ séparé sur la plupart des plateformes : on les en extrait
     * directement, ce qui fonctionne pour TikTok, YouTube et Instagram sans
     * dépendre d'un champ d'API spécifique à chacune.
     *
     * @return array<int, string>
     */
    private static function hashtags(?string $caption): array
    {
        if (blank($caption)) {
            return [];
        }

        preg_match_all('/#([\p{L}0-9_]+)/u', $caption, $matches);

        return array_values(array_unique($matches[1]));
    }
}
