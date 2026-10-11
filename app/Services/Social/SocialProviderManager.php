<?php

namespace App\Services\Social;

use App\Contracts\SocialProvider;
use App\Enums\Platform;
use RuntimeException;

/**
 * Choisit l'implémentation à utiliser pour une plateforme.
 *
 * Tant qu'une plateforme n'a pas ses identifiants d'application, le
 * fournisseur simulé prend le relais hors production : le produit reste
 * utilisable de bout en bout pendant les jours ouvrés que prennent les revues
 * d'application TikTok et Meta. En production, l'absence de clés est une
 * erreur, pas un mode dégradé silencieux.
 */
class SocialProviderManager
{
    /** @var array<string, SocialProvider> */
    protected array $resolved = [];

    public function for(Platform $platform): SocialProvider
    {
        return $this->resolved[$platform->value] ??= $this->resolve($platform);
    }

    /** @return array<int, SocialProvider> */
    public function all(): array
    {
        return array_map(fn (Platform $platform) => $this->for($platform), Platform::cases());
    }

    /**
     * La plateforme manque-t-elle de vrais identifiants ?
     *
     * Répond sans jamais résoudre le fournisseur, donc sans jamais lever.
     * Auparavant la question passait par `for()`, qui lève en production
     * pour une plateforme sans clés : « Mes comptes », « Mes clips » et le
     * catalogue — qui posent la question pour chaque plateforme afin de
     * masquer celles qui ne marchent pas — auraient rendu une erreur 500 dès
     * le passage en production, à cause de YouTube et d'Instagram qu'on
     * n'utilise pas.
     */
    public function isSimulated(Platform $platform): bool
    {
        return ! $this->real($platform)->isConfigured();
    }

    /**
     * Peut-on réellement s'en servir ici ?
     *
     * Hors production, le fournisseur simulé suffit. En production, seule
     * une plateforme configurée compte : c'est ce que le relevé des vues
     * interroge avant de s'y attaquer, plutôt que de lever en plein passage.
     */
    public function isAvailable(Platform $platform): bool
    {
        return ! $this->isSimulated($platform) || ! app()->isProduction();
    }

    /** Le vrai fournisseur, configuré ou non. */
    protected function real(Platform $platform): SocialProvider
    {
        return match ($platform) {
            Platform::YouTube => app(YouTubeProvider::class),
            Platform::TikTok => app(TikTokProvider::class),
            Platform::Instagram => app(InstagramProvider::class),
        };
    }

    protected function resolve(Platform $platform): SocialProvider
    {
        $provider = $this->real($platform);

        if ($provider->isConfigured()) {
            return $provider;
        }

        if (app()->isProduction()) {
            throw new RuntimeException(sprintf(
                'Aucun identifiant d\'application pour %s : renseignez les clés avant de déployer.',
                $platform->label(),
            ));
        }

        return new FakeSocialProvider($platform);
    }
}
