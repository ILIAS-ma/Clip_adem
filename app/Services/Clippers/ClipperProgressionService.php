<?php

namespace App\Services\Clippers;

use App\Enums\ClipperLevel;
use App\Enums\ClipStatus;
use App\Models\BudgetTransaction;
use App\Models\Clip;
use App\Models\User;
use App\Support\Clippers\Progression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Calcule le niveau et les avantages d'un clippeur.
 *
 * L'expérience part de `clips.paid_views`, jamais de `views_total` : seul le
 * premier ne compte que ce que le moteur de budget a réellement crédité, et une
 * invalidation le remet à zéro. Le niveau ne peut donc pas récompenser des vues
 * que le détecteur de fraude aurait dû rattraper.
 */
class ClipperProgressionService
{
    /**
     * Le service est appelé depuis le moteur de budget, sous verrou de
     * campagne : le résultat est mémorisé le temps de la requête pour ne pas
     * rejouer les mêmes agrégats à chaque clip d'un même clippeur.
     *
     * @var array<int, Progression>
     */
    protected array $cache = [];

    public function for(User $clipper): Progression
    {
        return $this->cache[$clipper->getKey()] ??= $this->compute($clipper);
    }

    public function forget(User $clipper): void
    {
        unset($this->cache[$clipper->getKey()]);
    }

    protected function compute(User $clipper): Progression
    {
        // Un compte banni repart de zéro : sans cela, le niveau deviendrait un
        // actif qu'on revend avec le compte.
        if ($clipper->is_banned) {
            return new Progression(
                level: ClipperLevel::Beginner,
                careerXp: 0,
                recentViews: 0,
                perksActive: false,
                paidViews: 0,
                approvedClips: 0,
                invalidatedClips: 0,
                campaignsCount: 0,
            );
        }

        $config = config('clipping.progression');
        $totals = $this->totals($clipper);

        $xp = max(0,
            $totals->paid_views
            + $totals->approved_clips * $config['xp_per_approved_clip']
            + $totals->campaigns_count * $config['xp_per_campaign']
            - $totals->invalidated_clips * $config['xp_penalty_per_invalidated_clip'],
        );

        $level = ClipperLevel::fromXp($xp);
        $recentViews = $this->recentViews($clipper, $config['activity_window_days']);

        return new Progression(
            level: $level,
            careerXp: $xp,
            recentViews: $recentViews,
            // Le niveau reste acquis ; les avantages, eux, se maintiennent.
            perksActive: $level->hasPerks() && $recentViews >= $level->activityFloor(),
            paidViews: $totals->paid_views,
            approvedClips: $totals->approved_clips,
            invalidatedClips: $totals->invalidated_clips,
            campaignsCount: $totals->campaigns_count,
        );
    }

    /**
     * Une seule requête pour toutes les composantes de l'expérience : elle
     * s'exécute pendant que le verrou de campagne est tenu, elle doit rester
     * courte. L'index (user_id, status) de `clips` la couvre.
     */
    protected function totals(User $clipper): object
    {
        return Clip::query()
            ->where('user_id', $clipper->getKey())
            ->selectRaw('COALESCE(SUM(paid_views), 0) as paid_views')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as approved_clips', [ClipStatus::Approved->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as invalidated_clips', [ClipStatus::Invalidated->value])
            // Campagnes distinctes où le clippeur a au moins un clip validé :
            // récompense la variété, pas le fait d'empiler des clips au même
            // endroit.
            ->selectRaw('COUNT(DISTINCT CASE WHEN status = ? THEN campaign_id END) as campaigns_count', [ClipStatus::Approved->value])
            ->first();
    }

    /**
     * Vues rémunérées sur la fenêtre glissante, lues dans le grand livre.
     *
     * Les annulations y figurent en négatif : une invalidation retire donc
     * aussi l'activité récente qu'elle avait apportée, sans traitement à part.
     */
    protected function recentViews(User $clipper, int $windowDays): int
    {
        return max(0, (int) BudgetTransaction::where('user_id', $clipper->getKey())
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->sum('views_delta'));
    }

    /**
     * Classement des clippeurs par expérience.
     *
     * Reproduit la formule en SQL pour éviter de charger tous les clippeurs en
     * mémoire ; les deux doivent rester alignées.
     *
     * @return Collection<int, object>
     */
    public function leaderboard(int $limit = 20)
    {
        $config = config('clipping.progression');

        return DB::table('clips')
            ->join('users', 'users.id', '=', 'clips.user_id')
            ->where('users.is_banned', false)
            ->whereNull('users.deleted_at')
            ->groupBy('users.id', 'users.name', 'users.pseudo')
            ->select('users.id', 'users.name', 'users.pseudo')
            ->selectRaw('COALESCE(SUM(clips.paid_views), 0) as paid_views')
            ->selectRaw(
                'GREATEST(0,'
                .'COALESCE(SUM(clips.paid_views), 0)'
                .' + SUM(CASE WHEN clips.status = ? THEN 1 ELSE 0 END) * ?'
                .' + COUNT(DISTINCT CASE WHEN clips.status = ? THEN clips.campaign_id END) * ?'
                .' - SUM(CASE WHEN clips.status = ? THEN 1 ELSE 0 END) * ?'
                .') as xp',
                [
                    ClipStatus::Approved->value, $config['xp_per_approved_clip'],
                    ClipStatus::Approved->value, $config['xp_per_campaign'],
                    ClipStatus::Invalidated->value, $config['xp_penalty_per_invalidated_clip'],
                ],
            )
            ->orderByDesc('xp')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->xp = (int) $row->xp;
                $row->paid_views = (int) $row->paid_views;
                $row->level = ClipperLevel::fromXp($row->xp);

                return $row;
            });
    }
}
