<?php

namespace App\Services\Reporting;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\PayoutStatus;
use App\Models\BudgetTransaction;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\Creator;
use App\Models\Payout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agrégats du back-office.
 *
 * Les montants viennent du grand livre chaque fois que c'est possible : c'est
 * la seule table dont les chiffres sont reproductibles et auditables, alors
 * qu'un compteur dénormalisé peut avoir dérivé.
 */
class ReportingService
{
    /**
     * @return array{
     *     budget_engaged_cents: int, spent_cents: int, remaining_cents: int,
     *     paid_cents: int, owed_cents: int, views: int, clips: int,
     *     active_campaigns: int, real_cpm_cents: ?int
     * }
     */
    public function globalStats(): array
    {
        $engaged = (int) Campaign::whereNotIn('status', [CampaignStatus::Draft, CampaignStatus::Archived])
            ->sum('budget_total_cents');

        $spent = (int) BudgetTransaction::sum('amount_cents');
        $paid = (int) Payout::where('status', PayoutStatus::Paid)->sum('amount_cents');
        $views = (int) Clip::where('status', ClipStatus::Approved)->sum('views_total');

        return [
            'budget_engaged_cents' => $engaged,
            'spent_cents' => $spent,
            'remaining_cents' => max(0, $engaged - $spent),
            'paid_cents' => $paid,
            // Ce que la plateforme doit encore : gains acquis moins versements
            // réellement partis.
            'owed_cents' => $spent - $paid,
            'views' => $views,
            'clips' => Clip::where('status', ClipStatus::Approved)->count(),
            'active_campaigns' => Campaign::where('status', CampaignStatus::Active)->count(),
            'real_cpm_cents' => $views > 0 ? intdiv($spent * 1000, $views) : null,
        ];
    }

    /**
     * Dépenses et rendement par créateur.
     *
     * Le CPM réel est le seul indicateur de ROI qui ait du sens ici : il se
     * compare directement au CPM affiché sur les campagnes.
     *
     * @return Collection<int, object>
     */
    public function spendPerCreator(int $limit = 20): Collection
    {
        return Creator::query()
            ->select('creators.id', 'creators.name')
            ->selectRaw('COALESCE(SUM(campaigns.budget_total_cents), 0) as budget_cents')
            ->selectRaw('COALESCE(SUM(campaigns.spent_cents), 0) as spent_cents')
            ->selectRaw('COUNT(DISTINCT campaigns.id) as campaigns_count')
            ->leftJoin('campaigns', function ($join) {
                $join->on('campaigns.creator_id', '=', 'creators.id')
                    ->whereNull('campaigns.deleted_at');
            })
            ->whereNull('creators.deleted_at')
            ->groupBy('creators.id', 'creators.name')
            ->having('spent_cents', '>', 0)
            ->orderByDesc('spent_cents')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $views = (int) Clip::whereIn(
                    'campaign_id',
                    Campaign::where('creator_id', $row->id)->select('id'),
                )->sum('views_total');

                $row->views = $views;
                $row->real_cpm_cents = $views > 0 ? intdiv((int) $row->spent_cents * 1000, $views) : null;

                return $row;
            });
    }

    /**
     * Meilleurs clippeurs.
     *
     * Le taux d'invalidation compte autant que les gains : il révèle les
     * profils qui coûtent du temps de modération.
     *
     * @return Collection<int, object>
     */
    public function topClippers(int $limit = 10): Collection
    {
        return DB::table('clips')
            ->join('users', 'users.id', '=', 'clips.user_id')
            ->select('users.id', 'users.name', 'users.is_banned')
            ->selectRaw('SUM(clips.earned_cents) as earned_cents')
            ->selectRaw('SUM(CASE WHEN clips.status = ? THEN clips.views_total ELSE 0 END) as views', [ClipStatus::Approved->value])
            ->selectRaw('COUNT(*) as clips_count')
            ->selectRaw('SUM(CASE WHEN clips.status = ? THEN 1 ELSE 0 END) as invalidated_count', [ClipStatus::Invalidated->value])
            ->groupBy('users.id', 'users.name', 'users.is_banned')
            ->orderByDesc('earned_cents')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->earned_cents = (int) $row->earned_cents;
                $row->views = (int) $row->views;
                $row->invalidation_rate = $row->clips_count > 0
                    ? round($row->invalidated_count / $row->clips_count * 100, 1)
                    : 0.0;

                return $row;
            });
    }

    /**
     * Consommation quotidienne du budget, depuis le grand livre.
     *
     * @return Collection<string, int> Date (Y-m-d) => centimes
     */
    public function dailySpend(int $days = 30, ?Campaign $campaign = null): Collection
    {
        $rows = BudgetTransaction::query()
            ->when($campaign, fn ($q) => $q->where('campaign_id', $campaign->getKey()))
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('SUM(amount_cents) as cents')
            ->groupBy('day')
            ->pluck('cents', 'day');

        // Les jours sans dépense doivent apparaître à zéro, sinon la courbe
        // laisse croire à une consommation continue.
        return collect(range($days - 1, 0))
            ->mapWithKeys(function (int $offset) use ($rows) {
                $day = now()->subDays($offset)->format('Y-m-d');

                return [$day => (int) ($rows[$day] ?? 0)];
            });
    }

    /**
     * Répartition des dépenses par plateforme.
     *
     * @return Collection<int, object>
     */
    public function spendPerPlatform(): Collection
    {
        return DB::table('campaign_budget_transactions as ledger')
            ->join('clips', 'clips.id', '=', 'ledger.clip_id')
            ->select('clips.platform')
            ->selectRaw('SUM(ledger.amount_cents) as spent_cents')
            ->selectRaw('SUM(ledger.views_delta) as views')
            ->groupBy('clips.platform')
            ->orderByDesc('spent_cents')
            ->get()
            ->map(function ($row) {
                $row->spent_cents = (int) $row->spent_cents;
                $row->views = (int) $row->views;
                $row->real_cpm_cents = $row->views > 0 ? intdiv($row->spent_cents * 1000, $row->views) : null;

                return $row;
            });
    }
}
