<?php

namespace App\Exceptions;

use App\Enums\Platform;
use RuntimeException;

/**
 * Refus de soumission, avec un message directement affichable au clippeur.
 *
 * Chaque message dit ce qui ne va pas ET comment le corriger : « lien non
 * reconnu » sans la marche à suivre produit un ticket de support.
 */
class ClipSubmissionRefused extends RuntimeException
{
    public static function invalidUrl(): self
    {
        return new self("Ce lien n'est pas une adresse valide. Copiez-le depuis le bouton « Partager » de la publication.");
    }

    public static function unsupportedPlatform(): self
    {
        return new self('Seuls les liens TikTok, YouTube et Instagram sont acceptés.');
    }

    public static function unrecognisedUrl(string $platform): self
    {
        return new self("Ce lien {$platform} n'est pas reconnu. Utilisez l'adresse complète de la publication.");
    }

    public static function shortLink(string $platform): self
    {
        return new self("Les liens raccourcis {$platform} ne peuvent pas être vérifiés. Ouvrez la publication et copiez l'adresse complète.");
    }

    public static function alreadySubmitted(): self
    {
        return new self('Cette publication a déjà été soumise.');
    }

    public static function noParticipation(): self
    {
        return new self("Rejoignez la campagne avant d'y soumettre un clip.");
    }

    public static function participationNotApproved(): self
    {
        return new self('Votre participation à cette campagne attend encore la validation d\'un administrateur.');
    }

    public static function platformMismatch(Platform $clip, Platform $account): self
    {
        return new self(sprintf(
            'Ce lien %s ne correspond pas au compte %s utilisé pour rejoindre la campagne.',
            $clip->label(),
            $account->label(),
        ));
    }

    public static function campaignClosed(): self
    {
        return new self("Cette campagne n'accepte plus de nouveaux clips.");
    }
}
