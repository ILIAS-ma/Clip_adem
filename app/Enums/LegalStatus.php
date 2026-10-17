<?php

namespace App\Enums;

enum LegalStatus: string
{
    /** Particulier, sans structure déclarée : retraits plafonnés sur l'année. */
    case Individual = 'individual';

    /** Auto-entrepreneur ou société, identifié par un SIRET. */
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Particulier',
            self::Business => 'Auto-entrepreneur ou société',
        };
    }
}
