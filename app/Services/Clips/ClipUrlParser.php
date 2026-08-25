<?php

namespace App\Services\Clips;

use App\Enums\Platform;
use App\Exceptions\ClipSubmissionRefused;
use App\Support\Clips\ClipUrl;

/**
 * Reconnaît une URL de publication et en extrait l'identifiant du post.
 *
 * Deux URLs différentes pointant vers le même post doivent produire le même
 * identifiant : c'est ce qui permet à la contrainte unique
 * (platform, external_post_id) d'empêcher réellement les doublons, y compris
 * quand deux clippeurs soumettent le même clip avec des paramètres de suivi
 * différents.
 */
class ClipUrlParser
{
    /**
     * @throws ClipSubmissionRefused
     */
    public function parse(string $url): ClipUrl
    {
        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ClipSubmissionRefused::invalidUrl();
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        // Les liens raccourcis ne portent pas l'identifiant : le résoudre
        // demanderait une requête HTTP à la soumission, donc un point de panne
        // et une latence. On demande le lien complet, que l'application
        // officielle fournit aussi.
        if (str_contains($host, 'vm.tiktok.com') || str_contains($host, 'vt.tiktok.com')) {
            throw ClipSubmissionRefused::shortLink('TikTok');
        }

        return match (true) {
            str_contains($host, 'tiktok.com') => $this->tiktok($path),
            str_contains($host, 'youtube.com'), str_contains($host, 'youtu.be') => $this->youtube($host, $path, $query),
            str_contains($host, 'instagram.com') => $this->instagram($path),
            default => throw ClipSubmissionRefused::unsupportedPlatform(),
        };
    }

    protected function tiktok(string $path): ClipUrl
    {
        // https://www.tiktok.com/@pseudo/video/7123456789012345678
        if (! preg_match('#/@([\w.\-]+)/(?:video|photo)/(\d+)#', $path, $matches)) {
            throw ClipSubmissionRefused::unrecognisedUrl('TikTok');
        }

        return new ClipUrl(
            Platform::TikTok,
            $matches[2],
            "https://www.tiktok.com/@{$matches[1]}/video/{$matches[2]}",
        );
    }

    /** @param  array<string, mixed>  $query */
    protected function youtube(string $host, string $path, array $query): ClipUrl
    {
        $id = match (true) {
            // https://youtu.be/ID
            str_contains($host, 'youtu.be') => ltrim($path, '/'),
            // https://www.youtube.com/shorts/ID
            (bool) preg_match('#/shorts/([\w\-]+)#', $path, $m) => $m[1],
            // https://www.youtube.com/watch?v=ID
            default => $query['v'] ?? null,
        };

        // Les identifiants YouTube font 11 caractères : contrôler la longueur
        // évite d'accepter un fragment de chemin comme identifiant.
        if (! $id || ! preg_match('/^[\w\-]{11}$/', $id)) {
            throw ClipSubmissionRefused::unrecognisedUrl('YouTube');
        }

        return new ClipUrl(Platform::YouTube, $id, "https://www.youtube.com/watch?v={$id}");
    }

    protected function instagram(string $path): ClipUrl
    {
        // https://www.instagram.com/reel/CODE/ ou /p/CODE/
        if (! preg_match('#/(reel|reels|p|tv)/([\w\-]+)#', $path, $matches)) {
            throw ClipSubmissionRefused::unrecognisedUrl('Instagram');
        }

        return new ClipUrl(
            Platform::Instagram,
            $matches[2],
            "https://www.instagram.com/reel/{$matches[2]}/",
        );
    }
}
