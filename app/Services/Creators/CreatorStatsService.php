<?php

namespace App\Services\Creators;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Models\BudgetTransaction;
use App\Models\Clip;
use App\Models\Creator;
use Illuminate\Support\Collection;

/**
 * Les chiffres d'un créateur, en langage de créateur.
 *
 * Un créateur n'a pas à savoir ce qu'est un grand livre, ni pourquoi
 * `spent_cents` est un cache. Il pose trois questions : combien de vues, combien
 * ça m'a coûté, est-ce que ça marche. Ce service répond à ces trois-là, et rien
 * d'autre.
 *
 * Tout est lu depuis `campaign_budget_transactions`, jamais depuis les
 * compteurs : c'est la seule table auditable, et c'est celle qui reflète
 * immédiatement une invalidation pour vues achetées.
 */
class CreatorStatsService
{
    /**
     * Le résumé affiché en haut de l'espace créateur.
     *
     * @return array<string, mixed>
     */
    public function summary(Creator $creator): array
    {
        $campaignIds = $creator->campaigns()->pluck('id');

        $engaged = (int) $creator->campaigns()
            ->whereNotIn('status', [CampaignStatus::Draft->value, CampaignStatus::Archived->value])
            ->sum('budget_total_cents');

        $ledger = BudgetTransaction::whereIn('campaign_id', $campaignIds)
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as cents')
            ->selectRaw('COALESCE(SUM(views_delta), 0) as views')
            ->first();

        $spent = (int) $ledger->cents;
        $views = (int) $ledger->views;

        return [
            'views' => $views,
            'spent_cents' => $spent,
            'engaged_cents' => $engaged,
            'remaining_cents' => max(0, $engaged - $spent),

            // Le seul indicateur de rendement qui ait un sens : ce qui a
            // réellement été payé pour 1000 vues. À comparer au CPM annoncé.
            'real_cpm_cents' => $views > 0 ? intdiv($spent * 1000, $views) : null,

            'clips' => Clip::whereIn('campaign_id', $campaignIds)
                ->where('status', ClipStatus::Approved->value)
                ->count(),

            'clippers' => Clip::whereIn('campaign_id', $campaignIds)
                ->where('status', ClipStatus::Approved->value)
                ->distinct()
                ->count('user_id'),

            'active_campaigns' => $creator->campaigns()
                ->where('status', CampaignStatus::Active->value)
                ->count(),
        ];
    }

    /**
     * Vues et dépenses jour par jour, zéros compris.
     *
     * Sans les jours vides, un graphique laisse croire à une progression
     * continue entre deux pics éloignés de trois semaines.
     *
     * @return Collection<string, array{views: int, cents: int}>
     */
    public function daily(Creator $creator, int $days = 30): Collection
    {
        $rows = BudgetTransaction::query()
            ->whereIn('campaign_id', $creator->campaigns()->select('id'))
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COALESCE(SUM(views_delta), 0) as views')
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as cents')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        return collect(range($days - 1, 0))->mapWithKeys(function (int $offset) use ($rows) {
            $day = now()->subDays($offset)->format('Y-m-d');
            $row = $rows->get($day);

            return [$day => [
                'views' => (int) ($row->views ?? 0),
                'cents' => (int) ($row->cents ?? 0),
            ]];
        });
    }

    /**
     * Les clips qui ont réellement porté la campagne.
     *
     * Classés sur les vues rémunérées, pas sur les vues brutes : un clip dont
     * les vues ont été refusées n'a rien apporté au créateur.
     *
     * @return Collection<int, Clip>
     */
    public function topClips(Creator $creator, int $limit = 5): Collection
    {
        return Clip::query()
            ->whereIn('campaign_id', $creator->campaigns()->select('id'))
            ->where('status', ClipStatus::Approved->value)
            ->with(['campaign', 'user'])
            ->orderByDesc('paid_views')
            ->limit($limit)
            ->get();
    }

    /**
     * Une phrase qui dit où en est le créateur, sans chiffre à interpréter.
     *
     * C'est la seule ligne que beaucoup liront réellement.
     */
    public function headline(array $summary): string
    {
        if ($summary['active_campaigns'] === 0 && $summary['views'] === 0) {
            return 'Aucune campagne lancée pour le moment.';
        }

        if ($summary['views'] === 0) {
            return 'Campagne lancée, les premiers clips arrivent.';
        }

        return sprintf(
            '%s vues générées par %d clip%s de %d clippeur%s.',
            number_format($summary['views'], 0, ',', ' '),
            $summary['clips'],
            $summary['clips'] > 1 ? 's' : '',
            $summary['clippers'],
            $summary['clippers'] > 1 ? 's' : '',
        );
    }
}
