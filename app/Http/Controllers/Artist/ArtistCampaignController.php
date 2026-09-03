<?php

namespace App\Http\Controllers\Artist;

use App\Contracts\CampaignBudgetService;
use App\Enums\ClipStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Clip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ArtistCampaignController extends Controller
{
    public function show(Request $request, Campaign $campaign, CampaignBudgetService $budget): View
    {
        $artist = $request->user()->artist;

        // Cloisonnement : un artiste ne voit que ses propres campagnes.
        abort_unless($campaign->artist_id === $artist->getKey(), 404);

        $campaign->load('rates');

        $clips = Clip::where('campaign_id', $campaign->getKey())
            ->where('status', ClipStatus::Approved)
            ->with('user')
            ->orderByDesc('views_total')
            ->get();

        return view('artist.campaign', [
            'artist' => $artist,
            'campaign' => $campaign,
            'remainingCents' => $budget->remaining($campaign),
            'clips' => $clips,
            'views' => (int) $clips->sum('views_total'),

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
}
