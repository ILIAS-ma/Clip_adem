<?php

namespace App\Http\Controllers\Creator;

use App\Contracts\CampaignBudgetService;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Creators\CreatorStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CampaignBudgetService $budget,
        CreatorStatsService $stats,
    ): View {
        $creator = $request->user()->creator;

        $campaigns = $creator->campaigns()
            ->with('rates')
            ->withCount('clips')
            ->latest('created_at')
            ->get();

        $summary = $stats->summary($creator);

        return view('creator.dashboard', [
            'creator' => $creator,
            'campaigns' => $campaigns,

            'summary' => $summary,
            'headline' => $stats->headline($summary),
            'daily' => $stats->daily($creator, 30),
            'topClips' => $stats->topClips($creator, 5),

            'remaining' => $campaigns->mapWithKeys(fn (Campaign $campaign) => [
                $campaign->getKey() => $budget->remaining($campaign),
            ]),
        ]);
    }
}
