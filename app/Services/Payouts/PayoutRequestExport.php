<?php

namespace App\Services\Payouts;

use App\Enums\ClipStatus;
use App\Enums\PayoutMethod;
use App\Models\Clip;
use App\Models\Payout;
use App\Models\SocialAccount;
use App\Support\Iban;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Le tableau des demandes de retrait, dans la forme du formulaire Google.
 *
 * Avant la plateforme, les clippeurs remplissaient un formulaire par période de
 * paiement et déclaraient eux-mêmes leurs comptes, leurs clips et leurs vues.
 * L'équipe payait en lisant cette feuille, ligne par ligne. Ce fichier rend la
 * même feuille, mais remplie par la base plutôt que par les clippeurs : mêmes
 * colonnes, même ordre, donc la méthode de paiement n'a pas à changer.
 *
 * Une ligne = une demande de retrait, triée par date de demande, comme
 * l'horodateur du formulaire.
 *
 * Format tableur français : séparateur point-virgule, virgule décimale, BOM
 * UTF-8 — sans quoi Excel casse les accents.
 */
class PayoutRequestExport
{
    /** @var list<string> */
    private const HEADERS = [
        'Horodateur',
        'Pseudo + numéro',
        '@ du compte / des comptes',
        'Nombre de clips posté',
        'Nombre de vues combiné',
        'Moyen de paiement',
        '(PayPal, RIB, autre) à stipuler',
        // Deux colonnes que le formulaire n'avait pas : le clippeur ne
        // déclarait pas son montant, c'est l'équipe qui le calculait à la
        // main. Payer sans les avoir sous les yeux n'aurait aucun sens.
        'Montant EUR',
        'Statut',
    ];

    /**
     * Réponse téléchargeable, pour le back-office.
     *
     * $withFullIban : l'IBAN en clair ne sort que pour un super administrateur,
     * comme dans le fichier des virements. Un modérateur reçoit le numéro
     * masqué — assez pour pointer une ligne, pas pour exécuter un virement.
     */
    public function download(
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        bool $withFullIban = false,
    ): StreamedResponse {
        return response()->streamDownload(
            fn () => $this->write(fopen('php://output', 'w'), $from, $to, $withFullIban),
            $this->filename($from, $to),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * Écriture sur disque, pour une commande artisan ou un test.
     *
     * @return array{path: string, rows: int}
     */
    public function toFile(
        ?string $path = null,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        bool $withFullIban = false,
    ): array {
        $path ??= storage_path('app/exports/'.$this->filename($from, $to));

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $rows = $this->write(fopen($path, 'w'), $from, $to, $withFullIban);

        return ['path' => $path, 'rows' => $rows];
    }

    /** Les demandes de la période, dans l'ordre où elles sont arrivées. */
    public function payouts(?CarbonInterface $from = null, ?CarbonInterface $to = null)
    {
        return Payout::query()
            ->with('user')
            // Sur la date de demande, pas de création : c'est l'horodateur du
            // formulaire, et c'est lui qui découpe une période de paiement.
            ->when($from, fn ($query) => $query->where('requested_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('requested_at', '<=', $to))
            ->orderBy('requested_at')
            ->get();
    }

    /** @param  resource  $handle */
    private function write($handle, ?CarbonInterface $from, ?CarbonInterface $to, bool $withFullIban): int
    {
        fwrite($handle, "\xEF\xBB\xBF");
        $this->ligne($handle, self::HEADERS);

        $payouts = $this->payouts($from, $to);
        $userIds = $payouts->pluck('user_id')->filter()->unique();

        // Deux requêtes groupées plutôt qu'une paire par ligne : une période
        // chargée fait des centaines de demandes.
        $handles = $this->handles($userIds);
        $activity = $this->activity($userIds, $from, $to);

        foreach ($payouts as $payout) {
            $stats = $activity[$payout->user_id] ?? ['clips' => 0, 'views' => 0];

            $this->ligne($handle, [
                $payout->requested_at?->format('d/m/Y H:i:s'),
                $this->identity($payout),
                $handles[$payout->user_id] ?? '',
                $stats['clips'],
                $stats['views'],
                $this->method($payout),
                $this->destination($payout, $withFullIban),
                number_format($payout->amount_cents / 100, 2, ',', ''),
                $payout->status->label(),
            ]);
        }

        fclose($handle);

        return $payouts->count();
    }

    /**
     * Une ligne du fichier.
     *
     * L'échappement est explicitement vide : depuis PHP 8.4, laisser fputcsv
     * choisir déclenche un avertissement, et l'antislash hérité de C n'a
     * jamais rien voulu dire pour un tableur.
     *
     * @param  resource  $handle
     * @param  array<int, mixed>  $champs
     */
    private function ligne($handle, array $champs): void
    {
        fputcsv($handle, $champs, ';', '"', '');
    }

    /**
     * « Pseudo + num » du formulaire.
     *
     * Le formulaire demandait le pseudo Discord ; la plateforme ne connaît que
     * le pseudo du compte, qui est celui sous lequel l'équipe reconnaît le
     * clippeur partout ailleurs.
     */
    private function identity(Payout $payout): string
    {
        $user = $payout->user;

        return collect([$user?->displayName(), $user?->phone])
            ->filter()
            ->implode(' + ');
    }

    /** « Paypal », « RIB » : les mots de la feuille, pas ceux du code. */
    private function method(Payout $payout): string
    {
        return $payout->payoutMethod() === PayoutMethod::PayPal ? 'Paypal' : 'RIB';
    }

    /** L'adresse PayPal, ou l'IBAN — en clair pour un super administrateur seulement. */
    private function destination(Payout $payout, bool $withFullIban): string
    {
        if ($payout->payoutMethod() === PayoutMethod::PayPal) {
            return (string) ($payout->paypal_email ?: $payout->destination);
        }

        if (! $withFullIban) {
            return $payout->destinationLabel();
        }

        /*
         * Le retrait ne garde que les derniers chiffres du RIB ; l'IBAN complet
         * vient du profil. Si le clippeur en a changé depuis sa demande, les
         * deux ne correspondent plus et sortir le nouveau ferait partir
         * l'argent ailleurs que là où il a été demandé.
         */
        $iban = $payout->user?->iban;
        $asked = substr(preg_replace('/[^0-9A-Za-z]/', '', (string) $payout->destination), -4);

        if (blank($iban) || ($asked !== '' && strcasecmp($asked, (string) Iban::last4($iban)) !== 0)) {
            return $payout->destinationLabel().' (RIB modifié depuis la demande)';
        }

        return collect([
            Iban::format($iban),
            $payout->user?->bic ? 'BIC '.$payout->user->bic : null,
            $payout->user?->account_holder,
        ])->filter()->implode(' · ');
    }

    /**
     * Les @ des comptes liés, comme le clippeur les écrivait lui-même.
     *
     * @param  Collection<int, int>  $userIds
     * @return array<int, string>
     */
    private function handles(Collection $userIds): array
    {
        if ($userIds->isEmpty()) {
            return [];
        }

        return SocialAccount::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('handle')
            ->orderBy('id')
            ->get(['user_id', 'handle'])
            ->groupBy('user_id')
            ->map(fn ($accounts) => $accounts
                ->pluck('handle')
                ->unique()
                ->map(fn (string $handle) => '@'.ltrim($handle, '@'))
                ->implode(' / '))
            ->all();
    }

    /**
     * Clips postés et vues cumulées sur la période.
     *
     * Les clips refusés ou invalidés sont exclus : ils ne rapportent rien, les
     * compter gonflerait une ligne qu'on relit justement pour vérifier qu'un
     * montant est plausible.
     *
     * @param  Collection<int, int>  $userIds
     * @return array<int, array{clips: int, views: int}>
     */
    private function activity(Collection $userIds, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        if ($userIds->isEmpty()) {
            return [];
        }

        return Clip::query()
            ->whereIn('user_id', $userIds)
            ->whereNotIn('status', [ClipStatus::Rejected->value, ClipStatus::Invalidated->value])
            /*
             * Un clip publié avant la période mais soumis pendant compte pour
             * la période : c'est sa soumission qui le rattache à un paiement.
             *
             * COALESCE parce qu'un clip importé ou repris à la main peut
             * n'avoir aucune date de soumission ; sans ce repli il
             * disparaîtrait de toutes les périodes à la fois.
             */
            ->when($from, fn ($query) => $query->whereRaw(
                'COALESCE(submitted_at, posted_at, created_at) >= ?', [$from]
            ))
            ->when($to, fn ($query) => $query->whereRaw(
                'COALESCE(submitted_at, posted_at, created_at) <= ?', [$to]
            ))
            ->selectRaw('user_id, COUNT(*) as clips, COALESCE(SUM(views_total), 0) as views')
            ->groupBy('user_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->user_id => ['clips' => (int) $row->clips, 'views' => (int) $row->views],
            ])
            ->all();
    }

    private function filename(?CarbonInterface $from, ?CarbonInterface $to): string
    {
        $range = $from || $to
            ? ($from?->format('d-m') ?? 'debut').'-a-'.($to?->format('d-m') ?? 'fin')
            : now()->format('Y-m-d');

        return "demandes-de-retrait-{$range}.csv";
    }
}
