<?php

namespace App\Enums;

/**
 * Toute action de modération est journalisée : en cas de litige sur un
 * paiement, il faut pouvoir dire qui a invalidé quoi, quand et pourquoi.
 */
enum ModerationAction: string
{
    case ClipApproved = 'clip_approved';
    case ClipRejected = 'clip_rejected';
    case ClipInvalidated = 'clip_invalidated';
    case ClipperBanned = 'clipper_banned';
    case ClipperUnbanned = 'clipper_unbanned';
    case PayoutApproved = 'payout_approved';
    case PayoutCancelled = 'payout_cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ClipApproved => 'Clip validé',
            self::ClipRejected => 'Clip refusé',
            self::ClipInvalidated => 'Clip invalidé',
            self::ClipperBanned => 'Clippeur banni',
            self::ClipperUnbanned => 'Clippeur débanni',
            self::PayoutApproved => 'Retrait validé',
            self::PayoutCancelled => 'Retrait annulé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ClipApproved, self::ClipperUnbanned, self::PayoutApproved => 'success',
            self::ClipRejected, self::PayoutCancelled => 'warning',
            self::ClipInvalidated, self::ClipperBanned => 'danger',
        };
    }
}
