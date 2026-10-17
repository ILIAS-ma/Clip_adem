<?php

namespace App\Enums;

enum BudgetTransactionType: string
{
    /** Dépense : des vues ont été payées. amount_cents > 0. */
    case Credit = 'credit';

    /** Annulation d'un clip (fraude, non-respect du brief). amount_cents < 0. */
    case Reversal = 'reversal';

    /** Correction manuelle d'un administrateur. Signe libre. */
    case Adjustment = 'adjustment';

    /** Prime fixe sur un clip validé, sans vues associées. amount_cents > 0. */
    case Bonus = 'bonus';

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Dépense',
            self::Reversal => 'Annulation',
            self::Adjustment => 'Ajustement',
            self::Bonus => 'Prime',
        };
    }
}
