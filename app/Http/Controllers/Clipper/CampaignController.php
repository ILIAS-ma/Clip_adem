<?php

namespace App\Http\Controllers\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Clips\ParticipationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        // Le catalogue et ses filtres vivent dans un composant Livewire ;
        // cette page n'est que sa coquille.
        return view('clipper.campaigns.index');
    }

    public function show(
        Request $request,
        Campaign $campaign,
        CampaignBudgetService $budget,
        ParticipationService $participations,
    ): View {
        abort_unless($campaign->isVisibleToClippers(), 404);

        $campaign->load(['creator', 'rates', 'assets']);
        $clipper = $request->user();

        return view('clipper.campaigns.show', [
            'campaign' => $campaign,
            // Le reliquat est demandé au service, jamais lu directement sur la
            // colonne : c'est la même valeur que celle vue par le back-office.
            'remainingCents' => $budget->remaining($campaign),

            // Une campagne programmée peut être rejoignable en avance selon le
            // niveau : l'ouverture n'est donc pas la même pour tout le monde.
            'isOpen' => $participations->canJoinNow($campaign, $clipper),
            'canSubmit' => $budget->acceptsNewClips($campaign),
            'opensAt' => $campaign->hasStarted() ? null : $participations->opensAtFor($campaign, $clipper),
            'participations' => $campaign->participations()
                ->where('user_id', $clipper->getKey())
                ->with('socialAccount')
                ->get(),
            'eligibleAccounts' => $participations->eligibleAccounts($campaign, $clipper),
            'clips' => $campaign->clips()
                ->where('user_id', $clipper->getKey())
                ->latest('submitted_at')
                ->get(),
        ]);
    }
}
