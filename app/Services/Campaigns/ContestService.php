<?php

namespace App\Services\Campaigns;

use App\Enums\ClipStatus;
use App\Models\Campaign;
use App\Models\User;
use App\Services\Budget\BonusService;
use App\Support\Budget\CreditOutcome;
use App\Support\Budget\CreditResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Campagnes « classement » : qui est devant, et le versement des prix.
 *
 * Le classement se lit sur les vues des clips VALIDÉS. Tant que la campagne
 * court, ce sont les vues actuelles ; une fois la date de fin passée, ce sont
 * les vues relevées au plus tard à cette date — sans quoi les relevés qui
 * continuent après la fin feraient encore bouger un podium déjà annoncé.
 */
class ContestService
{
    public function __construct(
        protected BonusService $bonuses,
    ) {}

    /**
     * @return Collection<int, object{rank: int, user_id: int, name: string, views: int, clips: int, best_clip_id: int}>
     */
    public function standings(Campaign $campaign, ?int $limit = null): Collection
    {
        $frozenAt = $campaign->ends_at !== null && now()->gt($campaign->ends_at) ? $campaign->ends_at : null;

        /*
         * Classement arrêté : le dernier relevé fait au plus tard à la date de
         * fin. Un clip relevé seulement après la fin compte pour zéro — ses
         * vues sont arrivées trop tard. Un clip jamais relevé du tout garde
         * ses vues actuelles : sans relevé, il n'y a rien d'autre à lire.
         */
        $views = $frozenAt
            ? DB::raw('COALESCE('
                .'(SELECT MAX(s.views) FROM clip_view_snapshots s WHERE s.clip_id = clips.id AND s.captured_at <= '
                .DB::getPdo()->quote($frozenAt->toDateTimeString()).'), '
                .'(SELECT CASE WHEN COUNT(*) = 0 THEN clips.views_total ELSE 0 END FROM clip_view_snapshots s2 WHERE s2.clip_id = clips.id)'
                .') as effective_views')
            : DB::raw('clips.views_total as effective_views');

        $clips = DB::table('clips')
            ->join('users', 'users.id', '=', 'clips.user_id')
            ->where('clips.campaign_id', $campaign->getKey())
            ->where('clips.status', ClipStatus::Approved->value)
            // Un compte banni ne monte pas sur un podium public.
            ->where('users.is_banned', false)
            ->select('clips.id', 'clips.user_id', 'clips.submitted_at', 'users.name', $views)
            ->get();

        $rows = $clips
            ->groupBy('user_id')
            ->map(function (Collection $own) {
                $best = $own->sortByDesc('effective_views')->first();

                return (object) [
                    'user_id' => (int) $best->user_id,
                    'name' => $best->name,
                    'views' => (int) $own->sum('effective_views'),
                    'clips' => $own->count(),
                    'best_clip_id' => (int) $best->id,
                    // Départage : à vues égales, celui qui a publié en premier.
                    'first_at' => (string) $own->min('submitted_at'),
                ];
            })
            ->filter(fn (object $row) => $row->views > 0)
            ->sort(fn (object $a, object $b) => [$b->views, $a->first_at] <=> [$a->views, $b->first_at])
            ->values()
            ->map(function (object $row, int $index) {
                $row->rank = $index + 1;
                unset($row->first_at);

                return $row;
            });

        return $limit ? $rows->take($limit)->values() : $rows;
    }

    /** La ligne d'un clippeur dans le classement, ou null s'il n'y figure pas. */
    public function rowFor(Campaign $campaign, User $clipper): ?object
    {
        return $this->standings($campaign)->firstWhere('user_id', $clipper->getKey());
    }

    /**
     * Verse les prix aux premières places, une seule fois.
     *
     * Chaque prix passe par le moteur de budget, avec une clé par place : une
     * relance après une coupure ne paie jamais deux fois la même place. Les
     * places sans clippeur (moins de participants que de prix) ne sont pas
     * versées, et leur montant reste dans le budget.
     *
     * @return list<array{rank: int, user: string, cents: int, result: CreditResult}>
     */
    public function payPrizes(Campaign $campaign, ?User $by = null): array
    {
        if (! $campaign->isContest()) {
            throw new InvalidArgumentException('Cette campagne n’est pas un classement.');
        }

        if (! $campaign->contestIsOver()) {
            throw new InvalidArgumentException('Le classement n’est pas encore arrêté : attendez la date de fin ou terminez la campagne.');
        }

        if ($campaign->prizes_paid_at !== null) {
            throw new InvalidArgumentException('Les prix de ce classement ont déjà été versés.');
        }

        $standings = $this->standings($campaign);
        $paid = [];

        foreach ($campaign->prizes() as $index => $cents) {
            $row = $standings[$index] ?? null;

            if ($row === null) {
                break;
            }

            $paid[] = [
                'rank' => $row->rank,
                'user' => $row->name,
                'cents' => $cents,
                'result' => $this->bonuses->grantPrize($campaign, $row->best_clip_id, $row->rank, $cents, $by),
            ];
        }

        /*
         * Marqué « versé » seulement si chaque place est passée : un prix
         * refusé (budget insuffisant) doit pouvoir être retenté, et les places
         * déjà payées ne le seront pas deux fois grâce à leur clé.
         */
        $allPaid = collect($paid)->every(fn (array $line) => $line['result']->wasPaid()
            || $line['result']->outcome === CreditOutcome::AlreadyProcessed);

        if ($allPaid) {
            $campaign->forceFill(['prizes_paid_at' => now()])->save();
        }

        return $paid;
    }
}
