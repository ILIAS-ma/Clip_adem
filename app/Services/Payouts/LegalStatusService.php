<?php

namespace App\Services\Payouts;

use App\Enums\LegalStatus;
use App\Enums\ModerationAction;
use App\Models\ModerationLog;
use App\Models\User;
use App\Notifications\SiretReviewed;
use App\Rules\Siret;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Déclaration du statut légal par le clippeur, vérification par l'équipe.
 */
class LegalStatusService
{
    /**
     * Le clippeur déclare son statut.
     *
     * Changer de SIRET, ou passer de société à particulier, efface la
     * vérification précédente : elle portait sur un autre numéro.
     */
    public function declare(User $clipper, LegalStatus $status, ?string $siret = null): User
    {
        $siret = $status === LegalStatus::Business ? Siret::normalize($siret) : null;

        if ($status === LegalStatus::Business && ! Siret::isValid($siret)) {
            throw new InvalidArgumentException('SIRET invalide.');
        }

        $unchanged = $clipper->legalStatus() === $status && $clipper->siret === $siret;

        if ($unchanged) {
            return $clipper;
        }

        $clipper->forceFill([
            'legal_status' => $status,
            'siret' => $siret,
            'siret_submitted_at' => $siret ? now() : null,
            'siret_verified_at' => null,
            'siret_verified_by' => null,
            'siret_rejection_reason' => null,
        ])->save();

        return $clipper;
    }

    public function verify(User $clipper, ?User $by = null): User
    {
        if (! filled($clipper->siret)) {
            throw new InvalidArgumentException('Aucun SIRET à vérifier.');
        }

        $clipper->forceFill([
            'siret_verified_at' => now(),
            'siret_verified_by' => $by?->getKey(),
            'siret_rejection_reason' => null,
        ])->save();

        ModerationLog::record(ModerationAction::SiretVerified, $clipper, $by, 'SIRET '.$clipper->siret);

        $this->tell($clipper, new SiretReviewed($clipper, accepted: true));

        return $clipper;
    }

    public function reject(User $clipper, string $reason, ?User $by = null): User
    {
        $clipper->forceFill([
            'siret_verified_at' => null,
            'siret_verified_by' => null,
            'siret_rejection_reason' => $reason,
        ])->save();

        ModerationLog::record(ModerationAction::SiretRejected, $clipper, $by, $reason);

        $this->tell($clipper, new SiretReviewed($clipper, accepted: false));

        return $clipper;
    }

    /** La décision est enregistrée : un e-mail qui ne part pas ne l'annule pas. */
    protected function tell(User $clipper, SiretReviewed $notification): void
    {
        try {
            $clipper->notify($notification);
        } catch (\Throwable $exception) {
            Log::warning('Notification de statut légal non envoyée', [
                'user_id' => $clipper->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
