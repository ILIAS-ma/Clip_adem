<?php

namespace App\Enums;

enum CampaignMode: string
{
    /** Chaque tranche de 1 000 vues est payée au fil de l'eau. */
    case Cpm = 'cpm';

    /** Rien n'est payé aux vues : des prix récompensent le haut du classement. */
    case Contest = 'contest';

    public function label(): string
    {
        return match ($this) {
            self::Cpm => 'Payée aux vues',
            self::Contest => 'Classement',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $mode) => [$mode->value => $mode->label()])->all();
    }
}
