<?php

namespace App\Services\Moderation;

use Carbon\CarbonInterface;

/**
 * Cherche, dans l'historique d'un compte, la vidéo plus ancienne dont une autre semble être la reprise.
 *
 * Une vidéo republiée reçoit un nouvel identifiant et une nouvelle date : rien dans la
 * plateforme ne la relie à l'originale. On ne peut donc que comparer ce qui se voit — la
 * durée à la seconde près, et la légende hors hashtags et mentions. Les deux ensemble
 * font un indice, pas une preuve : c'est pourquoi le résultat est un signalement à
 * vérifier à la main, jamais un refus automatique.
 *
 * Une légende vide ou réduite à des hashtags ne dit rien (cent vidéos partagent la même
 * durée courte) : sans texte à comparer, on ne conclut pas.
 */
class ReuploadDetector
{
    /** Ressemblance minimale des légendes, en pourcentage. */
    public const MIN_SIMILARITY = 85;

    /** Longueur minimale du texte restant, hashtags et mentions retirés. */
    public const MIN_TEXT_LENGTH = 8;

    /** Au-delà de ce nombre de vidéos du compte à la légende voisine, c'est un modèle, pas une reprise. */
    public const MAX_SAME_CAPTION = 3;

    /** La légende sans hashtags, mentions ni ponctuation : ce qui a été écrit à la main. */
    public static function text(?string $caption): string
    {
        $text = mb_strtolower((string) $caption);
        $text = preg_replace('/[#@][\p{L}\p{N}_.]+/u', ' ', $text);
        $text = preg_replace('/[^\p{L}\p{N} ]+/u', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', (string) $text));
    }

    /**
     * @param  array{id: string, caption: ?string, duration: ?int, posted_at: ?CarbonInterface}  $post
     * @param  iterable<array{id: string, caption: ?string, duration: ?int, posted_at: ?CarbonInterface}>  $history
     * @return array{post: array, similarity: float}|null la plus ancienne vidéo qui ressemble à $post
     */
    public function findOriginal(array $post, iterable $history): ?array
    {
        $text = static::text($post['caption'] ?? null);

        if ($post['duration'] === null || $post['posted_at'] === null || mb_strlen($text) < self::MIN_TEXT_LENGTH) {
            return null;
        }

        $history = is_array($history) ? $history : iterator_to_array($history, false);

        // Une légende modèle, collée sur la plupart des vidéos du compte, ne désigne personne :
        // à la longue, la durée seule suffit à faire coïncider n'importe quelle paire.
        $sameText = 0;

        foreach ($history as $other) {
            if ($other['id'] === $post['id']) {
                continue;
            }

            similar_text($text, static::text($other['caption'] ?? null), $percent);

            if ($percent >= self::MIN_SIMILARITY && ++$sameText > self::MAX_SAME_CAPTION) {
                return null;
            }
        }

        $found = null;

        foreach ($history as $older) {
            if ($older['id'] === $post['id']
                || $older['posted_at'] === null
                || $older['duration'] !== $post['duration']
                || $older['posted_at']->gte($post['posted_at'])) {
                continue;
            }

            $olderText = static::text($older['caption'] ?? null);

            if (mb_strlen($olderText) < self::MIN_TEXT_LENGTH) {
                continue;
            }

            similar_text($text, $olderText, $percent);

            if ($percent >= self::MIN_SIMILARITY
                && ($found === null || $older['posted_at']->lt($found['post']['posted_at']))) {
                $found = ['post' => $older, 'similarity' => round($percent, 1)];
            }
        }

        return $found;
    }
}
