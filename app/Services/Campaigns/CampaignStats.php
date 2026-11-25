<?php

namespace App\Services\Campaigns;

use App\Enums\ClipStatus;
use App\Enums\ContestMetric;
use App\Enums\ModerationAction;
use App\Models\Campaign;
use App\Models\CampaignParticipation;
use App\Models\Clip;
use App\Models\ModerationLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Toutes les statistiques d'une campagne, et leur export dans un classeur Excel.
 *
 * Une seule source pour l'aperçu de la page d'administration et pour le fichier : ce qu'on
 * lit à l'écran est ce qu'on retrouve dans le tableau. Les classements viennent de
 * ContestService, les mêmes chiffres que ceux des clippeurs.
 */
class CampaignStats
{
    /** Actions de modération qui font l'historique d'une campagne. */
    protected const MODERATION = [
        ModerationAction::ClipRejected,
        ModerationAction::ClipInvalidated,
        ModerationAction::ClipOutsideWindow,
        ModerationAction::ClipSuspectedReupload,
        ModerationAction::ClipNotOwned,
        ModerationAction::ClipDisappeared,
    ];

    public function __construct(
        protected ContestService $contest,
    ) {}

    /**
     * @return array{
     *     kpis: list<array{label: string, value: string}>,
     *     summary: list<array{0: string, 1: string|int|float|null}>,
     *     boards: array<string, list<array<string, mixed>>>,
     *     clippers: list<array<string, mixed>>,
     *     clips: list<array<string, mixed>>,
     *     days: list<array<string, mixed>>,
     *     moderation: list<array<string, mixed>>,
     * }
     */
    public function gather(Campaign $campaign): array
    {
        $clips = Clip::query()
            ->where('campaign_id', $campaign->getKey())
            ->with(['user:id,name,pseudo,email', 'socialAccount:id,handle'])
            ->orderBy('submitted_at')
            ->get();

        $logs = ModerationLog::query()
            ->where('subject_type', (new Clip)->getMorphClass())
            ->whereIn('subject_id', $clips->pluck('id'))
            ->whereIn('action', array_map(fn (ModerationAction $a) => $a->value, self::MODERATION))
            ->orderBy('created_at')
            ->get();

        $flags = $logs
            ->filter(fn (ModerationLog $log) => in_array($log->action, [ModerationAction::ClipOutsideWindow, ModerationAction::ClipSuspectedReupload], true))
            ->groupBy('subject_id')
            ->map(fn (Collection $group) => $group->map(fn (ModerationLog $log) => $log->action->label())->unique()->implode(' · '));

        $counted = $clips->filter(fn (Clip $clip) => $clip->status === ClipStatus::Approved);

        return [
            'kpis' => $this->kpis($campaign, $clips, $counted, $flags),
            'summary' => $this->summary($campaign, $clips, $counted, $flags),
            'boards' => $campaign->isContest() ? $this->boards($campaign) : [],
            'clippers' => $this->clippers($clips, $flags),
            'clips' => $this->clipRows($clips, $flags),
            'days' => $this->days($clips),
            'moderation' => $this->moderation($logs, $clips),
        ];
    }

    /** Écrit le classeur dans un fichier temporaire et renvoie son chemin. */
    public function export(Campaign $campaign): string
    {
        $data = $this->gather($campaign);

        $options = new Options;
        $options->setColumnWidth(26, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14);

        $path = tempnam(sys_get_temp_dir(), 'stats-').'.xlsx';
        $writer = new Writer($options);
        $writer->openToFile($path);

        $header = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor(Color::rgb(204, 63, 69));
        $bold = (new Style)->setFontBold();

        $writer->getCurrentSheet()->setName('Résumé');
        $writer->addRow(Row::fromValues(['Statistiques de la campagne', $campaign->title], $bold));
        $writer->addRow(Row::fromValues([]));
        foreach ($data['summary'] as [$label, $value]) {
            $writer->addRow(Row::fromValues([$label, $value ?? '']));
        }

        $sheet = fn (string $name) => $writer->addNewSheetAndMakeItCurrent()->setName($name);

        foreach ($data['boards'] as $metricValue => $rows) {
            $metric = ContestMetric::from($metricValue);
            $sheet('Classement '.($metric === ContestMetric::Views ? 'vues' : 'clips'));
            $writer->addRow(Row::fromValues(['Place', 'Clippeur', 'Pseudo', $metric === ContestMetric::Views ? 'Vues' : 'Clips comptés', 'Vues', 'Clips', 'Récompense'], $header));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues([$row['rank'], $row['name'], $row['pseudo'], $row['score'], $row['views'], $row['clips'], $row['reward']]));
            }
        }

        $sheet('Clippeurs');
        $writer->addRow(Row::fromValues(['Clippeur', 'Pseudo', 'E-mail', 'Clips soumis', 'Validés', 'Refusés', 'Invalidés', 'Vues (validés)', 'Vues moyennes / clip', 'Meilleur clip (vues)', 'Gains (€)', 'Premier clip', 'Dernier clip', 'Signalements'], $header));
        foreach ($data['clippers'] as $row) {
            $writer->addRow(Row::fromValues([
                $row['name'], $row['pseudo'], $row['email'], $row['submitted'], $row['approved'], $row['rejected'], $row['invalidated'],
                $row['views'], $row['average'], $row['best'], $row['earned'], $row['first'], $row['last'], $row['flags'],
            ]));
        }

        $sheet('Clips');
        $writer->addRow(Row::fromValues(['N°', 'Clippeur', 'Pseudo', 'Compte TikTok', 'Plateforme', 'Lien', 'Publié le', 'Soumis le', 'Statut', 'Motif de refus', 'Vues', 'Conformité', 'Signalement', 'Gains (€)'], $header));
        foreach ($data['clips'] as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }

        $sheet('Par jour');
        $writer->addRow(Row::fromValues(['Jour', 'Clips soumis', 'Clippeurs actifs', 'Vues actuelles de ces clips'], $header));
        foreach ($data['days'] as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }

        $sheet('Modération');
        $writer->addRow(Row::fromValues(['Date', 'Clippeur', 'Clip n°', 'Action', 'Motif'], $header));
        foreach ($data['moderation'] as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }

        $writer->close();

        return $path;
    }

    /**
     * Le tableau « une ligne par clippeur », en deux versions.
     *
     * Version équipe : pseudo et numéro, comptes, clips, vues, moyen de paiement et coordonnées : tout
     * ce qu'il faut pour payer. Version client : seulement les comptes, les clips et les vues. Les
     * coordonnées de paiement et le numéro de téléphone ne sortent jamais de l'équipe.
     *
     * @return string chemin du fichier temporaire
     */
    public function payoutSheet(Campaign $campaign, bool $forClient = false): string
    {
        $clips = Clip::query()
            ->where('campaign_id', $campaign->getKey())
            ->where('status', ClipStatus::Approved)
            ->with(['user', 'socialAccount:id,handle'])
            ->get();

        $rows = $clips->groupBy('user_id')->map(function (Collection $group) {
            $user = $group->first()->user;

            return [
                'user' => $user,
                'accounts' => $group->pluck('socialAccount.handle')->filter()->map(fn ($h) => ltrim((string) $h, '@'))->unique()->implode(', '),
                'clips' => $group->count(),
                'views' => (int) $group->sum('views_total'),
            ];
        })->sortByDesc('views')->values();

        // Le classement et les gains, pour l'équipe : la place officielle sur un classement, sinon l'ordre des vues.
        $earned = Clip::where('campaign_id', $campaign->getKey())
            ->selectRaw('user_id, SUM(earned_cents) as cents')->groupBy('user_id')->pluck('cents', 'user_id');
        $ranks = [];
        $prizes = [];

        if ($campaign->isContest()) {
            foreach (ContestMetric::cases() as $metric) {
                foreach ($this->contest->standings($campaign, null, $metric) as $standing) {
                    if ($metric === ContestMetric::Views) {
                        $ranks[$standing->user_id] = $standing->rank;
                    }

                    if ($reward = $campaign->rewardFor($standing->rank, $metric)) {
                        $prizes[$standing->user_id][] = $reward->display();
                    }
                }
            }
        } else {
            foreach ($rows as $i => $row) {
                $ranks[$row['user']->getKey()] = $i + 1;
            }
        }

        $now = now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s');

        $options = new Options;
        $options->setColumnWidth(20, 1);
        $options->setColumnWidth($forClient ? 34 : 30, 2);
        $options->setColumnWidth(30, 3);
        $options->setColumnWidth(26, 4, 5, 6);
        $options->setColumnWidth(44, 7);
        $options->setColumnWidth(16, 8, 9);
        $options->setColumnWidth(28, 10);

        $path = tempnam(sys_get_temp_dir(), 'tableau-').'.xlsx';
        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Form_Responses');

        $header = (new Style)->setFontBold()->setFontColor(Color::WHITE)->setBackgroundColor(Color::rgb(90, 50, 130));

        if ($forClient) {
            $writer->addRow(Row::fromValues(['Horodateur', '@ DU COMPTES/DES COMPTES', 'NOMBRE DE CLIPS POSTÉ', 'NOMBRE DE VUES COMBINÉ'], $header));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues([$now, $row['accounts'], $row['clips'], $row['views']]));
            }

            // Les vues cumulées et le nombre de clips de toute la campagne, en bas.
            $writer->addRow(Row::fromValues(['TOTAL', $rows->count().' clippeur(s)', $rows->sum('clips'), $rows->sum('views')], (new Style)->setFontBold()));
        } else {
            $writer->addRow(Row::fromValues(['Horodateur', 'PSEUDO DISCORD + NUM', '@ DU COMPTES/DES COMPTES', 'NOMBRE DE CLIPS POSTÉ', 'NOMBRE DE VUES COMBINÉ', 'MOYEN DE PAIEMENT', '(PAYPAL, RIB, AUTRE) À STIPULER', 'CLASSEMENT', 'GAINS (€)', 'PRIX DU CLASSEMENT'], $header));

            foreach ($rows as $row) {
                /** @var User $user */
                $user = $row['user'];

                $writer->addRow(Row::fromValues([
                    $now,
                    trim(($user->pseudo ?: $user->name).' '.$user->phone),
                    $row['accounts'],
                    $row['clips'],
                    $row['views'],
                    $user->payoutDestination() ? $user->payoutMethod()->label() : '',
                    $user->payoutDestination() ?? '',
                    $ranks[$user->getKey()] ?? '',
                    ((int) ($earned[$user->getKey()] ?? 0)) / 100,
                    implode(' + ', $prizes[$user->getKey()] ?? []),
                ]));
            }

            $writer->addRow(Row::fromValues([
                'TOTAL', $rows->count().' clippeur(s)', '', $rows->sum('clips'), $rows->sum('views'), '', '', '',
                $rows->sum(fn (array $row) => (int) ($earned[$row['user']->getKey()] ?? 0)) / 100, '',
            ], (new Style)->setFontBold()));
        }

        $writer->close();

        return $path;
    }

    public function payoutSheetFilename(Campaign $campaign, bool $forClient): string
    {
        return ($forClient ? 'client-' : 'equipe-').(Str::slug($campaign->title) ?: 'campagne-'.$campaign->getKey()).'-'.now()->format('Y-m-d').'.xlsx';
    }

    public function filename(Campaign $campaign): string
    {
        // Le titre, pas l'identifiant technique : « DJIBRIL94 x LIGUE1 » se lit, son slug non.
        return 'stats-'.(Str::slug($campaign->title) ?: 'campagne-'.$campaign->getKey()).'-'.now()->format('Y-m-d').'.xlsx';
    }

    // ------------------------------------------------------------------

    /** @return list<array{label: string, value: string}> */
    protected function kpis(Campaign $campaign, Collection $clips, Collection $counted, Collection $flags): array
    {
        $views = $counted->sum('views_total');

        return [
            ['label' => 'Clippeurs', 'value' => $this->number($clips->pluck('user_id')->unique()->count())],
            ['label' => 'Clips', 'value' => $this->number($counted->count())],
            ['label' => 'Vues', 'value' => $this->number($views)],
            ['label' => 'Vues par clip', 'value' => $this->number($counted->count() ? intdiv($views, $counted->count()) : 0)],
        ];
    }

    /** @return list<array{0: string, 1: string|int|float|null}> */
    protected function summary(Campaign $campaign, Collection $clips, Collection $counted, Collection $flags): array
    {
        $views = $counted->sum('views_total');
        $best = $counted->sortByDesc('views_total')->first();

        $rows = [
            ['Créateur', $campaign->creator?->name],
            ['Type', $campaign->isContest() ? 'Classement' : 'Paiement aux vues'],
            ['Statut', $campaign->status->label()],
            ['Début', $this->date($campaign->starts_at)],
            ['Fin', $this->date($campaign->ends_at)],
            ['Hashtags obligatoires', implode(' ', $campaign->required_hashtags ?? [])],
            ['Limite par envoi', $campaign->submissionBatchLimit() ?? 'aucune'],
            ['Limite par heure', $campaign->hourlySubmissionLimit() ?? 'aucune'],
            ['', ''],
            ['Inscrits à la campagne', CampaignParticipation::where('campaign_id', $campaign->getKey())->count()],
            ['Clippeurs ayant soumis', $clips->pluck('user_id')->unique()->count()],
            ['Clips soumis', $clips->count()],
            ['Clips validés', $counted->count()],
            ['Clips refusés', $clips->where('status', ClipStatus::Rejected)->count()],
            ['Clips invalidés', $clips->where('status', ClipStatus::Invalidated)->count()],
            ['Clips signalés', $flags->count()],
            ['Vues (clips validés)', $views],
            ['Vues moyennes par clip', $counted->count() ? intdiv($views, $counted->count()) : 0],
            ['Clip le plus vu', $best ? $best->views_total.' vues ('.($best->user?->name ?? '?').')' : ''],
        ];

        $rows[] = ['', ''];

        if ($campaign->isContest()) {
            $rows[] = ['Récompenses en argent (€)', $campaign->prizePoolCents() / 100];
            $rows[] = ['Vues minimum pour qu\'un clip compte (classement des clips)', $campaign->postMinViews()];
        } else {
            $rows[] = ['Budget total (€)', $campaign->budget_total_cents / 100];
            $rows[] = ['Dépensé (€)', $campaign->spent_cents / 100];
            $rows[] = ['Restant (€)', $campaign->remainingCents() / 100];
        }

        $rows[] = ['', ''];
        $rows[] = ['Export généré le', $this->date(now())];

        return $rows;
    }

    /** @return array<string, list<array<string, mixed>>> */
    protected function boards(Campaign $campaign): array
    {
        $boards = [];

        foreach (ContestMetric::cases() as $metric) {
            $standings = $this->contest->standings($campaign, null, $metric);
            $pseudos = User::whereIn('id', $standings->pluck('user_id'))->pluck('pseudo', 'id');

            $boards[$metric->value] = $standings->map(fn (object $row) => [
                'rank' => $row->rank,
                'name' => $row->name,
                'pseudo' => $pseudos[$row->user_id] ?? '',
                'score' => (int) $row->score,
                'views' => (int) $row->views,
                'clips' => (int) $row->clips,
                'reward' => $campaign->rewardFor($row->rank, $metric)?->display() ?? '',
            ])->values()->all();
        }

        return $boards;
    }

    /** @return list<array<string, mixed>> */
    protected function clippers(Collection $clips, Collection $flags): array
    {
        return $clips->groupBy('user_id')->map(function (Collection $group) use ($flags) {
            $user = $group->first()->user;
            $approved = $group->filter(fn (Clip $c) => $c->status === ClipStatus::Approved);
            $views = $approved->sum('views_total');

            return [
                'name' => $user?->name ?? '?',
                'pseudo' => $user?->pseudo ?? '',
                'email' => $user?->email ?? '',
                'submitted' => $group->count(),
                'approved' => $approved->count(),
                'rejected' => $group->where('status', ClipStatus::Rejected)->count(),
                'invalidated' => $group->where('status', ClipStatus::Invalidated)->count(),
                'views' => (int) $views,
                'average' => $approved->count() ? intdiv($views, $approved->count()) : 0,
                'best' => (int) $approved->max('views_total'),
                'earned' => round($group->sum(fn (Clip $c) => $c->earned_cents + $c->bonus_cents) / 100, 2),
                'first' => $this->date($group->min('submitted_at')),
                'last' => $this->date($group->max('submitted_at')),
                'flags' => $group->filter(fn (Clip $c) => $flags->has($c->getKey()))->count(),
            ];
        })->sortByDesc('views')->values()->all();
    }

    /** @return list<array<string, mixed>> */
    protected function clipRows(Collection $clips, Collection $flags): array
    {
        return $clips->map(fn (Clip $clip) => [
            'id' => $clip->getKey(),
            'name' => $clip->user?->name ?? '?',
            'pseudo' => $clip->user?->pseudo ?? '',
            'account' => $clip->socialAccount?->handle ? '@'.$clip->socialAccount->handle : '',
            'platform' => $clip->platform->label(),
            'url' => $clip->url,
            'posted' => $this->date($clip->posted_at),
            'submitted' => $this->date($clip->submitted_at),
            'status' => $clip->status->label(),
            'reason' => (string) $clip->rejection_reason,
            'views' => (int) $clip->views_total,
            'compliance' => match ($clip->compliance_status) {
                'passed' => 'Conforme',
                'failed' => 'Brief non respecté',
                default => 'Pas vérifié',
            },
            'flag' => (string) ($flags[$clip->getKey()] ?? ''),
            'earned' => round(($clip->earned_cents + $clip->bonus_cents) / 100, 2),
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    protected function days(Collection $clips): array
    {
        return $clips
            ->filter(fn (Clip $clip) => $clip->submitted_at !== null)
            ->groupBy(fn (Clip $clip) => $clip->submitted_at->timezone(config('app.timezone'))->format('Y-m-d'))
            ->sortKeys()
            ->map(fn (Collection $group, string $day) => [
                'day' => Carbon::parse($day)->format('d/m/Y'),
                'clips' => $group->count(),
                'clippers' => $group->pluck('user_id')->unique()->count(),
                'views' => (int) $group->sum('views_total'),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function moderation(Collection $logs, Collection $clips): array
    {
        $byId = $clips->keyBy('id');

        return $logs->map(fn (ModerationLog $log) => [
            'date' => $this->date($log->created_at),
            'name' => $byId[$log->subject_id]?->user?->name ?? '?',
            'clip' => $log->subject_id,
            'action' => $log->action->label(),
            'reason' => (string) $log->reason,
        ])->values()->all();
    }

    protected function date(?CarbonInterface $date): string
    {
        return $date?->copy()->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '';
    }

    protected function number(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }
}
