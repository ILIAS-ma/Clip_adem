<?php

namespace Tests\Feature\Social;

use App\Exceptions\SocialProviderFailed;
use App\Services\Social\TikTokProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lecture du profil TikTok au retour OAuth.
 *
 * `follower_count` relève de `user.info.stats`, distincte de `user.info.basic`.
 * Demander le champ sans la portée fait échouer TOUT l'appel — et donc la
 * liaison du compte — alors que le nombre d'abonnés n'est qu'un signal de
 * fraude secondaire.
 */
class TikTokProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tiktok.client_key' => 'cle',
            'services.tiktok.client_secret' => 'secret',
        ]);
    }

    #[Test]
    public function the_default_scopes_match_what_a_sandbox_actually_grants(): void
    {
        /*
         * Un Sandbox TikTok n'active que `user.info.basic` et `video.list`.
         * Demander une portée que l'application n'a pas fait échouer l'écran
         * de consentement avant même que l'utilisateur ne voie quoi que ce
         * soit — un échec bien plus coûteux que de se passer du nombre
         * d'abonnés.
         */
        config(['services.tiktok.scopes' => null]);

        $this->assertSame(
            ['user.info.basic', 'video.list'],
            app(TikTokProvider::class)->requestedScopes(),
        );
    }

    #[Test]
    public function the_scopes_follow_the_configuration(): void
    {
        // La liste doit suivre la console TikTok sans toucher au code : c'est
        // une valeur qui vit chez eux, pas chez nous.
        config(['services.tiktok.scopes' => 'user.info.basic, user.info.stats , video.list']);

        $this->assertSame(
            ['user.info.basic', 'user.info.stats', 'video.list'],
            app(TikTokProvider::class)->requestedScopes(),
        );
    }

    #[Test]
    public function a_refused_stats_scope_still_links_the_account(): void
    {
        // Perdre un indicateur de modération vaut mieux qu'empêcher quelqu'un
        // de lier son compte.
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'access_token' => 'jeton', 'refresh_token' => 'refresh',
                'open_id' => 'abc', 'expires_in' => 86400,
                'scope' => 'user.info.basic,video.list',
            ]),
            'open.tiktokapis.com/v2/user/info/*' => Http::sequence()
                ->push(['error' => ['code' => 'scope_not_authorized']], 401)
                ->push(['data' => ['user' => ['open_id' => 'abc', 'display_name' => 'maya']]], 200),
        ]);

        $compte = app(TikTokProvider::class)->connect('un-code');

        $this->assertSame('abc', $compte->externalAccountId);
        $this->assertSame('maya', $compte->handle);
        // Sans la portée, on n'a simplement pas le nombre d'abonnés.
        $this->assertNull($compte->followersCount);
    }

    #[Test]
    public function a_granted_stats_scope_brings_the_follower_count(): void
    {
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'access_token' => 'jeton', 'open_id' => 'abc', 'expires_in' => 86400,
                'scope' => 'user.info.basic,user.info.stats,video.list',
            ]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response([
                'data' => ['user' => ['open_id' => 'abc', 'display_name' => 'maya', 'follower_count' => 68000]],
            ]),
        ]);

        $this->assertSame(68000, app(TikTokProvider::class)->connect('un-code')->followersCount);
    }

    #[Test]
    public function a_real_failure_still_surfaces(): void
    {
        // Le repli ne doit pas masquer une vraie panne : sans ça, un jeton
        // invalide passerait pour un compte lié sans nom.
        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'access_token' => 'jeton', 'open_id' => 'abc', 'expires_in' => 86400,
            ]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->expectException(SocialProviderFailed::class);

        app(TikTokProvider::class)->connect('un-code');
    }
}
