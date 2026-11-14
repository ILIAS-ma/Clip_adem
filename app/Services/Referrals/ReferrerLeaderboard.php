<?php

namespace App\Services\Referrals;

use App\Enums\ClipStatus;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Le classement des parrains : qui a amené le plus de clippeurs qui participent vraiment.
 *
 * Un filleul compte comme « actif » quand il participe vraiment : un nombre minimal de clips validés, de
 * vues cumulées, et de l'activité sur plusieurs jours (voir rule()). Un simple compte ouvert, ou un compte
 * qui poste un clip pour la forme, ne rapporte rien à son parrain au classement. Les parrains et les
 * filleuls bannis n'y figurent pas, et seul le pseudo est montré.
 *
 * Le classement ne verse rien. S'il y a une récompense (« le meilleur parrain du mois »), c'est l'équipe
 * qui l'annonce (clipping.referrals.leaderboard_prize) et la verse à la main.
 */
class ReferrerLeaderboard
{
    public const MONTH = 'mois';

    public const ALL = 'tout';

    /** Combien de parrains sont affichés. */
    public const TOP = 20;

    /**
     * Ce qu'il faut pour qu'un filleul compte, tel que réglé dans la configuration.
     *
     * @return array{clips: int, views: int, days: int}
     */
    public static function rule(): array
    {
        return [
            'clips' => max(1, (int) config('clipping.referrals.active_min_clips')),
            'views' => max(0, (int) config('clipping.referrals.active_min_views')),
            'days' => max(1, (int) config('clipping.referrals.active_min_days')),
        ];
    }

    public static function period(?string $value): string
    {
        return $value === self::ALL ? self::ALL : self::MONTH;
    }

    /**
     * @return array{
     *     rows: Collection<int, object>,
     *     ranked: int,
     *     mine: object{rank: ?int, referred: int, active: int},
     * }
     */
    public function for(User $viewer, string $period): array
    {
        $since = $period === self::MONTH ? now()->startOfMonth() : null;
        $rule = self::rule();

        $all = DB::table('users as filleul')
            ->join('users as parrain', 'parrain.id', '=', 'filleul.referred_by')
            ->where('parrain.is_banned', false)
            ->where('filleul.is_banned', false)
            ->when($since, fn ($query) => $query->where('filleul.referred_at', '>=', $since))
            ->groupBy('parrain.id', 'parrain.pseudo')
            ->selectRaw(
                'parrain.id as user_id, parrain.pseudo, COUNT(filleul.id) as referred, '
                // Actif : assez de clips validés, assez de vues, et de l'activité sur plusieurs jours.
                .'SUM(EXISTS (SELECT 1 FROM clips WHERE clips.user_id = filleul.id AND clips.status = ? '
                .'HAVING COUNT(*) >= ? AND COALESCE(SUM(clips.views_total), 0) >= ? AND COUNT(DISTINCT DATE(clips.submitted_at)) >= ?)) as active, '
                .'MIN(filleul.referred_at) as first_at',
                [ClipStatus::Approved->value, $rule['clips'], $rule['views'], $rule['days']],
            )
            ->get()
            ->map(fn (object $row) => (object) [
                'user_id' => (int) $row->user_id,
                // Le pseudo seulement : jamais le nom réel d'un clippeur sur une page que tous voient.
                'name' => $row->pseudo ?: 'Clippeur',
                'referred' => (int) $row->referred,
                'active' => (int) $row->active,
                'first_at' => (string) $row->first_at,
            ]);

        // Classé : au moins un filleul actif. Départage : le plus de filleuls inscrits, puis le premier arrivé.
        $ranked = $all
            ->filter(fn (object $row) => $row->active > 0)
            ->sort(fn (object $a, object $b) => [$b->active, $b->referred, $a->first_at] <=> [$a->active, $a->referred, $b->first_at])
            ->values()
            ->map(function (object $row, int $index) {
                $row->rank = $index + 1;

                return $row;
            });

        $mine = $all->firstWhere('user_id', $viewer->getKey());
        $myRank = $ranked->firstWhere('user_id', $viewer->getKey());

        return [
            'rows' => $ranked->take(self::TOP)->values(),
            'ranked' => $ranked->count(),
            'mine' => (object) [
                'rank' => $myRank?->rank,
                'referred' => $mine->referred ?? 0,
                'active' => $mine->active ?? 0,
            ],
        ];
    }
}
