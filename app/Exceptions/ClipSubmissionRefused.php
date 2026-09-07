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

    /**
     * Distinguer « c'est déjà le vôtre » de « quelqu'un d'autre l'a prise »
     * n'est pas un détail : dans le premier cas il n'y a rien à faire, dans le
     * second il y a un litige. Le même message pour les deux laisse le
     * clippeur ressoumettre en boucle sans comprendre.
     */
    public static function alreadySubmittedByYou(): self
    {
        return new self(
            'Vous avez déjà soumis cette publication. Retrouvez-la dans « Mes clips » '
            .'pour suivre ses vues et ses gains.'
        );
    }

    public static function alreadySubmittedBySomeoneElse(): self
    {
        return new self(
            'Cette publication a déjà été soumise par un autre clippeur. '
            .'Une même vidéo ne peut être rémunérée qu’une fois.'
        );
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
