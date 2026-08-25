<?php

namespace App\Exceptions;

use App\Enums\Platform;
use RuntimeException;

class SocialProviderFailed extends RuntimeException
{
    public static function notConfigured(Platform $platform): self
    {
        return new self(sprintf(
            "L'intégration %s n'est pas configurée : renseignez ses identifiants d'application.",
            $platform->label(),
        ));
    }

    public static function tokenExchange(Platform $platform, int $status, string $body): self
    {
        return new self(sprintf('Échange de jeton %s refusé (HTTP %d) : %s', $platform->label(), $status, $body));
    }

    public static function refreshFailed(Platform $platform, int $status, string $body): self
    {
        return new self(sprintf('Rafraîchissement %s refusé (HTTP %d) : %s', $platform->label(), $status, $body));
    }

    public static function missingRefreshToken(Platform $platform): self
    {
        return new self(sprintf(
            'Aucun jeton de rafraîchissement pour ce compte %s : une reconnexion manuelle est nécessaire.',
            $platform->label(),
        ));
    }

    public static function fetchFailed(Platform $platform, int $status, string $body): self
    {
        return new self(sprintf('Lecture %s échouée (HTTP %d) : %s', $platform->label(), $status, $body));
    }

    public static function noChannel(Platform $platform): self
    {
        return new self(sprintf('Aucune chaîne %s associée à ce compte.', $platform->label()));
    }

    public static function invalidState(): self
    {
        return new self('Session de connexion expirée ou invalide. Relancez la liaison du compte.');
    }
}
