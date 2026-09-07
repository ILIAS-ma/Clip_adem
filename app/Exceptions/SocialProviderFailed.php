<?php

namespace App\Exceptions;

use App\Enums\Platform;
use RuntimeException;

class SocialProviderFailed extends RuntimeException
{
    /**
     * Code HTTP renvoyé par la plateforme, quand il y en a un.
     *
     * Porté sur l'exception plutôt que noyé dans le message : distinguer un
     * jeton mort (401) d'une panne passagère (500) change ce qu'on fait — on
     * arrête d'interroger le compte dans un cas, on réessaie dans l'autre.
     */
    public ?int $status = null;

    /** L'échec vient-il d'une autorisation, et non d'un incident réseau ? */
    public function isAuthFailure(): bool
    {
        return in_array($this->status, [401, 403], true);
    }

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
        return tap(
            new self(sprintf('Lecture %s échouée (HTTP %d) : %s', $platform->label(), $status, $body)),
            fn (self $e) => $e->status = $status,
        );
    }

    public static function noChannel(Platform $platform): self
    {
        return new self(sprintf('Aucune chaîne %s associée à ce compte.', $platform->label()));
    }

    /**
     * @param  array<int, string>  $missing
     */
    public static function missingScopes(Platform $platform, array $missing): self
    {
        return new self(sprintf(
            'Il manque une autorisation sur votre compte %s : %s. Relancez la connexion en laissant '
            .'toutes les cases activées — sans elle, nous ne pouvons pas lire les vues de vos clips, '
            .'donc pas vous payer.',
            $platform->label(),
            implode(', ', $missing),
        ));
    }

    public static function invalidState(): self
    {
        return new self('Session de connexion expirée ou invalide. Relancez la liaison du compte.');
    }
}
