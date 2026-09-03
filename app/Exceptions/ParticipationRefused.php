<?php

namespace App\Exceptions;

use App\Enums\Platform;
use Carbon\CarbonInterface;
use RuntimeException;

class ParticipationRefused extends RuntimeException
{
    public static function campaignClosed(): self
    {
        return new self("Cette campagne n'accepte plus de nouveaux participants.");
    }

    public static function notOpenYet(CarbonInterface $opensAt): self
    {
        return new self(sprintf(
            'Cette campagne ouvre le %s à %s. Les niveaux élevés y accèdent en avance.',
            $opensAt->format('d/m/Y'),
            $opensAt->format('H\hi'),
        ));
    }

    public static function budgetExhausted(): self
    {
        return new self('Le budget de cette campagne est épuisé.');
    }

    public static function alreadyJoined(): self
    {
        return new self('Ce compte participe déjà à cette campagne.');
    }

    public static function accountNotOwned(): self
    {
        return new self("Ce compte réseau n'est pas rattaché à votre profil.");
    }

    public static function accountNeedsReconnect(): self
    {
        return new self('Reconnectez ce compte avant de rejoindre une campagne : sans jeton valide, vos vues ne pourront pas être comptées.');
    }

    public static function platformNotOpen(Platform $platform): self
    {
        return new self(sprintf('Cette campagne n\'est pas ouverte sur %s.', $platform->label()));
    }

    public static function bannedClipper(): self
    {
        return new self('Votre compte est suspendu.');
    }
}
