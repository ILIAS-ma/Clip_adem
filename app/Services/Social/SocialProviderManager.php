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

    /** La plateforme tourne-t-elle sur un fournisseur simulé ? */
    public function isSimulated(Platform $platform): bool
    {
        return $this->for($platform) instanceof FakeSocialProvider;
    }

    protected function resolve(Platform $platform): SocialProvider
    {
        $provider = match ($platform) {
            Platform::YouTube => app(YouTubeProvider::class),
            Platform::TikTok => app(TikTokProvider::class),
            Platform::Instagram => app(InstagramProvider::class),
        };

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
