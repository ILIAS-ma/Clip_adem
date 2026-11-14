<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Demandé',
            self::Approved => 'Validé',
            self::Processing => 'En cours',
            self::Paid => 'Payé',
            self::Failed => 'Échoué',
            self::Cancelled => 'Annulé',
        };
    }

    /**
     * Statuts qui immobilisent le solde du clippeur.
     * Un payout Failed ou Cancelled rend le solde disponible.
     */
    public function locksBalance(): bool
    {
        return in_array($this, [self::Requested, self::Approved, self::Processing, self::Paid], true);
    }

    /**
     * Demandé, pas encore reçu : l'argent a quitté le solde retirable mais
     * n'est pas arrivé sur le compte du clippeur.
     *
     * Avec `Paid`, couvre exactement les statuts de `locksBalance()` : tout ce
     * qui est immobilisé est soit en route, soit déjà arrivé. C'est la
     * distinction qui manquait côté clippeur — « demandé ou versé » regroupait
     * les deux, et un solde qui baisse sans explication passe pour une erreur.
     */
    public function awaitsTransfer(): bool
    {
        return in_array($this, [self::Requested, self::Approved, self::Processing], true);
    }
}
