<?php

namespace App\Services\Clips;

use App\Enums\Platform;
use App\Exceptions\ClipSubmissionRefused;
use App\Support\Clips\ClipUrl;
use Illuminate\Support\Facades\Http;

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

        /*
         * Les liens raccourcis — ceux que l'application TikTok copie sur
         * téléphone, vm.tiktok.com/… ou tiktok.com/t/… — ne portent pas
         * l'identifiant : on suit la redirection jusqu'à l'adresse complète.
         * Les refuser revenait à refuser presque tous les liens collés depuis
         * un téléphone, c'est-à-dire presque tous les liens.
         */
        if (static::isTikTokShortLink($url)) {
            $url = $this->resolveShortLink($url) ?? throw ClipSubmissionRefused::shortLink('TikTok');
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return match (true) {
            str_contains($host, 'tiktok.com') => $this->tiktok($path),
            str_contains($host, 'youtube.com'), str_contains($host, 'youtu.be') => $this->youtube($host, $path, $query),
            str_contains($host, 'instagram.com') => $this->instagram($path),
            default => throw ClipSubmissionRefused::unsupportedPlatform(),
        };
    }

    protected static function isTikTokShortLink(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return str_contains($host, 'vm.tiktok.com')
            || str_contains($host, 'vt.tiktok.com')
            || (str_contains($host, 'tiktok.com') && str_starts_with((string) parse_url($url, PHP_URL_PATH), '/t/'));
    }

    /**
     * Suit les redirections d'un lien court, une à une, jusqu'à une adresse
     * qui porte l'identifiant de la vidéo. Null si TikTok ne répond pas.
     */
    protected function resolveShortLink(string $url): ?string
    {
        for ($hop = 0; $hop < 5; $hop++) {
            try {
                $response = Http::withoutRedirecting()
                    ->timeout(8)
                    ->withUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148')
                    ->get($url);
            } catch (\Throwable) {
                return null;
            }

            $location = $response->header('Location');

            if (! $response->redirect() || ! $location) {
                return null;
            }

            // Une redirection relative reste sur le même hôte.
            $url = str_starts_with($location, '/')
                ? parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).$location
                : $location;

            if (preg_match('#/(?:video|photo)/\d+#', (string) parse_url($url, PHP_URL_PATH))) {
                return $url;
            }
        }

        return null;
    }

    protected function tiktok(string $path): ClipUrl
    {
        // https://m.tiktok.com/v/7123456789012345678.html : sans pseudo, le
        // contrôle de propriété se fera au premier relevé.
        if (preg_match('#^/v/(\d+)#', $path, $matches)) {
            return new ClipUrl(Platform::TikTok, $matches[1], "https://www.tiktok.com/@user/video/{$matches[1]}");
        }

        // https://www.tiktok.com/@pseudo/video/7123456789012345678
        if (! preg_match('#/@([\w.\-]+)/(?:video|photo)/(\d+)#', $path, $matches)) {
            throw ClipSubmissionRefused::unrecognisedUrl('TikTok');
        }

        return new ClipUrl(
            Platform::TikTok,
            $matches[2],
            "https://www.tiktok.com/@{$matches[1]}/video/{$matches[2]}",
            handle: $matches[1],
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
