<?php

namespace Tests\Feature\Social;

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Services\Social\InstagramProvider;
use App\Services\Social\TikTokProvider;
use App\Services\Social\YouTubeProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'adresse de retour OAuth.
 *
 * Deux propriétés cassent en silence et coûtent chacune une heure de
 * recherche, parce que le fournisseur ne répond qu'un « invalid redirect_uri »
 * sans dire ce qu'il attendait :
 *
 *  - elle doit être identique à l'autorisation et à l'échange du code ;
 *  - elle doit correspondre caractère par caractère à celle enregistrée dans
 *    la console développeur, ce que la déduction depuis l'hôte de la requête
 *    ne garantit pas derrière un tunnel.
 */
class RedirectUriTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{0: string, 1: class-string, 2: Platform}> */
    public static function providers(): array
    {
        return [
            'tiktok' => ['tiktok', TikTokProvider::class, Platform::TikTok],
            'youtube' => ['youtube', YouTubeProvider::class, Platform::YouTube],
            'instagram' => ['instagram', InstagramProvider::class, Platform::Instagram],
        ];
    }

    #[Test]
    public function without_configuration_the_uri_falls_back_to_the_route(): void
    {
        // Le développement simulé doit continuer de marcher sans rien régler.
        foreach (static::providers() as [$key, $class, $platform]) {
            config(["services.{$key}.redirect" => null]);

            $this->assertStringContainsString(
                urlencode(route('social.callback', ['platform' => $platform->value])),
                app($class)->redirectUrl('un-etat'),
                "Repli sur la route absent pour {$key}.",
            );
        }
    }

    #[Test]
    public function a_configured_uri_wins_over_the_route(): void
    {
        // Le cas du tunnel : l'hôte de la requête n'est pas celui enregistré
        // chez le fournisseur.
        foreach (static::providers() as [$key, $class, $platform]) {
            $fixed = "https://clip.example.test/oauth/{$platform->value}/callback";
            config(["services.{$key}.redirect" => $fixed]);

            $url = app($class)->redirectUrl('un-etat');

            $this->assertStringContainsString(urlencode($fixed), $url);
            $this->assertStringNotContainsString('localhost', $url);
        }
    }

    #[Test]
    public function the_token_exchange_sends_the_same_uri_as_the_authorization(): void
    {
        // OAuth compare les deux : une divergence fait échouer l'échange après
        // que l'utilisateur a pourtant accepté, ce qui est le pire moment.
        $fixed = 'https://clip.example.test/oauth/tiktok/callback';
        config(['services.tiktok.redirect' => $fixed]);
        config(['services.tiktok.client_key' => 'cle', 'services.tiktok.client_secret' => 'secret']);

        Http::fake(['*' => Http::response(['error' => 'peu importe'], 400)]);

        try {
            app(TikTokProvider::class)->connect('un-code');
        } catch (\Throwable) {
            // Seule la requête sortante nous intéresse.
        }

        Http::assertSent(fn ($request) => ($request['redirect_uri'] ?? null) === $fixed);

        $this->assertStringContainsString(urlencode($fixed), app(TikTokProvider::class)->redirectUrl('etat'));
    }

    #[Test]
    public function the_refresh_call_never_needs_a_redirect_uri(): void
    {
        // Le rafraîchissement se fait sans l'utilisateur : y envoyer une
        // adresse de retour n'aurait aucun sens et masquerait une confusion
        // entre les deux appels.
        config(['services.tiktok.client_key' => 'cle', 'services.tiktok.client_secret' => 'secret']);

        Http::fake(['*' => Http::response(['error' => 'peu importe'], 400)]);

        $account = SocialAccount::factory()->create([
            'platform' => Platform::TikTok,
            'refresh_token' => 'un-jeton',
        ]);

        try {
            app(TikTokProvider::class)->refresh($account);
        } catch (\Throwable) {
            // Idem.
        }

        Http::assertSent(fn ($request) => ! isset($request['redirect_uri']));
    }
}
