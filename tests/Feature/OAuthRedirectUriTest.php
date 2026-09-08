<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les adresses de retour OAuth.
 *
 * Elles étaient figées une par une dans le `.env`. Derrière un tunnel de
 * développement, l'adresse du site change à chaque redémarrage : il fallait
 * alors corriger quatre variables à la main, et celle qu'on oubliait produisait
 * un « redirect_uri » refusé — chez un seul fournisseur, souvent des jours plus
 * tard. C'est exactement ce qui est arrivé à Google, resté sur `127.0.0.1`
 * pendant que TikTok pointait sur le tunnel.
 *
 * Une seule valeur commande désormais l'ensemble : APP_URL.
 */
class OAuthRedirectUriTest extends TestCase
{
    /** Les chemins de retour, tels que les consoles des fournisseurs les connaissent. */
    public static function providers(): array
    {
        return [
            'google' => ['google', '/auth/google/callback'],
            'tiktok' => ['tiktok', '/oauth/tiktok/callback'],
            'youtube' => ['youtube', '/oauth/youtube/callback'],
            'instagram' => ['instagram', '/oauth/instagram/callback'],
        ];
    }

    #[Test]
    #[DataProvider('providers')]
    public function every_redirect_uri_follows_the_site_address(string $service, string $path): void
    {
        $this->assertSame(
            rtrim(env('APP_URL'), '/').$path,
            config("services.$service.redirect"),
            "L'adresse de retour de $service ne suit pas APP_URL.",
        );
    }

    #[Test]
    public function no_redirect_uri_is_left_empty(): void
    {
        // Une valeur vide se propage silencieusement : le fournisseur reçoit
        // `redirect_uri=` et refuse, sans que rien n'ait signalé l'oubli.
        foreach (array_keys(static::providers()) as $service) {
            $this->assertNotEmpty(
                config("services.$service.redirect"),
                "L'adresse de retour de $service est vide.",
            );
        }
    }

    #[Test]
    public function an_explicit_variable_still_wins(): void
    {
        /*
         * La dérivation est une commodité, pas une contrainte : un domaine de
         * production peut différer de l'APP_URL interne, et un fournisseur peut
         * imposer une adresse particulière. On doit pouvoir la figer sans
         * toucher au reste.
         */
        $previous = $_SERVER['TIKTOK_REDIRECT_URI'] ?? null;
        $_SERVER['TIKTOK_REDIRECT_URI'] = 'https://impose.example/retour';

        try {
            $services = require config_path('services.php');

            $this->assertSame('https://impose.example/retour', $services['tiktok']['redirect']);
        } finally {
            if ($previous === null) {
                unset($_SERVER['TIKTOK_REDIRECT_URI']);
            } else {
                $_SERVER['TIKTOK_REDIRECT_URI'] = $previous;
            }
        }
    }

    #[Test]
    public function a_trailing_slash_on_the_site_address_does_not_double_up(): void
    {
        /*
         * `https://site.test//oauth/tiktok/callback` est une autre chaîne que
         * celle enregistrée dans la console, et la comparaison se fait
         * caractère par caractère. La faute de frappe est trop facile pour ne
         * pas être absorbée ici.
         */
        $previousUrl = $_SERVER['APP_URL'] ?? null;
        $previousUri = $_SERVER['TIKTOK_REDIRECT_URI'] ?? null;

        $_SERVER['APP_URL'] = 'https://site.test/';
        $_SERVER['TIKTOK_REDIRECT_URI'] = '';

        try {
            $services = require config_path('services.php');

            $this->assertSame('https://site.test/oauth/tiktok/callback', $services['tiktok']['redirect']);
        } finally {
            $this->restore('APP_URL', $previousUrl);
            $this->restore('TIKTOK_REDIRECT_URI', $previousUri);
        }
    }

    protected function restore(string $key, ?string $previous): void
    {
        if ($previous === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $previous;
        }
    }
}
