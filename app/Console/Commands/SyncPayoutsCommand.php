<?php

namespace App\Console\Commands;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Services\Payouts\PayoutService;
use Illuminate\Console\Command;

/**
 * Filet de sécurité des versements.
 *
 * Les webhooks PayPal arrivent en double, dans le désordre, ou pas du tout.
 * Cette commande interroge PayPal pour chaque lot encore en vol et tranche.
 * À planifier toutes les heures.
 */
class SyncPayoutsCommand extends Command
{
    protected $signature = 'payouts:sync {batch? : Identifiant de lot PayPal, sinon tous les lots en cours}';

    protected $description = 'Réconcilie les retraits en cours avec PayPal.';

    public function handle(PayoutService $payouts): int
    {
        $batches = $this->argument('batch')
            ? [$this->argument('batch')]
            : Payout::where('status', PayoutStatus::Processing)
                ->whereNotNull('paypal_batch_id')
                ->distinct()
                ->pluck('paypal_batch_id')
                ->all();

        if (empty($batches)) {
            $this->info('Aucun lot en cours.');

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($batches as $batchId) {
            $count = $payouts->syncBatch($batchId);
            $updated += $count;

            $this->line("Lot {$batchId} : {$count} retrait(s) mis à jour.");
        }

        $this->info("{$updated} retrait(s) réconcilié(s) sur ".count($batches).' lot(s).');

        return self::SUCCESS;
    }
}
