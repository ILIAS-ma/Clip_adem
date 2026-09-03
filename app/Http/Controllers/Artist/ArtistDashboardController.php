<?php

namespace App\Http\Controllers\Artist;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Clip;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArtistDashboardController extends Controller
{
    public function __invoke(Request $request, CampaignBudgetService $budget): View
    {
        $artist = $request->user()->artist;

        $campaigns = $artist->campaigns()
            ->with('rates')
            ->withCount('clips')
            ->latest('created_at')
            ->get();

        $clips = Clip::whereIn('campaign_id', $campaigns->modelKeys())->get();
        $views = (int) $clips->sum('views_total');
        $spent = (int) $campaigns->sum('spent_cents');

        return view('artist.dashboard', [
            'artist' => $artist,
            'campaigns' => $campaigns,

            'budgetEngaged' => (int) $campaigns
                ->whereNotIn('status', [CampaignStatus::Draft, CampaignStatus::Archived])
                ->sum('budget_total_cents'),
            'spentCents' => $spent,
            'views' => $views,

            // Le seul indicateur de rendement qui ait du sens : ce que l'artiste
            // a réellement payé pour 1000 vues, à comparer au CPM annoncé.
            'realCpmCents' => $views > 0 ? intdiv($spent * 1000, $views) : null,

            'clipsCount' => $clips->where('status', ClipStatus::Approved)->count(),
            'activeCampaigns' => $campaigns->where('status', CampaignStatus::Active)->count(),

            'remaining' => $campaigns->mapWithKeys(fn (Campaign $campaign) => [
                $campaign->getKey() => $budget->remaining($campaign),
            ]),
        ]);
    }
}
