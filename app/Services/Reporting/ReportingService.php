<?php

namespace App\Services\Reporting;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Enums\PayoutStatus;
use App\Models\BudgetTransaction;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Clip;
use App\Models\Creator;
use App\Models\Payout;
use App\Models\Season;
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
     * Classement depuis toujours des clippeurs d'une catégorie : vues et gains
     * cumulés sur toutes les campagnes qui y sont rangées.
     *
     * Mêmes définitions que topClippers() — vues des clips validés, gains de
     * tous les clips (un clip invalidé est remis à zéro, il ne compte donc
     * plus) — pour qu'un clippeur ne lise jamais deux chiffres différents
     * pour la même chose selon l'onglet.
     *
     * Les campagnes supprimées restent comptées : c'est un classement de
     * carrière, et l'argent gagné dessus a bien été gagné.
     *
     * Les comptes bannis sont exclus à la requête : ce classement est public.
     *
     * @return Collection<int, object{id: int, name: string, views: int, earned_cents: int, clips_count: int}>
     */
    public function topClippersInCategory(Category $category, int $limit = 20): Collection
    {
        return DB::table('clips')
            ->join('campaigns', 'campaigns.id', '=', 'clips.campaign_id')
            ->join('users', 'users.id', '=', 'clips.user_id')
            ->where('campaigns.category_id', $category->getKey())
            ->where('users.is_banned', false)
            ->select('users.id', 'users.name')
            ->selectRaw('SUM(clips.earned_cents) as earned_cents')
            ->selectRaw('SUM(CASE WHEN clips.status = ? THEN clips.views_total ELSE 0 END) as views', [ClipStatus::Approved->value])
            ->selectRaw('SUM(CASE WHEN clips.status = ? THEN 1 ELSE 0 END) as clips_count', [ClipStatus::Approved->value])
            ->groupBy('users.id', 'users.name')
            ->havingRaw('SUM(clips.earned_cents) > 0 OR SUM(CASE WHEN clips.status = ? THEN clips.views_total ELSE 0 END) > 0', [ClipStatus::Approved->value])
            ->orderByDesc('earned_cents')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->earned_cents = (int) $row->earned_cents;
                $row->views = (int) $row->views;
                $row->clips_count = (int) $row->clips_count;

                return $row;
            });
    }

    /**
     * Classement d'une saison : ce qui a été gagné ENTRE ses bornes, lu dans
     * le grand livre.
     *
     * Le grand livre plutôt que les compteurs des clips : un clip publié avant
     * la saison continue de faire des vues pendant, et ce sont celles-là qui
     * comptent — pas son total de carrière. Une invalidation pendant la
     * saison y figure en négatif et fait redescendre son auteur.
     *
     * @return Collection<int, object{id: int, name: string, views: int, earned_cents: int}>
     */
    public function topClippersInSeason(Season $season, int $limit = 20): Collection
    {
        $table = (new BudgetTransaction)->getTable();

        return DB::table($table)
            ->join('users', 'users.id', '=', $table.'.user_id')
            ->whereBetween($table.'.created_at', [$season->startsAt(), $season->endsAt()])
            ->where('users.is_banned', false)
            ->groupBy('users.id', 'users.name')
            ->select('users.id', 'users.name')
            ->selectRaw('SUM('.$table.'.amount_cents) as earned_cents')
            ->selectRaw('SUM('.$table.'.views_delta) as views')
            ->havingRaw('SUM('.$table.'.amount_cents) > 0')
            ->orderByDesc('earned_cents')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->earned_cents = (int) $row->earned_cents;
                $row->views = (int) $row->views;

                return $row;
            });
    }

    /**
     * Meilleurs parrains : commissions cumulées, reprises déduites.
     *
     * @return Collection<int, object{id: int, name: string, earned_cents: int, referrals: int}>
     */
    public function topReferrers(int $limit = 10): Collection
    {
        return DB::table('referral_commissions')
            ->join('users', 'users.id', '=', 'referral_commissions.referrer_id')
            ->where('users.is_banned', false)
            ->groupBy('users.id', 'users.name')
            ->select('users.id', 'users.name')
            ->selectRaw('SUM(referral_commissions.amount_cents) as earned_cents')
            ->selectRaw('COUNT(DISTINCT referral_commissions.referred_id) as referrals')
            ->havingRaw('SUM(referral_commissions.amount_cents) > 0')
            ->orderByDesc('earned_cents')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->earned_cents = (int) $row->earned_cents;
                $row->referrals = (int) $row->referrals;

                return $row;
            });
    }

    /**
     * Totaux d'une catégorie, tous clippeurs confondus : ce que la catégorie
     * a généré en vues et en euros depuis toujours.
     *
     * @return array{views: int, earned_cents: int}
     */
    public function categoryTotals(Category $category): array
    {
        $row = DB::table('clips')
            ->join('campaigns', 'campaigns.id', '=', 'clips.campaign_id')
            ->where('campaigns.category_id', $category->getKey())
            ->selectRaw('COALESCE(SUM(clips.earned_cents), 0) as earned_cents')
            ->selectRaw('COALESCE(SUM(CASE WHEN clips.status = ? THEN clips.views_total ELSE 0 END), 0) as views', [ClipStatus::Approved->value])
            ->first();

        return [
            'views' => (int) $row->views,
            'earned_cents' => (int) $row->earned_cents,
        ];
    }

    /**
     * Classement des clippeurs sur les vues gagnées cette semaine, pas leurs
     * gains cumulés depuis toujours : topClippers() répond « qui rapporte le
     * plus au total », celui-ci répond « qui est actif en ce moment ».
     *
     * Lu dans le grand livre (`budget_transactions.views_delta`), comme
     * `ClipperProgressionService::recentViews()` pour un clippeur seul : les
     * invalidations y figurent en négatif, donc un clippeur sanctionné cette
     * semaine ne remonte pas artificiellement dans le classement.
     *
     * @return Collection<int, object{id: int, name: string, is_banned: bool, views: int}>
     */
    public function topClippersThisWeek(int $limit = 10, int $days = 7): Collection
    {
        // Le nom de la table est pris sur le modèle : l'écrire en dur invite
        // exactement la faute qu'on répare ici — elle s'appelle
        // `campaign_budget_transactions`, pas `budget_transactions`.
        $table = (new BudgetTransaction)->getTable();

        return DB::table($table)
            ->join('users', 'users.id', '=', $table.'.user_id')
            ->where($table.'.created_at', '>=', now()->subDays($days))
            ->groupBy('users.id', 'users.name', 'users.is_banned')
            ->select('users.id', 'users.name', 'users.is_banned')
            ->selectRaw('SUM('.$table.'.views_delta) as views')
            ->havingRaw('SUM('.$table.'.views_delta) > 0')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->views = (int) $row->views;

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
