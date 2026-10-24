<?php

namespace App\Http\Controllers\Creator;

use App\Contracts\CampaignBudgetService;
use App\Enums\ClipStatus;
use App\Enums\ContestMetric;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Clip;
use App\Models\User;
use App\Services\Campaigns\ContestService;
use App\Services\Creators\CampaignAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CampaignController extends Controller
{
    /** Les clips listés dans le tableau : au-delà, la page deviendrait interminable. */
    protected const LISTED_CLIPS = 25;

    public function show(Request $request, Campaign $campaign, CampaignBudgetService $budget, CampaignAnalytics $analytics): View
    {
        $creator = $request->user()->creator;

        // Cloisonnement : un créateur ne voit que ses propres campagnes.
        abort_unless($campaign->creator_id === $creator->getKey(), 404);

        $campaign->load('rates');

        $clips = Clip::where('campaign_id', $campaign->getKey())
            ->where('status', ClipStatus::Approved)
            ->with('user')
            ->orderByDesc('views_total')
            ->get();

        $ids = [$campaign->getKey()];

        return view('creator.campaign', [
            'creator' => $creator,
            'campaign' => $campaign,
            'remainingCents' => $budget->remaining($campaign),
            'clips' => $clips->take(self::LISTED_CLIPS),
            'clipsTotal' => $clips->count(),
            'views' => (int) $clips->sum('views_total'),

            'overview' => $analytics->overview($ids),
            'timeline' => $analytics->timeline($ids),
            'posts' => $analytics->postsPerDay($ids),
            'distribution' => $analytics->distribution($ids),
            'topClippers' => $analytics->topClippers($ids, 6),
            'topClips' => $analytics->topClips($ids, 6),
            'platforms' => $analytics->platforms($ids),
            'podium' => $this->podium($campaign),

            // Répartition par plateforme, lue dans le grand livre : c'est la
            // seule source dont les chiffres sont auditables.
            'perPlatform' => DB::table('campaign_budget_transactions as ledger')
                ->join('clips', 'clips.id', '=', 'ledger.clip_id')
                ->where('ledger.campaign_id', $campaign->getKey())
                ->select('clips.platform')
                ->selectRaw('SUM(ledger.amount_cents) as spent_cents')
                ->selectRaw('SUM(ledger.views_delta) as views')
                ->groupBy('clips.platform')
                ->orderByDesc('spent_cents')
                ->get(),
        ]);
    }

    /**
     * Le podium final d'un classement terminé, par pseudo, avec les récompenses.
     *
     * C'est le classement officiel — arrêté à la date de fin —, pas les vues du
     * jour : le créateur y lit ce qui a été annoncé et récompensé.
     *
     * @return array<string, list<array{rank: int, name: string, score: string, reward: ?string}>>|null
     */
    protected function podium(Campaign $campaign): ?array
    {
        if (! $campaign->isContest() || ! $campaign->hasEnded()) {
            return null;
        }

        $standings = app(ContestService::class);

        $boards = collect(ContestMetric::cases())->mapWithKeys(
            fn (ContestMetric $metric) => [$metric->value => $standings->standings($campaign, 3, $metric)],
        );

        $users = User::whereIn('id', $boards->flatten(1)->pluck('user_id')->unique())->get()->keyBy('id');

        $result = $boards->map(fn ($rows, string $key) => $rows->map(function ($row) use ($campaign, $users, $key) {
            $metric = ContestMetric::from($key);

            return [
                'rank' => $row->rank,
                'name' => $users[$row->user_id]?->displayName() ?? $row->name,
                'score' => $metric->formatScore($row->score),
                'reward' => $campaign->rewardFor($row->rank, $metric)?->display(),
            ];
        })->all())->filter(fn (array $rows) => $rows !== [])->all();

        return $result === [] ? null : $result;
    }
}
