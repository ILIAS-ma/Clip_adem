<?php

namespace App\Services\Clips;

use App\Contracts\CampaignBudgetService;
use App\Enums\ParticipationStatus;
use App\Exceptions\ParticipationRefused;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Adhésion d'un clippeur à une campagne.
 *
 * L'ouverture de la campagne est demandée au moteur de budget, jamais déduite
 * du statut lu en base : c'est lui qui fait autorité, et lui seul connaît le
 * reliquat réel.
 */
class ParticipationService
{
    public function __construct(
        protected CampaignBudgetService $budget,
    ) {}

    /**
     * @throws ParticipationRefused
     */
    public function join(Campaign $campaign, User $clipper, SocialAccount $account): CampaignParticipation
    {
        if ($clipper->is_banned) {
            throw ParticipationRefused::bannedClipper();
        }

        if ($account->user_id !== $clipper->getKey()) {
            throw ParticipationRefused::accountNotOwned();
        }

        if (! $account->isSyncable()) {
            throw ParticipationRefused::accountNeedsReconnect();
        }

        if (! $this->budget->acceptsNewClips($campaign)) {
            throw $campaign->remainingCents() <= 0
                ? ParticipationRefused::budgetExhausted()
                : ParticipationRefused::campaignClosed();
        }

        if ($campaign->rateFor($account->platform) === null) {
            throw ParticipationRefused::platformNotOpen($account->platform);
        }

        // Une campagne sans validation manuelle accepte immédiatement :
        // faire attendre un clippeur pour rien lui fait perdre l'élan qui
        // l'a amené sur la fiche.
        $status = $campaign->requires_approval
            ? ParticipationStatus::Pending
            : ParticipationStatus::Approved;

        try {
            return CampaignParticipation::create([
                'campaign_id' => $campaign->getKey(),
                'user_id' => $clipper->getKey(),
                'social_account_id' => $account->getKey(),
                'status' => $status,
                'applied_at' => now(),
                'approved_at' => $status === ParticipationStatus::Approved ? now() : null,
            ]);
        } catch (QueryException $exception) {
            // La contrainte unique (campaign_id, social_account_id) est le vrai
            // garde-fou : un double clic ne doit pas créer deux participations.
            if ($existing = $this->existing($campaign, $account)) {
                throw ParticipationRefused::alreadyJoined();
            }

            throw $exception;
        }
    }

    public function existing(Campaign $campaign, SocialAccount $account): ?CampaignParticipation
    {
        return CampaignParticipation::where('campaign_id', $campaign->getKey())
            ->where('social_account_id', $account->getKey())
            ->first();
    }

    /** Les comptes du clippeur avec lesquels il peut encore rejoindre la campagne. */
    public function eligibleAccounts(Campaign $campaign, User $clipper): Collection
    {
        $joined = CampaignParticipation::where('campaign_id', $campaign->getKey())
            ->where('user_id', $clipper->getKey())
            ->pluck('social_account_id');

        return $clipper->socialAccounts()
            ->where('is_active', true)
            ->get()
            ->reject(fn (SocialAccount $account) => $joined->contains($account->getKey()))
            ->filter(fn (SocialAccount $account) => $campaign->rateFor($account->platform) !== null)
            ->values();
    }
}
