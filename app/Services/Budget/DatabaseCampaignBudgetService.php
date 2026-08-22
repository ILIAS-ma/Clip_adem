<?php

namespace App\Services\Budget;

use App\Contracts\CampaignBudgetService;
use App\Enums\BudgetTransactionType;
use App\Enums\CampaignStatus;
use App\Events\CampaignExhausted;
use App\Models\BudgetTransaction;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use App\Support\Budget\BudgetQuote;
use App\Support\Budget\CreditOutcome;
use App\Support\Budget\CreditResult;
use App\Support\Budget\ReversalResult;
use Illuminate\Support\Facades\DB;

/**
 * Moteur de budget.
 *
 * Invariants garantis, dans cet ordre de priorité :
 *
 *  1. spent_cents n'excède JAMAIS budget_total_cents, quel que soit le nombre
 *     de crédits concurrents.
 *  2. SUM(campaign_budget_transactions.amount_cents) === campaigns.spent_cents.
 *  3. Une même clé d'idempotence ne débite qu'une fois.
 *  4. Aucun crédit négatif : les plateformes révisent leurs compteurs de vues
 *     à la baisse, on ne reprend jamais d'argent déjà versé.
 *
 * Deux règles de mise en œuvre à ne pas casser :
 *  - ordre de verrouillage constant campagne → clip, partout, sinon deadlock ;
 *  - aucun appel réseau ni job dans la transaction : un verrou de campagne
 *    tenu pendant un appel à l'API PayPal bloquerait tous les autres crédits.
 */
class DatabaseCampaignBudgetService implements CampaignBudgetService
{
    /** Nombre de reprises sur deadlock InnoDB (erreur 1213). */
    protected const TRANSACTION_ATTEMPTS = 3;

    public function remaining(Campaign|int $campaign): int
    {
        $campaign = $campaign instanceof Campaign
            ? $campaign
            : Campaign::findOrFail($campaign);

        return $campaign->remainingCents();
    }

    public function quote(Clip $clip, int $newTotalViews): BudgetQuote
    {
        $campaign = $clip->campaign;

        return $this->compute($campaign, $clip, $newTotalViews);
    }

    public function creditViews(Clip $clip, int $newTotalViews, string $idempotencyKey): CreditResult
    {
        $exhaustedCampaign = null;

        $result = DB::transaction(function () use ($clip, $newTotalViews, $idempotencyKey, &$exhaustedCampaign) {
            // 1. Idempotence, premier passage : évite de poser un verrou pour
            //    rien dans le cas courant du webhook rejoué longtemps après.
            if ($existing = $this->findTransaction($idempotencyKey)) {
                return $this->alreadyProcessed($existing);
            }

            // 2. Verrous, TOUJOURS campagne puis clip. Tous les crédits d'une
            //    même campagne se sérialisent ici ; deux campagnes différentes
            //    ne se bloquent pas entre elles.
            $campaign = Campaign::whereKey($clip->campaign_id)->lockForUpdate()->firstOrFail();
            $clip = Clip::whereKey($clip->getKey())->lockForUpdate()->firstOrFail();

            // 3. Idempotence, second passage. Deux rejeux simultanés peuvent
            //    tous deux passer l'étape 1 avant que le premier ne commite ;
            //    ce n'est qu'après le verrou que la réponse est déterministe.
            //    Sans ce contrôle, le rejeu perdant ressortirait en
            //    « aucune vue nouvelle » au lieu de « déjà traité ».
            //
            //    La lecture DOIT être verrouillante : en REPEATABLE READ, une
            //    lecture ordinaire réutilise le snapshot pris à l'étape 1 et ne
            //    verrait pas la ligne commitée entre-temps par le concurrent.
            if ($existing = $this->findTransaction($idempotencyKey, locking: true)) {
                return $this->alreadyProcessed($existing);
            }

            $quote = $this->compute($campaign, $clip, $newTotalViews);

            if ($quote->payableCents <= 0) {
                // Budget tombé à zéro pendant que d'autres clips étaient crédités :
                // on fige le statut maintenant plutôt que d'attendre le prochain passage.
                $exhausted = $this->markExhaustedIfNeeded($campaign);
                $exhaustedCampaign = $exhausted ? $campaign : null;

                return CreditResult::skipped(
                    $quote->outcome,
                    $campaign->remainingCents(),
                    $campaign->status === CampaignStatus::Exhausted,
                );
            }

            // 3. Écritures. Les seules de toute l'application sur ces colonnes.
            $clip->forceFill([
                'paid_views' => $clip->paid_views + $quote->payableViews,
                'earned_cents' => $clip->earned_cents + $quote->payableCents,
            ])->save();

            $campaign->forceFill([
                'spent_cents' => $campaign->spent_cents + $quote->payableCents,
            ])->save();

            $transaction = BudgetTransaction::create([
                'campaign_id' => $campaign->getKey(),
                'clip_id' => $clip->getKey(),
                'user_id' => $clip->user_id,
                'type' => BudgetTransactionType::Credit,
                'amount_cents' => $quote->payableCents,
                'views_delta' => $quote->payableViews,
                'rate_per_1k_cents' => $quote->ratePer1kCents,
                'balance_after_cents' => $campaign->remainingCents(),
                'idempotency_key' => $idempotencyKey,
                'meta' => [
                    'views_total' => $newTotalViews,
                    'gross_cents' => $quote->grossCents,
                    'capped' => $quote->isCapped(),
                ],
            ]);

            // 4. Bascule automatique du statut, dans la même transaction que le
            //    débit : il n'existe aucun instant où le budget est à zéro et la
            //    campagne encore annoncée comme active.
            $exhausted = $this->markExhaustedIfNeeded($campaign);
            $exhaustedCampaign = $exhausted ? $campaign : null;

            return new CreditResult(
                outcome: $quote->isCapped() ? CreditOutcome::Capped : CreditOutcome::Credited,
                creditedCents: $quote->payableCents,
                creditedViews: $quote->payableViews,
                remainingCents: $campaign->remainingCents(),
                campaignExhausted: $campaign->status === CampaignStatus::Exhausted,
                transaction: $transaction,
            );
        }, self::TRANSACTION_ATTEMPTS);

        if ($exhaustedCampaign) {
            CampaignExhausted::dispatch($exhaustedCampaign);
        }

        return $result;
    }

    public function reverseClip(Clip $clip, string $reason, ?User $by = null): ReversalResult
    {
        return DB::transaction(function () use ($clip, $reason, $by) {
            $campaign = Campaign::whereKey($clip->campaign_id)->lockForUpdate()->firstOrFail();
            $clip = Clip::whereKey($clip->getKey())->lockForUpdate()->firstOrFail();

            $refund = $clip->earned_cents;
            $refundedViews = $clip->paid_views;

            if ($refund <= 0) {
                return ReversalResult::nothing($campaign->remainingCents());
            }

            $clip->forceFill([
                'paid_views' => 0,
                'earned_cents' => 0,
            ])->save();

            $campaign->forceFill([
                // Ne peut pas devenir négatif : le grand livre garantit que
                // spent_cents contient au moins ce que ce clip a coûté.
                'spent_cents' => max(0, $campaign->spent_cents - $refund),
            ])->save();

            $transaction = BudgetTransaction::create([
                'campaign_id' => $campaign->getKey(),
                'clip_id' => $clip->getKey(),
                'user_id' => $clip->user_id,
                'type' => BudgetTransactionType::Reversal,
                'amount_cents' => -$refund,
                'views_delta' => -$refundedViews,
                'balance_after_cents' => $campaign->remainingCents(),
                'idempotency_key' => sprintf('clip:%d:reversal:%s', $clip->getKey(), now()->format('YmdHisv')),
                'created_by' => $by?->getKey(),
                'meta' => ['reason' => $reason],
            ]);

            // Le budget rendu peut relancer une campagne épuisée. On ne repasse
            // pas par transitionTo() : la modération ne doit jamais échouer sur
            // une règle d'activation (brief vide, taux désactivé entre-temps).
            $reactivated = false;

            if ($campaign->status === CampaignStatus::Exhausted && $campaign->remainingCents() > 0) {
                $campaign->forceFill([
                    'status' => CampaignStatus::Active,
                    'exhausted_at' => null,
                ])->save();

                $reactivated = true;
            }

            return new ReversalResult(
                refundedCents: $refund,
                refundedViews: $refundedViews,
                remainingCents: $campaign->remainingCents(),
                campaignReactivated: $reactivated,
                transaction: $transaction,
            );
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function acceptsNewClips(Campaign $campaign): bool
    {
        return $campaign->status->acceptsNewClips() && $campaign->acceptsCredits();
    }

    // ------------------------------------------------------------------
    // Calcul pur — aucune écriture. Partagé par quote() et creditViews()
    // pour qu'une simulation et un crédit réel ne puissent jamais diverger.
    // ------------------------------------------------------------------

    protected function compute(Campaign $campaign, Clip $clip, int $newTotalViews): BudgetQuote
    {
        $remaining = $campaign->remainingCents();

        if (! $campaign->acceptsCredits()) {
            return BudgetQuote::nothing(CreditOutcome::CampaignClosed, $remaining);
        }

        if (! $clip->status->isPayable()) {
            return BudgetQuote::nothing(CreditOutcome::ClipNotPayable, $remaining);
        }

        if ($newTotalViews < $campaign->min_views_per_clip) {
            return BudgetQuote::nothing(CreditOutcome::BelowThreshold, $remaining);
        }

        $rate = $campaign->rateFor($clip->platform);

        if (! $rate || $rate <= 0) {
            return BudgetQuote::nothing(CreditOutcome::NoRate, $remaining);
        }

        // Jamais de delta négatif : TikTok et Instagram révisent régulièrement
        // leurs compteurs à la baisse. On ne reprend pas d'argent déjà crédité.
        $deltaViews = max(0, $newTotalViews - $clip->paid_views);

        if ($deltaViews === 0) {
            return BudgetQuote::nothing(CreditOutcome::NothingToCredit, $remaining);
        }

        // Arithmétique entière de bout en bout, arrondi plancher, toujours en
        // faveur du budget. Aucun flottant n'entre dans un calcul d'argent.
        $gross = intdiv($deltaViews * $rate, 1000);

        // Plafonnement en cascade. Les lectures ci-dessous sont sûres : tous
        // les crédits de cette campagne sont sérialisés par le verrou posé sur
        // sa ligne, donc aucun autre processus ne peut modifier ces totaux
        // entre ce calcul et l'écriture.
        $caps = [$gross, $remaining];

        if ($campaign->max_payout_per_clip_cents !== null) {
            $caps[] = max(0, $campaign->max_payout_per_clip_cents - $clip->earned_cents);
        }

        if ($campaign->max_payout_per_clipper_cents !== null) {
            $clipperEarned = (int) Clip::where('campaign_id', $campaign->getKey())
                ->where('user_id', $clip->user_id)
                ->sum('earned_cents');

            $caps[] = max(0, $campaign->max_payout_per_clipper_cents - $clipperEarned);
        }

        $payable = min($caps);

        if ($payable <= 0) {
            return BudgetQuote::nothing(CreditOutcome::NoBudgetLeft, $remaining, $deltaViews);
        }

        // Vues réellement rémunérées, recalculées DEPUIS le montant plafonné.
        // Sans cette étape, un clip plafonné verrait ses vues excédentaires
        // marquées comme payées : elles ne seraient jamais rattrapées si du
        // budget se libérait ensuite (annulation d'un clip frauduleux).
        $payableViews = intdiv($payable * 1000, $rate);

        return new BudgetQuote(
            outcome: $payable < $gross ? CreditOutcome::Capped : CreditOutcome::Credited,
            deltaViews: $deltaViews,
            grossCents: $gross,
            payableCents: $payable,
            payableViews: $payableViews,
            remainingCents: $remaining,
            ratePer1kCents: $rate,
        );
    }

    protected function findTransaction(string $idempotencyKey, bool $locking = false): ?BudgetTransaction
    {
        return BudgetTransaction::where('idempotency_key', $idempotencyKey)
            ->when($locking, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    protected function alreadyProcessed(BudgetTransaction $transaction): CreditResult
    {
        return new CreditResult(
            outcome: CreditOutcome::AlreadyProcessed,
            remainingCents: $transaction->balance_after_cents,
            transaction: $transaction,
        );
    }

    /** @return bool true si la campagne vient de basculer en Épuisée. */
    protected function markExhaustedIfNeeded(Campaign $campaign): bool
    {
        if ($campaign->status !== CampaignStatus::Active || ! $campaign->isExhausted()) {
            return false;
        }

        $campaign->forceFill([
            'status' => CampaignStatus::Exhausted,
            'exhausted_at' => now(),
        ])->save();

        return true;
    }
}
