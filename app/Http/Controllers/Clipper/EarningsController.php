<?php

namespace App\Http\Controllers\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Http\Controllers\Controller;
use App\Models\BudgetTransaction;
use App\Models\Clip;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EarningsController extends Controller
{
    public function index(Request $request, CampaignBudgetService $budget): View
    {
        $clipper = $request->user();

        $clips = Clip::where('user_id', $clipper->getKey())->with('campaign')->get();

        return view('clipper.earnings', [
            'clipper' => $clipper,

            // Validé : inscrit au grand livre, le budget est déjà consommé.
            'earnedCents' => (int) $clips->sum('earned_cents'),

            // Estimé : ce que rapporteraient les vues pas encore créditées.
            // Jamais un « vues × taux » maison, qui promettrait des sommes que
            // le reliquat de budget ne pourrait pas honorer.
            'pendingCents' => $clips->sum(
                fn (Clip $clip) => $budget->quote($clip, $clip->views_total)->payableCents,
            ),

            'balanceCents' => $clipper->availableBalanceCents(),
            'lockedCents' => $clipper->lockedPayoutCents(),
            'minimumCents' => config('clipping.payouts.minimum_cents'),

            'payouts' => $clipper->payouts()->latest('requested_at')->get(),

            // Historique des crédits, lu dans le grand livre : c'est la seule
            // source dont les chiffres sont auditables.
            'transactions' => BudgetTransaction::where('user_id', $clipper->getKey())
                ->with('campaign')
                ->latest('created_at')
                ->limit(50)
                ->get(),
        ]);
    }
}
