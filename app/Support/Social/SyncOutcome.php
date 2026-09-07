<?php

namespace App\Support\Social;

/**
 * Pourquoi un relevé manuel n'a rien donné.
 *
 * `syncClip()` rendait un simple booléen pour trois situations distinctes :
 * délai de garde, compte à reconnecter, échec de l'API. Le contrôleur les
 * annonçait donc toutes comme « déjà relevé récemment » — un message faux dans
 * deux cas sur trois, qui envoie le clippeur attendre quinze minutes pour rien
 * alors que son compte a besoin d'être reconnecté.
 */
enum SyncOutcome: string
{
    case Updated = 'updated';
    case Cooldown = 'cooldown';
    case AccountUnusable = 'account_unusable';
    case Unreachable = 'unreachable';

    public function succeeded(): bool
    {
        return $this === self::Updated;
    }

    /** Ce qu'on dit au clippeur, et surtout ce qu'il peut faire ensuite. */
    public function message(int $cooldownMinutes = 15): string
    {
        return match ($this) {
            self::Updated => 'Relevé effectué.',

            self::Cooldown => sprintf(
                'Déjà relevé il y a moins de %d minutes. Les plateformes ne mettent pas leurs '
                .'compteurs à jour en continu : revenez un peu plus tard.',
                $cooldownMinutes,
            ),

            self::AccountUnusable => 'Le compte lié à ce clip doit être reconnecté avant de pouvoir '
                .'relever ses vues. Rendez-vous dans « Mes comptes ».',

            self::Unreachable => 'La plateforme n’a pas renvoyé cette publication. Elle a été '
                .'supprimée, passée en privé, ou elle appartient à un autre compte que celui lié.',
        };
    }
}
