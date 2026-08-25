<?php

namespace App\Services\Payouts;

use App\Enums\ModerationAction;
use App\Enums\PayoutStatus;
use App\Exceptions\PayoutRefused;
use App\Exceptions\PayPalException;
use App\Models\ModerationLog;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Cycle de vie d'un retrait : demande, validation, versement, réconciliation.
 *
 * Un payout ne touche jamais le budget d'une campagne. Le budget est consommé
 * au moment où les vues sont créditées ; ici on ne fait que transférer au
 * clippeur de l'argent qu'il a déjà gagné.
 */
class PayoutService
{
    public function __construct(
        protected PayPalClient $paypal,
    ) {}

    /**
     * Demande de retrait, appelée depuis l'espace clippeur.
     *
     * Le solde est vérifié sous verrou, exactement comme le budget d'une
     * campagne : deux demandes simultanées ne doivent pas pouvoir retirer deux
     * fois le même argent.
     *
     * @throws PayoutRefused
     */
    public function request(User $clipper, ?int $amountCents = null): Payout
    {
        return DB::transaction(function () use ($clipper, $amountCents) {
            $clipper = User::whereKey($clipper->getKey())->lockForUpdate()->firstOrFail();

            $available = $clipper->availableBalanceCents();
            $amount = $amountCents ?? $available;
            $minimum = config('clipping.payouts.minimum_cents');

            if ($clipper->is_banned) {
                throw PayoutRefused::bannedClipper();
            }

            if (blank($clipper->paypal_email)) {
                throw PayoutRefused::missingPaypalEmail();
            }

            if ($amount < $minimum) {
                throw PayoutRefused::belowMinimum($amount, $minimum);
            }

            if ($amount > $available) {
                throw PayoutRefused::insufficientBalance($amount, $available);
            }

            $payout = Payout::create([
                'user_id' => $clipper->getKey(),
                'amount_cents' => $amount,
                'currency' => 'EUR',
                'status' => PayoutStatus::Requested,
                'paypal_email' => $clipper->paypal_email,
                'requested_at' => now(),
            ]);

            if ($this->shouldAutoApprove($payout, $clipper)) {
                $payout->forceFill([
                    'status' => PayoutStatus::Approved,
                    'approved_at' => now(),
                ])->save();
            }

            return $payout;
        });
    }

    /**
     * Validation automatique : petits montants, clippeur sans incident.
     * Tout le reste passe par un humain.
     */
    public function shouldAutoApprove(Payout $payout, ?User $clipper = null): bool
    {
        $clipper ??= $payout->user;
        $threshold = config('clipping.payouts.auto_approve_below_cents');

        if ($payout->amount_cents >= $threshold || $clipper->is_banned) {
            return false;
        }

        return ModerationLog::where('subject_type', $clipper->getMorphClass())
            ->where('subject_id', $clipper->getKey())
            ->doesntExist();
    }

    public function approve(Payout $payout, ?User $by = null): Payout
    {
        if ($payout->status !== PayoutStatus::Requested) {
            throw PayoutRefused::notPending($payout->status);
        }

        $payout->forceFill([
            'status' => PayoutStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $by?->getKey(),
        ])->save();

        ModerationLog::record(ModerationAction::PayoutApproved, $payout, $by);

        return $payout;
    }

    public function cancel(Payout $payout, string $reason, ?User $by = null): Payout
    {
        if (in_array($payout->status, [PayoutStatus::Paid, PayoutStatus::Processing], true)) {
            // Un virement parti ou en vol ne s'annule pas côté plateforme :
            // il faut attendre le retour de PayPal.
            throw PayoutRefused::alreadyInFlight($payout->status);
        }

        $payout->forceFill([
            'status' => PayoutStatus::Cancelled,
            'failure_reason' => $reason,
        ])->save();

        ModerationLog::record(ModerationAction::PayoutCancelled, $payout, $by, $reason);

        return $payout;
    }

    /**
     * Envoie les retraits validés à PayPal, par lot.
     *
     * L'ordre des opérations est ce qui protège l'argent : les payouts sont
     * marqués « en cours » avec leur identifiant de lot AVANT l'appel réseau.
     * Si l'appel se perd, on sait quoi réconcilier ; l'inverse produirait des
     * virements dont on ignore l'existence.
     *
     * @param  Collection<int, Payout>|null  $payouts  Par défaut, tous les validés.
     * @return array{batch_id: string, count: int, amount_cents: int}|null
     */
    public function sendApproved(?Collection $payouts = null): ?array
    {
        if (! $this->paypal->isConfigured()) {
            throw PayPalException::notConfigured();
        }

        $senderBatchId = 'clip-'.Str::ulid();

        $batch = DB::transaction(function () use ($payouts, $senderBatchId) {
            $query = Payout::query()
                ->where('status', PayoutStatus::Approved)
                ->when($payouts, fn ($q) => $q->whereKey($payouts->modelKeys()))
                ->limit(config('clipping.payouts.batch_size'))
                ->lockForUpdate();

            $selected = $query->get();

            if ($selected->isEmpty()) {
                return null;
            }

            Payout::whereKey($selected->modelKeys())->update([
                'status' => PayoutStatus::Processing,
                'paypal_batch_id' => $senderBatchId,
            ]);

            return $selected;
        });

        if (! $batch) {
            return null;
        }

        $items = $batch->map(fn (Payout $payout) => [
            'receiver' => $payout->paypal_email,
            'amount_cents' => $payout->amount_cents,
            'currency' => $payout->currency,
            'sender_item_id' => (string) $payout->getKey(),
        ])->all();

        try {
            $response = $this->paypal->createBatch($senderBatchId, $items);
        } catch (PayPalException $exception) {
            // On ne remet PAS les payouts en « validé » : l'appel a pu aboutir
            // côté PayPal malgré l'erreur. `payouts:sync` tranchera.
            Payout::whereKey($batch->modelKeys())->update([
                'failure_reason' => 'À réconcilier : '.Str::limit($exception->getMessage(), 400),
            ]);

            throw $exception;
        }

        // PayPal renvoie son propre identifiant de lot : on remplace le nôtre,
        // qui n'aura plus servi qu'à garantir l'idempotence de la création.
        if ($payoutBatchId = data_get($response, 'batch_header.payout_batch_id')) {
            Payout::whereKey($batch->modelKeys())->update(['paypal_batch_id' => $payoutBatchId]);
        }

        return [
            'batch_id' => $payoutBatchId ?? $senderBatchId,
            'count' => $batch->count(),
            'amount_cents' => (int) $batch->sum('amount_cents'),
        ];
    }

    /**
     * Réconciliation : interroge PayPal et met les statuts à jour.
     *
     * C'est ce qui rattrape un webhook perdu ou un appel de création dont on
     * n'a jamais vu la réponse.
     */
    public function syncBatch(string $batchId): int
    {
        try {
            $response = $this->paypal->getBatch($batchId);
        } catch (PayPalException $exception) {
            // Lot inconnu de PayPal : la création n'a jamais abouti, les
            // retraits redeviennent payables.
            $reverted = Payout::where('paypal_batch_id', $batchId)
                ->where('status', PayoutStatus::Processing)
                ->update([
                    'status' => PayoutStatus::Approved,
                    'paypal_batch_id' => null,
                    'failure_reason' => 'Lot introuvable chez PayPal, retrait remis en file.',
                ]);

            Log::warning('PayPal : lot introuvable, retraits remis en file', [
                'batch_id' => $batchId,
                'reverted' => $reverted,
                'error' => $exception->getMessage(),
            ]);

            return $reverted;
        }

        $updated = 0;

        foreach (data_get($response, 'items', []) as $item) {
            $payout = Payout::find((int) data_get($item, 'payout_item.sender_item_id'));

            if ($payout && $this->applyItemStatus($payout, $item)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Applique le statut d'un item PayPal à un retrait.
     *
     * @param  array<string, mixed>  $item
     */
    public function applyItemStatus(Payout $payout, array $item): bool
    {
        if ($payout->status === PayoutStatus::Paid) {
            return false;
        }

        $status = strtoupper((string) data_get($item, 'transaction_status', ''));

        $mapped = match ($status) {
            'SUCCESS' => PayoutStatus::Paid,
            'PENDING', 'ONHOLD' => PayoutStatus::Processing,
            // UNCLAIMED : le destinataire n'a pas de compte PayPal. PayPal
            // rendra l'argent sous 30 jours, donc on considère l'échec tout de
            // suite et le solde redevient disponible pour le clippeur.
            'FAILED', 'DENIED', 'BLOCKED', 'REFUNDED', 'RETURNED', 'REVERSED', 'UNCLAIMED' => PayoutStatus::Failed,
            default => null,
        };

        if (! $mapped) {
            return false;
        }

        $payout->forceFill([
            'status' => $mapped,
            'paypal_payout_item_id' => data_get($item, 'payout_item_id') ?? $payout->paypal_payout_item_id,
            'processed_at' => $mapped === PayoutStatus::Paid ? now() : $payout->processed_at,
            'failure_reason' => $mapped === PayoutStatus::Failed
                ? trim($status.' '.(string) data_get($item, 'errors.message'))
                : null,
        ])->save();

        return true;
    }
}
