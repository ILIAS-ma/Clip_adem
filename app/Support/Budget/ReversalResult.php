<?php

namespace App\Support\Budget;

use App\Models\BudgetTransaction;

final readonly class ReversalResult
{
    public function __construct(
        /** Montant rendu au budget de la campagne, en centimes. */
        public int $refundedCents,
        public int $refundedViews,
        public int $remainingCents,
        /** La campagne était épuisée et a été réouverte par ce remboursement. */
        public bool $campaignReactivated = false,
        public ?BudgetTransaction $transaction = null,
    ) {}

    public static function nothing(int $remainingCents): self
    {
        return new self(0, 0, $remainingCents);
    }
}
