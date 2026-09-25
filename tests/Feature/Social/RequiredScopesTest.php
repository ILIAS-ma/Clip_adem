<?php

namespace Tests\Feature\Social;

use App\Services\Social\TikTokProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ce qu'on exige d'un compte lié ne peut pas dépasser ce qu'on lui a demandé.
 *
 * `requestedScopes()` est pilotée par TIKTOK_SCOPES ; `requiredScopes()`
 * exigeait `video.list` en dur. Le jour où la console n'avait pas encore
 * approuvé cette portée et qu'on l'a retirée de la demande, l'écran de
 * consentement a cessé de la proposer — et la liaison était malgré tout refusée
 * pour son absence.
 *
 * Le message invitait à « relancer la connexion en laissant toutes les cases
 * activées ». Il n'y avait aucune case à activer : le clippeur pouvait
 * recommencer indéfiniment.
 */
class RequiredScopesTest extends TestCase
{
    protected function provider(): TikTokProvider
    {
        return app(TikTokProvider::class);
    }

    #[Test]
    public function nothing_is_required_that_was_never_asked_for(): void
    {
        config(['services.tiktok.scopes' => 'user.info.basic']);

        $this->assertSame([], $this->provider()->requiredScopes());
    }

    #[Test]
    public function the_guard_still_holds_when_the_scope_is_asked_for(): void
    {
        /*
         * L'assouplissement ne doit pas vider le contrôle de son sens : quand
         * on demande `video.list` et que le clippeur la décoche, la liaison
         * doit être refusée. Sans vues, il ne serait jamais payé, et rien dans
         * l'interface ne le lui dirait.
         */
        config(['services.tiktok.scopes' => 'user.info.basic,video.list']);

        $this->assertSame(['video.list'], $this->provider()->requiredScopes());
    }

    #[Test]
    public function the_default_configuration_asks_for_both(): void
    {
        // Le défaut reste le fonctionnement complet : une installation qui ne
        // règle rien doit relever les vues, pas s'arrêter à l'identité.
        config(['services.tiktok.scopes' => null]);

        $this->assertSame(
            ['user.info.basic', 'video.list'],
            $this->provider()->requestedScopes(),
        );
    }

    #[Test]
    public function requirements_never_exceed_the_request(): void
    {
        // La propriété générale, quelle que soit la configuration.
        foreach (['user.info.basic', 'video.list', 'user.info.basic,video.list'] as $configured) {
            config(['services.tiktok.scopes' => $configured]);

            $provider = $this->provider();

            $this->assertEmpty(
                array_diff($provider->requiredScopes(), $provider->requestedScopes()),
                "Configuration « {$configured} » : on exige une portée non demandée.",
            );
        }
    }
}
