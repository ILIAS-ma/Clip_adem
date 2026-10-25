<?php

namespace App\Services\Campaigns;

use App\Enums\ContestMetric;
use App\Enums\ModerationAction;
use App\Enums\UserRole;
use App\Filament\Resources\Clippers\ClipperResource;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Le classement d'une campagne, tel que l'équipe veut le lire.
 *
 * Les clippeurs voient des noms et des places. L'équipe, elle, veut savoir à qui elle
 * va verser quoi, sur combien de clips, et surtout qui a essayé de faire compter des
 * vidéos qui ne comptent pas. Ce service rassemble tout cela sans rien calculer lui-même :
 * le classement vient de ContestService, le même que celui des clippeurs.
 */
class ContestReport
{
    /** Combien de lignes par classement : au-delà, ce n'est plus un classement qu'on lit. */
    protected const ROWS = 50;

    public function __construct(
        protected ContestService $contest,
    ) {}

    /**
     * @return array{
     *     stats: array<string, int>,
     *     boards: list<array{metric: ContestMetric, podium: list<object>, rows: list<object>, leader: int}>,
     *     flagged: list<object>,
     * }
     */
    public function for(Campaign $campaign): array
    {
        $flagged = $this->flaggedByUser($campaign);

        $everyone = $this->contest->standings($campaign, null, ContestMetric::Views);

        $boards = collect(ContestMetric::cases())->map(function (ContestMetric $metric) use ($campaign, $flagged) {
            $standings = $this->contest->standings($campaign, self::ROWS, $metric);
            $users = User::whereIn('id', $standings->pluck('user_id'))->get(['id', 'name', 'pseudo', 'role'])->keyBy('id');

            $rows = $standings->map(function (object $row) use ($campaign, $metric, $flagged, $users) {
                $user = $users[$row->user_id] ?? null;

                return (object) [
                    'rank' => $row->rank,
                    'user_id' => $row->user_id,
                    'name' => $row->name,
                    'pseudo' => $user?->pseudo,
                    'views' => $row->views,
                    'clips' => $row->clips,
                    'score' => $row->score,
                    'reward' => $campaign->rewardFor($row->rank, $metric)?->display(),
                    'flagged' => (int) ($flagged[$row->user_id]->n ?? 0),
                    'url' => $user?->role === UserRole::Clipper ? ClipperResource::getUrl('view', ['record' => $user]) : null,
                ];
            })->values();

            return [
                'metric' => $metric,
                'podium' => $rows->take(3)->all(),
                'rows' => $rows->slice(3)->values()->all(),
                'leader' => max(1, (int) ($rows->first()->score ?? 1)),
            ];
        })->all();

        return [
            'stats' => [
                'participants' => $everyone->count(),
                'clips' => (int) $everyone->sum('clips'),
                'views' => (int) $everyone->sum('views'),
                'refused' => (int) $flagged->sum('n'),
                'prize_pool_cents' => $campaign->prizePoolCents(),
            ],
            'boards' => $boards,
            'flagged' => $this->flaggedList($flagged),
        ];
    }

    /**
     * Qui a eu des clips refusés pour avoir été publiés hors de la campagne, et combien.
     *
     * @return Collection<int, object{user_id: int, n: int, last_at: string}>
     */
    protected function flaggedByUser(Campaign $campaign): Collection
    {
        return DB::table('moderation_logs as log')
            ->join('clips as c', 'c.id', '=', 'log.subject_id')
            ->where('log.subject_type', (new Clip)->getMorphClass())
            ->where('log.action', ModerationAction::ClipOutsideWindow->value)
            ->where('c.campaign_id', $campaign->getKey())
            ->groupBy('c.user_id')
            ->selectRaw('c.user_id, COUNT(*) as n, MAX(log.created_at) as last_at')
            ->get()
            ->keyBy('user_id');
    }

    /**
     * @param  Collection<int, object>  $flagged
     * @return list<object>
     */
    protected function flaggedList(Collection $flagged): array
    {
        $users = User::whereIn('id', $flagged->keys())->get(['id', 'name', 'pseudo', 'role', 'is_banned'])->keyBy('id');

        return $flagged->sortByDesc('n')->map(fn (object $row) => (object) [
            'user_id' => (int) $row->user_id,
            'name' => $users[$row->user_id]?->name ?? 'Compte supprimé',
            'pseudo' => $users[$row->user_id]?->pseudo,
            'banned' => (bool) ($users[$row->user_id]?->is_banned),
            'count' => (int) $row->n,
            'last_at' => $row->last_at,
            'url' => ($users[$row->user_id]?->role === UserRole::Clipper) ? ClipperResource::getUrl('view', ['record' => $users[$row->user_id]]) : null,
        ])->values()->all();
    }
}
