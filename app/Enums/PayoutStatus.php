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
}
