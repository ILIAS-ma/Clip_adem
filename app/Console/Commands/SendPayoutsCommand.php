<?php

namespace App\Console\Commands;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Services\Payouts\PayoutService;
use Illuminate\Console\Command;

class SendPayoutsCommand extends Command
{
    protected $signature = 'payouts:send {--dry-run : Affiche le lot sans rien envoyer}';

    protected $description = 'Envoie les retraits validés à PayPal, par lot.';

    public function handle(PayoutService $payouts): int
    {
        $approved = Payout::where('status', PayoutStatus::Approved)->get();

        if ($approved->isEmpty()) {
            $this->info('Aucun retrait validé en attente.');

            return self::SUCCESS;
        }

        $total = (int) $approved->sum('amount_cents');

        $this->table(
            ['Retrait', 'Clippeur', 'Montant', 'PayPal'],
            $approved->map(fn (Payout $payout) => [
                '#'.$payout->getKey(),
                $payout->user?->name,
                number_format($payout->amount_cents / 100, 2, ',', ' ').' €',
                $payout->paypal_email,
            ])->all(),
        );

        $this->line(sprintf(
            '%d retrait(s) pour %s €.',
            $approved->count(),
            number_format($total / 100, 2, ',', ' '),
        ));

        if ($this->option('dry-run')) {
            $this->comment('Mode simulation : rien n\'a été envoyé.');

            return self::SUCCESS;
        }

        $result = $payouts->sendApproved($approved);

        if (! $result) {
            $this->info('Rien à envoyer.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Lot %s envoyé : %d versement(s), %s €.',
            $result['batch_id'],
            $result['count'],
            number_format($result['amount_cents'] / 100, 2, ',', ' '),
        ));

        $this->comment('Les statuts définitifs arriveront par webhook, ou via `php artisan payouts:sync`.');

        return self::SUCCESS;
    }
}
