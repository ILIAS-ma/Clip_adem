<?php

namespace App\Support\Budget;

/**
 * Simulation d'un crédit, sans aucune écriture.
 *
 * Sert au module clippeur pour afficher « ce clip vous rapportera X € »
 * avant que la synchronisation des vues n'ait lieu.
 */
final readonly class BudgetQuote
{
    public function __construct(
        public CreditOutcome $outcome,
        /** Vues nouvelles depuis le dernier crédit. */
        public int $deltaViews,
        /** Ce que vaudrait le delta sans aucun plafond. */
        public int $grossCents,
        /** Ce qui serait réellement payé, plafonds appliqués. */
        public int $payableCents,
        /** Vues effectivement rémunérées par ce montant. */
        public int $payableViews,
        /** Budget restant sur la campagne avant l'opération. */
        public int $remainingCents,
        public ?int $ratePer1kCents = null,
    ) {}

    /** Le plafonnement a-t-il rogné le montant dû ? */
    public function isCapped(): bool
    {
        return $this->payableCents < $this->grossCents;
    }

    public static function nothing(CreditOutcome $outcome, int $remainingCents, int $deltaViews = 0): self
    {
        return new self($outcome, $deltaViews, 0, 0, 0, $remainingCents);
    }
}
