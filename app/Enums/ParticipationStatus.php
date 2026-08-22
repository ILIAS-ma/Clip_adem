<?php

namespace App\Enums;

enum ParticipationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Approved => 'Acceptée',
            self::Rejected => 'Refusée',
            self::Banned => 'Bannie',
        };
    }
}
