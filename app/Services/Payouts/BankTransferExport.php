<?php

namespace App\Services\Payouts;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Support\Iban;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Liste de virements à exécuter depuis la banque.
 *
 * PayPal part tout seul ; un virement SEPA, non. Ce fichier est ce qu'un
 * administrateur ouvre à côté de son interface bancaire pour saisir les
 * virements, puis vient pointer un par un dans le back-office.
 *
 * Il contient des IBAN en clair — c'est sa raison d'être — donc il n'est
 * accessible qu'au super administrateur, il n'est jamais écrit sur disque, et
 * il n'inclut que les retraits déjà validés.
 */
class BankTransferExport
{
    /** @var list<string> */
    private const HEADERS = [
        'Retrait', 'Clippeur', 'Titulaire', 'IBAN', 'BIC', 'Montant EUR', 'Devise', 'Libellé', 'Demandé le',
    ];

    public function download(): StreamedResponse
    {
        $payouts = $this->pending();

        return response()->streamDownload(
            fn () => $this->write(fopen('php://output', 'w'), $payouts),
            'virements-'.now()->format('Y-m-d-Hi').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /** Retraits en virement bancaire, validés, pas encore exécutés. */
    public function pending()
    {
        return Payout::query()
            ->with('user')
            ->where('method', PayoutMethod::BankTransfer->value)
            ->where('status', PayoutStatus::Approved)
            ->orderBy('requested_at')
            ->get();
    }

    /** @param  Collection<int, Payout>  $payouts */
    private function write($handle, $payouts): int
    {
        // BOM : sans lui, Excel affiche les accents en mojibake.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::HEADERS, ';');

        foreach ($payouts as $payout) {
            $user = $payout->user;

            fputcsv($handle, [
                '#'.$payout->getKey(),
                $user?->displayName(),
                $user?->account_holder,
                // Formaté par groupes de quatre : c'est ainsi qu'on le relit
                // pour vérifier une saisie.
                Iban::format($user?->iban),
                $user?->bic,
                number_format($payout->amount_cents / 100, 2, ',', ''),
                $payout->currency,
                // Le libellé permet au clippeur de reconnaître le virement sur
                // son relevé, et à nous de le rapprocher.
                'iPlant Clip retrait '.$payout->getKey(),
                $payout->requested_at?->format('d/m/Y'),
            ], ';');
        }

        return $payouts->count();
    }
}
