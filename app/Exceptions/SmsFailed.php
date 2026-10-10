<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * L'envoi du SMS a échoué.
 *
 * Le code HTTP est porté par l'exception plutôt que noyé dans le message :
 * c'est lui qui décide si l'on réessaie ou si l'on s'arrête. Un 4xx vient du
 * numéro ou de la configuration et se reproduira à l'identique ; un 5xx est
 * une panne du prestataire, qui passera.
 */
class SmsFailed extends RuntimeException
{
    public ?int $status = null;

    public static function refused(int $status, string $body): self
    {
        $e = new self(sprintf('Envoi du SMS refusé (HTTP %d) : %s', $status, mb_substr($body, 0, 300)));
        $e->status = $status;

        return $e;
    }

    public static function notConfigured(): self
    {
        return new self("L'envoi de SMS n'est pas configuré : renseignez les identifiants du prestataire.");
    }

    /** Vaut-il la peine de réessayer ? */
    public function isTemporary(): bool
    {
        return $this->status !== null && $this->status >= 500;
    }
}
