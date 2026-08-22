<?php

namespace App\Contracts;

use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use App\Support\Budget\BudgetQuote;
use App\Support\Budget\CreditResult;
use App\Support\Budget\ReversalResult;

/**
 * Point d'entrée UNIQUE de toute mutation de budget.
 *
 * Le module clippeur appelle ce service ; il n'écrit jamais lui-même sur
 * campaigns.spent_cents, clips.paid_views ou clips.earned_cents.
 */
interface CampaignBudgetService
{
    /** Budget restant en centimes. Lecture non verrouillée : valeur d'affichage. */
    public function remaining(Campaign|int $campaign): int;

    /**
     * Simule ce que rapporterait ce clip à ce nombre de vues. N'écrit rien.
     * À utiliser pour afficher une estimation côté clippeur.
     */
    public function quote(Clip $clip, int $newTotalViews): BudgetQuote;

    /**
     * Crédite le delta de vues d'un clip, dans une transaction verrouillée.
     *
     * @param  string  $idempotencyKey  Clé unique de l'opération, typiquement
     *                                  BudgetTransaction::snapshotKey($clipId, $snapshotId).
     *                                  Un rejeu avec la même clé ne débite pas deux fois.
     */
    public function creditViews(Clip $clip, int $newTotalViews, string $idempotencyKey): CreditResult;

    /**
     * Annule tout ce qu'un clip a coûté et restitue le budget.
     * Utilisé par la modération : clip frauduleux, brief non respecté.
     */
    public function reverseClip(Clip $clip, string $reason, ?User $by = null): ReversalResult;

    /** Le clippeur peut-il encore poster un clip sur cette campagne ? */
    public function acceptsNewClips(Campaign $campaign): bool;
}
