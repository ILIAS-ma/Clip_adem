<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La recherche de clips a échoué.
 *
 * Le message est destiné à l'écran du clippeur : il doit dire quoi faire, pas
 * décrire une panne. « Twitch a répondu 429 » ne lui apprend rien ; « trop de
 * recherches d'un coup, réessayez dans une minute » lui dit d'attendre.
 */
class ClipSearchFailed extends RuntimeException
{
    public ?int $status = null;

    public string $detail = '';

    public static function refused(int $status, string $body): self
    {
        $message = match (true) {
            $status === 401 || $status === 403 => 'La connexion à Twitch a été refusée. '
                .'Les identifiants de la plateforme doivent être renouvelés.',
            $status === 429 => 'Trop de recherches d’un coup. Réessayez dans une minute.',
            $status >= 500 => 'Twitch ne répond pas pour le moment. Réessayez dans quelques minutes.',
            default => 'La recherche a échoué. Vérifiez le nom de la chaîne et réessayez.',
        };

        $e = new self($message);
        $e->status = $status;
        // Le corps de la réponse sert aux journaux, jamais à l'écran : un
        // extrait de JSON brut inquiète le clippeur sans rien lui apprendre.
        $e->detail = mb_substr($body, 0, 500);

        return $e;
    }

    public static function notConfigured(): self
    {
        return new self('La recherche de clips n’est pas encore branchée sur Twitch.');
    }

    public static function noChannel(): self
    {
        return new self('Indiquez au moins une chaîne à explorer.');
    }

    /** Vaut-il la peine de réessayer ? */
    public function isTemporary(): bool
    {
        return $this->status === 429 || ($this->status !== null && $this->status >= 500);
    }
}
