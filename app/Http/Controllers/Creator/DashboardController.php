<?php

namespace App\Http\Controllers\Creator;

use App\Enums\CampaignStatus;
use App\Enums\ClipStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Creators\CampaignAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CampaignAnalytics $analytics): View
    {
        $creator = $request->user()->creator;

        $approved = fn ($query) => $query->where('status', ClipStatus::Approved->value);

        $campaigns = $creator->campaigns()
            // Un brouillon est un travail de l'équipe, pas une campagne : le créateur ne le voit pas.
            ->whereNotIn('status', [CampaignStatus::Draft->value, CampaignStatus::Archived->value])
            ->withCount(['clips as clips_count' => $approved])
            ->withSum(['clips as views_sum' => $approved], 'views_total')
            ->latest('created_at')
            ->get()
            // Les campagnes en cours d'abord, les terminées ensuite : elles restent là,
            // consultables, mais ce n'est plus ce qu'on vient regarder en premier.
            ->sortBy(fn (Campaign $campaign) => $campaign->hasEnded() ? 1 : 0)
            ->values();

        $ids = $campaigns->pluck('id')->all();
        $overview = $analytics->overview($ids);

        return view('creator.dashboard', [
            'creator' => $creator,
            'campaigns' => $campaigns,

            // L'audience se lit sur les clips : une campagne « classement » ne paie rien
            // aux vues, mais elle en produit. L'argent n'est pas l'affaire du créateur ici.
            'overview' => $overview,
            'headline' => $analytics->headline(
                $overview,
                $campaigns->reject(fn (Campaign $c) => $c->hasEnded())->count(),
                $campaigns->count(),
            ),

            'timeline' => $analytics->timeline($ids),
            'posts' => $analytics->postsPerDay($ids),
            'distribution' => $analytics->distribution($ids),
            'topClippers' => $analytics->topClippers($ids, 6),
            'topClips' => $analytics->topClips($ids, 6),
            'platforms' => $analytics->platforms($ids),
        ]);
    }
}
