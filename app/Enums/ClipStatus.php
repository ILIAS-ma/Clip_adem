<?php

namespace App\Enums;

enum ClipStatus: string
{
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Invalidated = 'invalidated';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'En attente de validation',
            self::Approved => 'Validé',
            self::Rejected => 'Refusé',
            self::Invalidated => 'Invalidé',
        };
    }

    /** Seul un clip validé peut être crédité. */
    public function isPayable(): bool
    {
        return $this === self::Approved;
    }
}
