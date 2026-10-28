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
    case ClipDisappeared = 'clip_disappeared';
    case ClipNotOwned = 'clip_not_owned';
    case ClipOutsideWindow = 'clip_outside_window';
    case ClipSuspectedReupload = 'clip_suspected_reupload';
    case CampaignFunded = 'campaign_funded';
    case ClipperBanned = 'clipper_banned';
    case ClipperUnbanned = 'clipper_unbanned';
    case PayoutApproved = 'payout_approved';
    case PayoutCancelled = 'payout_cancelled';
    case PasswordResetLinkIssued = 'password_reset_link_issued';
    case SiretVerified = 'siret_verified';
    case SiretRejected = 'siret_rejected';
    case BonusGranted = 'bonus_granted';

    public function label(): string
    {
        return match ($this) {
            self::ClipApproved => 'Clip validé',
            self::ClipRejected => 'Clip refusé',
            self::ClipInvalidated => 'Clip invalidé',
            self::ClipDisappeared => 'Publication disparue',
            self::ClipNotOwned => 'Publication d’un autre compte',
            self::ClipOutsideWindow => 'Vidéo publiée hors de la campagne',
            self::ClipSuspectedReupload => 'Reprise probable d’une vidéo plus ancienne',
            self::CampaignFunded => 'Encaissement enregistré',
            self::ClipperBanned => 'Clippeur banni',
            self::ClipperUnbanned => 'Clippeur débanni',
            self::PayoutApproved => 'Retrait validé',
            self::PayoutCancelled => 'Retrait annulé',
            self::PasswordResetLinkIssued => 'Lien de réinitialisation créé',
            self::SiretVerified => 'SIRET vérifié',
            self::SiretRejected => 'SIRET refusé',
            self::BonusGranted => 'Prime accordée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ClipApproved, self::ClipperUnbanned, self::PayoutApproved, self::CampaignFunded,
            self::SiretVerified, self::BonusGranted => 'success',
            self::ClipRejected, self::PayoutCancelled, self::ClipDisappeared, self::PasswordResetLinkIssued,
            self::SiretRejected, self::ClipSuspectedReupload => 'warning',
            self::ClipInvalidated, self::ClipperBanned, self::ClipNotOwned, self::ClipOutsideWindow => 'danger',
        };
    }
}
