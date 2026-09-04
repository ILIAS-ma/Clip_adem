<?php

namespace App\Http\Controllers\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Http\Controllers\Controller;
use App\Models\Clip;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClipController extends Controller
{
    public function index(Request $request, CampaignBudgetService $budget): View
    {
        $clips = Clip::where('user_id', $request->user()->getKey())
            ->with(['campaign.creator', 'socialAccount'])
            ->latest('submitted_at')
            ->get();

        return view('clipper.clips.index', [
            'clips' => $clips,
            // Estimation par clip : ce que rapporteraient les vues pas encore
            // créditées. Passe par le service, donc plafonds et reliquat inclus.
            'pending' => $clips->mapWithKeys(fn (Clip $clip) => [
                $clip->getKey() => $budget->quote($clip, $clip->views_total)->payableCents,
            ]),
        ]);
    }

    public function show(Request $request, Clip $clip, CampaignBudgetService $budget): View
    {
        abort_unless($clip->user_id === $request->user()->getKey(), 403);

        $clip->load(['campaign.creator', 'socialAccount']);

        return view('clipper.clips.show', [
            'clip' => $clip,
            'quote' => $budget->quote($clip, $clip->views_total),
            'snapshots' => $clip->snapshots()->latest('captured_at')->limit(20)->get(),
        ]);
    }
}
