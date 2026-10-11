<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Un travail sur fichier a échoué.
 *
 * Les messages sont destinés à l'écran du clippeur : ils disent quoi faire,
 * pas ce qui s'est passé dans le processus. « yt-dlp exited with code 1 » ne
 * lui apprend rien ; « ce clip n'est plus disponible » lui dit d'en choisir
 * un autre. Le détail technique part dans les journaux.
 */
class MediaFailed extends RuntimeException
{
    public string $detail = '';

    public static function outilAbsent(string $outil): self
    {
        return new self("L'outil de traitement vidéo n'est pas installé sur le serveur. "
            .'Prévenez-nous : ce n’est pas votre clip qui est en cause.');
    }

    public static function urlRefusee(): self
    {
        return new self('Cette adresse n’est pas celle d’un clip Twitch ou Kick.');
    }

    public static function fichierAbsent(): self
    {
        return new self('Le clip dépasse la taille autorisée, ou n’a pas pu être récupéré.');
    }

    public static function telechargementRefuse(string $sortie): self
    {
        /*
         * Les deux causes qu'on sait nommer, parce que ce sont les seules sur
         * lesquelles le clippeur peut agir. Le reste reste vague plutôt que
         * d'inventer une explication.
         */
        $message = match (true) {
            str_contains($sortie, '404') || str_contains($sortie, 'does not exist') => 'Ce clip n’existe plus : il a été supprimé de la chaîne.',
            str_contains($sortie, 'Private') || str_contains($sortie, 'subscriber') => 'Ce clip n’est pas public.',
            default => 'Le téléchargement a échoué. Réessayez, ou choisissez un autre clip.',
        };

        $e = new self($message);
        $e->detail = mb_substr($sortie, 0, 1000);

        return $e;
    }

    public static function quotaAtteint(): self
    {
        return new self('Votre espace est plein. Supprimez des fichiers avant d’en télécharger d’autres.');
    }

    public static function rendu(string $sortie): self
    {
        $e = new self('Le montage a échoué. Réessayez : si cela se reproduit, le clip source est en cause.');
        $e->detail = mb_substr($sortie, 0, 1000);

        return $e;
    }

    public static function sourceIntrouvable(): self
    {
        return new self('Le clip source n’est plus sur le serveur. Téléchargez-le à nouveau.');
    }
}
