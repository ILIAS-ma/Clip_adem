<?php

namespace Tests\Feature\Social;

use App\Contracts\SocialProvider;
use App\Enums\Platform;
use App\Enums\UserRole;
use App\Exceptions\SocialProviderFailed;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\FakeSocialProvider;
use App\Services\Social\InstagramProvider;
use App\Services\Social\SocialAccountLinker;
use App\Services\Social\TikTokProvider;
use App\Services\Social\YouTubeProvider;
use App\Support\Social\ConnectedAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Permissions réellement accordées au consentement.
 *
 * Les plateformes laissent l'utilisateur refuser une permission tout en
 * accordant les autres. Sans ce contrôle, un compte amputé de l'accès aux
 * statistiques se lie normalement, apparaît dans la liste, permet de rejoindre
 * une campagne et de publier — puis ne remonte jamais une vue. Le clippeur
 * découvre au bout d'une semaine qu'il ne sera pas payé.
 */
class GrantedScopesTest extends TestCase
{
    use RefreshDatabase;

    protected function clipper(): User
    {
        return User::factory()->create(['role' => UserRole::Clipper]);
    }

    /** @param  array<int, string>  $scopes */
    protected function connected(array $scopes, Platform $platform = Platform::TikTok): ConnectedAccount
    {
        return new ConnectedAccount(
            platform: $platform,
            externalAccountId: 'compte-'.uniqid(),
            handle: 'maya.clips',
            accessToken: 'un-jeton',
            refreshToken: 'un-jeton-de-rafraichissement',
            expiresAt: now()->addDays(60),
            scopes: $scopes,
            followersCount: 12_000,
        );
    }

    #[Test]
    public function a_full_consent_links_the_account(): void
    {
        $account = app(SocialAccountLinker::class)->link(
            $this->clipper(),
            $this->connected(['user.info.basic', 'video.list']),
        );

        $this->assertTrue($account->is_active);
        $this->assertContains('video.list', $account->scopes);
    }

    #[Test]
    public function refusing_the_metrics_permission_refuses_the_link(): void
    {
        $this->expectException(SocialProviderFailed::class);
        $this->expectExceptionMessage('video.list');

        app(SocialAccountLinker::class)->link(
            $this->clipper(),
            $this->connected(['user.info.basic']),
        );
    }

    #[Test]
    public function the_refused_account_is_not_left_half_created(): void
    {
        // Un compte enregistré puis inutilisable serait pire que pas de compte
        // du tout : il masquerait le problème derrière une ligne d'apparence
        // normale dans la liste.
        try {
            app(SocialAccountLinker::class)->link(
                $this->clipper(),
                $this->connected(['user.info.basic']),
            );
        } catch (SocialProviderFailed) {
            // Attendu.
        }

        $this->assertSame(0, SocialAccount::count());
    }

    #[Test]
    public function the_message_names_what_is_missing_and_what_to_do(): void
    {
        try {
            app(SocialAccountLinker::class)->link($this->clipper(), $this->connected(['user.info.basic']));
            $this->fail('La liaison aurait dû être refusée.');
        } catch (SocialProviderFailed $exception) {
            // Un message qui ne dit pas quoi refaire produit un ticket support.
            $this->assertStringContainsString('Relancez la connexion', $exception->getMessage());
            $this->assertStringContainsString('TikTok', $exception->getMessage());
        }
    }

    #[Test]
    public function a_provider_that_reports_no_scope_does_not_block_anyone(): void
    {
        // Un refus à tort empêcherait quelqu'un de gagner sa vie ; un contrôle
        // manqué ne fait que revenir au comportement d'avant.
        $account = app(SocialAccountLinker::class)->link($this->clipper(), $this->connected([]));

        $this->assertTrue($account->exists);
    }

    #[Test]
    public function instagram_does_not_demand_the_page_permissions_it_never_gets_back(): void
    {
        // Meta ne renvoie pas toujours les permissions « pages » dans la
        // réponse du jeton : les exiger produirait un refus systématique.
        $account = app(SocialAccountLinker::class)->link(
            $this->clipper(),
            $this->connected(['instagram_basic', 'instagram_manage_insights'], Platform::Instagram),
        );

        $this->assertTrue($account->exists);
    }

    #[Test]
    public function every_required_scope_is_also_requested(): void
    {
        // Exiger une permission qu'on n'a jamais demandée rendrait toute
        // liaison impossible, sur les trois plateformes à la fois. Contrôlé
        // aussi sur le fournisseur simulé, qui sert de référence en
        // développement.
        foreach ([...static::realProviders(), ...static::fakeProviders()] as $label => $provider) {
            $this->assertSame(
                [],
                array_diff($provider->requiredScopes(), $provider->requestedScopes()),
                "Une permission exigée n'est pas demandée pour {$label}.",
            );
            $this->assertNotSame([], $provider->requiredScopes(), "Aucune permission exigée pour {$label}.");
        }
    }

    #[Test]
    public function the_consent_url_asks_for_exactly_the_declared_scopes(): void
    {
        // La chaîne de l'URL et la liste déclarée doivent rester la même
        // source : deux listes finissent par diverger, et le contrôle refuse
        // alors une permission que l'utilisateur n'a jamais pu accorder.
        //
        // Sur les vrais fournisseurs uniquement : le simulé n'a pas d'écran de
        // consentement, il revient directement à l'adresse de retour.
        foreach (static::realProviders() as $label => $provider) {
            $url = urldecode($provider->redirectUrl('un-etat'));

            foreach ($provider->requestedScopes() as $scope) {
                $this->assertStringContainsString(
                    $scope,
                    $url,
                    "La permission {$scope} n'est pas demandée dans l'URL de {$label}.",
                );
            }
        }
    }

    /** @return array<string, SocialProvider> */
    protected static function realProviders(): array
    {
        return [
            'TikTok' => app(TikTokProvider::class),
            'YouTube' => app(YouTubeProvider::class),
            'Instagram' => app(InstagramProvider::class),
        ];
    }

    /** @return array<string, SocialProvider> */
    protected static function fakeProviders(): array
    {
        $providers = [];

        foreach (Platform::cases() as $platform) {
            $providers[$platform->label().' (simulé)'] = new FakeSocialProvider($platform);
        }

        return $providers;
    }
}
