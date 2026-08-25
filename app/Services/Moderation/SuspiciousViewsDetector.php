<?php

namespace App\Services\Moderation;

use App\Models\Clip;
use App\Models\ClipViewSnapshot;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repère les courbes de vues qui ne ressemblent pas à une audience réelle.
 *
 * Aucun seuil ne déclenche de sanction automatique : le rôle de ce détecteur
 * est de remonter un clip en tête de file de modération, la décision reste
 * humaine. Un faux positif coûte une vérification, un faux négatif coûte de
 * l'argent — les seuils penchent donc du côté prudent.
 */
class SuspiciousViewsDetector
{
    /**
     * Motifs de suspicion sur un clip donné.
     *
     * @return array<int, string>
     */
    public function flags(Clip $clip): array
    {
        $flags = [];

        if ($spike = $this->findSpike($clip)) {
            $flags[] = sprintf(
                '+%s vues en %s h (×%s)',
                number_format($spike['delta'], 0, ',', ' '),
                $spike['hours'],
                $spike['factor'],
            );
        }

        if ($coldStart = $this->findColdStart($clip)) {
            $flags[] = sprintf(
                '%s vues dans les %s premières minutes',
                number_format($coldStart, 0, ',', ' '),
                config('clipping.suspicious.cold_start_minutes'),
            );
        }

        if ($ratio = $this->findFollowerRatio($clip)) {
            $flags[] = sprintf('%s vues par abonné du compte lié', number_format($ratio, 0, ',', ' '));
        }

        return $flags;
    }

    public function isSuspicious(Clip $clip): bool
    {
        return $this->flags($clip) !== [];
    }

    /**
     * Filtre SQL équivalent à la première règle, pour la file de modération.
     *
     * Volontairement limité au bond de vues : les deux autres règles imposent
     * des jointures qui rendraient le listing lent pour un gain marginal.
     *
     * @param  Builder<Clip>  $query
     * @return Builder<Clip>
     */
    public function scopeSuspicious(Builder $query): Builder
    {
        $config = config('clipping.suspicious');

        return $query->whereExists(function ($sub) use ($config) {
            $sub->selectRaw('1')
                ->from('clip_view_snapshots as before')
                ->join('clip_view_snapshots as after', function ($join) use ($config) {
                    $join->on('after.clip_id', '=', 'before.clip_id')
                        ->whereColumn('after.id', '>', 'before.id')
                        // La fenêtre temporelle suffit à borner l'appariement :
                        // inutile d'exiger deux relevés strictement consécutifs.
                        ->whereRaw(
                            'TIMESTAMPDIFF(HOUR, `before`.`captured_at`, `after`.`captured_at`) <= ?',
                            [$config['spike_window_hours']],
                        );
                })
                ->whereColumn('before.clip_id', 'clips.id')
                ->whereRaw('`after`.`views` - `before`.`views` >= ?', [$config['spike_min_views']])
                ->whereRaw('`after`.`views` >= `before`.`views` * ?', [$config['spike_factor']]);
        });
    }

    /** @return array{delta: int, hours: int, factor: float}|null */
    protected function findSpike(Clip $clip): ?array
    {
        $config = config('clipping.suspicious');

        $snapshots = ClipViewSnapshot::where('clip_id', $clip->getKey())
            ->orderBy('captured_at')
            ->get(['views', 'captured_at']);

        $previous = null;

        foreach ($snapshots as $snapshot) {
            if ($previous) {
                $hours = $previous->captured_at->diffInHours($snapshot->captured_at);
                $delta = $snapshot->views - $previous->views;

                $exploded = $previous->views === 0
                    ? $delta >= $config['spike_min_views']
                    : $snapshot->views >= $previous->views * $config['spike_factor'];

                if ($hours <= $config['spike_window_hours']
                    && $delta >= $config['spike_min_views']
                    && $exploded) {
                    return [
                        'delta' => $delta,
                        'hours' => max(1, (int) $hours),
                        'factor' => $previous->views === 0
                            ? INF
                            : round($snapshot->views / $previous->views, 1),
                    ];
                }
            }

            $previous = $snapshot;
        }

        return null;
    }

    protected function findColdStart(Clip $clip): ?int
    {
        $config = config('clipping.suspicious');

        if (! $clip->posted_at) {
            return null;
        }

        $early = ClipViewSnapshot::where('clip_id', $clip->getKey())
            ->where('captured_at', '<=', $clip->posted_at->copy()->addMinutes($config['cold_start_minutes']))
            ->orderByDesc('captured_at')
            ->value('views');

        return $early >= $config['cold_start_views'] ? (int) $early : null;
    }

    protected function findFollowerRatio(Clip $clip): ?int
    {
        $followers = $clip->socialAccount?->followers_count;

        if (! $followers || $followers < 1) {
            return null;
        }

        $ratio = intdiv($clip->views_total, $followers);

        return $ratio >= config('clipping.suspicious.views_per_follower') ? $ratio : null;
    }
}
