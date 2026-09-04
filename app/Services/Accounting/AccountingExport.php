<?php

namespace App\Services\Accounting;

use App\Enums\PayoutStatus;
use App\Models\BudgetTransaction;
use App\Models\Payout;
use Carbon\CarbonInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports comptables.
 *
 * Deux journaux distincts, jamais fusionnés : les dépenses de campagne (quand
 * le budget est consommé) et les versements (quand l'argent part). Les
 * additionner donnerait un double comptage, puisqu'un euro dépensé est versé
 * plus tard, parfois sur un autre exercice.
 *
 * Format pensé pour un tableur français : séparateur point-virgule, virgule
 * décimale, BOM UTF-8.
 */
class AccountingExport
{
    public const SPENDINGS = 'depenses';

    public const PAYOUTS = 'versements';

    /** Réponse HTTP téléchargeable, pour le back-office. */
    public function download(string $journal, ?CarbonInterface $from = null, ?CarbonInterface $to = null): StreamedResponse
    {
        [$filename, $headers, $rows] = $this->journal($journal, $from, $to);

        return response()->streamDownload(
            fn () => $this->write(fopen('php://output', 'w'), $headers, $rows),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * Écriture sur disque, pour la commande artisan et les exports planifiés.
     *
     * @return array{path: string, rows: int}
     */
    public function toFile(string $journal, ?string $path = null, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        [$filename, $headers, $rows] = $this->journal($journal, $from, $to);

        $path ??= storage_path('app/exports/'.$filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $handle = fopen($path, 'w');
        $count = $this->write($handle, $headers, $rows);

        return ['path' => $path, 'rows' => $count];
    }

    /**
     * Contrôle de cohérence lisible par un comptable.
     *
     * @return array{spent_cents: int, paid_cents: int, owed_cents: int}
     */
    public function reconciliation(): array
    {
        $spent = (int) BudgetTransaction::sum('amount_cents');
        $paid = (int) Payout::where('status', PayoutStatus::Paid)->sum('amount_cents');

        return [
            'spent_cents' => $spent,
            'paid_cents' => $paid,
            // Ce que la plateforme doit encore aux clippeurs : gains acquis
            // moins versements réellement partis.
            'owed_cents' => $spent - $paid,
        ];
    }

    /** @return array{0: string, 1: array<int, string>, 2: callable(): iterable} */
    protected function journal(string $journal, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        return match ($journal) {
            self::SPENDINGS => [
                $this->filename(self::SPENDINGS, $from, $to),
                ['Date', 'Type', 'Créateur', 'Campagne', 'Clip', 'Clippeur', 'Vues', 'Montant EUR', 'Solde campagne EUR', 'Référence'],
                fn () => $this->spendingRows($from, $to),
            ],
            self::PAYOUTS => [
                $this->filename(self::PAYOUTS, $from, $to),
                ['Demandé le', 'Payé le', 'Statut', 'Clippeur', 'E-mail PayPal', 'Montant EUR', 'Devise', 'Lot PayPal', 'Item PayPal', "Motif d'échec"],
                fn () => $this->payoutRows($from, $to),
            ],
            default => throw new \InvalidArgumentException(
                "Journal inconnu : {$journal}. Attendu : ".self::SPENDINGS.' ou '.self::PAYOUTS.'.'
            ),
        };
    }

    protected function spendingRows(?CarbonInterface $from, ?CarbonInterface $to): iterable
    {
        $query = BudgetTransaction::query()
            ->with(['campaign.creator', 'clip', 'user'])
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->orderBy('created_at');

        foreach ($query->lazy(500) as $transaction) {
            yield [
                $transaction->created_at->format('d/m/Y H:i'),
                $transaction->type->label(),
                $transaction->campaign?->creator?->name,
                $transaction->campaign?->title,
                $transaction->clip?->external_post_id,
                $transaction->user?->name,
                $transaction->views_delta,
                $this->amount($transaction->amount_cents),
                $this->amount($transaction->balance_after_cents),
                $transaction->idempotency_key,
            ];
        }
    }

    protected function payoutRows(?CarbonInterface $from, ?CarbonInterface $to): iterable
    {
        $query = Payout::query()
            ->with('user')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->orderBy('created_at');

        foreach ($query->lazy(500) as $payout) {
            yield [
                $payout->requested_at?->format('d/m/Y H:i'),
                $payout->processed_at?->format('d/m/Y H:i'),
                $payout->status->label(),
                $payout->user?->name,
                $payout->destinationLabel(),
                $this->amount($payout->amount_cents),
                $payout->currency,
                $payout->paypal_batch_id,
                $payout->paypal_payout_item_id,
                $payout->failure_reason,
            ];
        }
    }

    /**
     * @param  resource  $handle
     * @param  array<int, string>  $headers
     * @param  callable(): iterable  $rows
     */
    protected function write($handle, array $headers, callable $rows): int
    {
        // BOM UTF-8 : sans lui, Excel massacre les accents.
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, $headers, ';');

        $count = 0;

        foreach ($rows() as $row) {
            fputcsv($handle, $row, ';');
            $count++;
        }

        fclose($handle);

        return $count;
    }

    protected function filename(string $prefix, ?CarbonInterface $from, ?CarbonInterface $to): string
    {
        $range = $from || $to
            ? '-'.($from?->format('Ymd') ?? 'debut').'-'.($to?->format('Ymd') ?? 'fin')
            : '-'.now()->format('Ymd');

        return "clip-adem-{$prefix}{$range}.csv";
    }

    protected function amount(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }
}
