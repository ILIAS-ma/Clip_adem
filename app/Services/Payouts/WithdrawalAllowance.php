<?php

namespace App\Services\Payouts;

use App\Enums\PayoutStatus;
use App\Models\User;
use App\Support\Payouts\AllowanceUsage;

/**
 * Plafond annuel des retraits d'un clippeur sans SIRET vérifié.
 *
 * Calculé à chaque fois depuis la table des retraits, jamais stocké : comme le
 * solde, un compteur dénormalisé finirait par mentir.
 */
class WithdrawalAllowance
{
    public function enabled(): bool
    {
        return (bool) config('clipping.legal.enabled');
    }

    public function appliesTo(User $clipper): bool
    {
        return $this->enabled() && ! $clipper->isVerifiedBusiness();
    }

    /**
     * Ce qui a déjà été demandé ou versé sur l'année civile en cours.
     *
     * Les mêmes statuts que le solde immobilisé : un retrait annulé ou échoué
     * n'a pas consommé le plafond, un retrait en attente si — sinon dix
     * demandes simultanées passeraient toutes sous la barre.
     */
    public function usage(User $clipper): AllowanceUsage
    {
        $statuses = array_map(
            fn (PayoutStatus $status) => $status->value,
            array_filter(PayoutStatus::cases(), fn (PayoutStatus $status) => $status->locksBalance()),
        );

        $row = $clipper->payouts()
            ->whereIn('status', $statuses)
            ->whereBetween('requested_at', [now()->startOfYear(), now()->endOfYear()])
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as cents, COUNT(*) as payouts')
            ->toBase()
            ->first();

        return new AllowanceUsage(
            usedCents: (int) $row->cents,
            usedPayouts: (int) $row->payouts,
            maxCents: (int) config('clipping.legal.unregistered_max_cents_per_year'),
            maxPayouts: (int) config('clipping.legal.unregistered_max_payouts_per_year'),
        );
    }

    /**
     * Le montant maximal que ce clippeur peut encore retirer cette année, ou
     * null s'il n'est pas plafonné.
     */
    public function remainingCents(User $clipper): ?int
    {
        if (! $this->appliesTo($clipper)) {
            return null;
        }

        $usage = $this->usage($clipper);

        return $usage->payoutsLeft() > 0 ? $usage->centsLeft() : 0;
    }
}
