<?php

namespace App\Support\Budget;

use App\Models\BudgetTransaction;

final readonly class CreditResult
{
    public function __construct(
        public CreditOutcome $outcome,
        public int $creditedCents = 0,
        public int $creditedViews = 0,
        public int $remainingCents = 0,
        public bool $campaignExhausted = false,
        public ?BudgetTransaction $transaction = null,
    ) {}

    public function wasPaid(): bool
    {
        return $this->outcome->isPaid();
    }

    public static function skipped(CreditOutcome $outcome, int $remainingCents, bool $exhausted = false): self
    {
        return new self(
            outcome: $outcome,
            remainingCents: $remainingCents,
            campaignExhausted: $exhausted,
        );
    }
}
