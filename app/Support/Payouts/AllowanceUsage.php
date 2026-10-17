<?php

namespace App\Support\Payouts;

/** Consommation du plafond annuel de retraits. */
final readonly class AllowanceUsage
{
    public function __construct(
        public int $usedCents,
        public int $usedPayouts,
        public int $maxCents,
        public int $maxPayouts,
    ) {}

    public function centsLeft(): int
    {
        return max(0, $this->maxCents - $this->usedCents);
    }

    public function payoutsLeft(): int
    {
        return max(0, $this->maxPayouts - $this->usedPayouts);
    }

    public function percent(): int
    {
        if ($this->maxCents <= 0) {
            return 100;
        }

        return (int) min(100, round($this->usedCents / $this->maxCents * 100));
    }
}
