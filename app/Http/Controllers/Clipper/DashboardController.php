<?php

namespace App\Http\Controllers\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Clip;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CampaignBudgetService $budget): View
    {
        $clipper = $request->user();

        $clips = Clip::where('user_id', $clipper->getKey())
            ->with('campaign.artist')
            ->latest('submitted_at')
            ->get();

        return view('clipper.dashboard', [
            'clipper' => $clipper,
            'clips' => $clips,
            'views' => (int) $clips->sum('views_total'),
            'earnedCents' => (int) $clips->sum('earned_cents'),

            // Ce qui reste à toucher : le solde fait autorité, jamais un calcul
            // refait dans la vue.
            'balanceCents' => $clipper->availableBalanceCents(),

            // Vues comptées mais non rémunérées, toutes campagnes confondues.
            // Les afficher évite qu'un clippeur dont les gains stagnent conclue
            // à un bug alors qu'un budget est simplement épuisé.
            'unpaidViews' => $clips->sum(fn (Clip $clip) => $clip->unpaidViews()),

            'openCampaigns' => Campaign::where('status', CampaignStatus::Active)->count(),
            'accountsCount' => $clipper->socialAccounts()->count(),
            'needsReconnect' => $clipper->socialAccounts()->where('needs_reconnect', true)->count(),
        ]);
    }
}
