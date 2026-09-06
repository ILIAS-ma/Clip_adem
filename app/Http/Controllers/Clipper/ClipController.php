<?php

namespace App\Http\Controllers\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Http\Controllers\Controller;
use App\Models\Clip;
use App\Services\Social\ClipSyncService;
use Illuminate\Http\RedirectResponse;
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

    /**
     * Relève ce clip tout de suite.
     *
     * Aucune plateforme ne pousse le compteur de vues : entre deux passages
     * automatiques, c'est le seul moyen pour un clippeur de voir où il en est.
     * Le délai de garde vit dans le service — le contrôleur ne fait
     * qu'annoncer le résultat.
     */
    public function refresh(Request $request, Clip $clip, ClipSyncService $sync): RedirectResponse
    {
        abort_unless($clip->user_id === $request->user()->getKey(), 403);

        $before = $clip->views_total;

        if (! $sync->syncClip($clip)) {
            return back()->with('status', sprintf(
                'Déjà relevé il y a moins de %d minutes. Les plateformes ne mettent pas leurs '
                .'compteurs à jour en continu : revenez un peu plus tard.',
                config('clipping.sync.manual_cooldown_minutes'),
            ));
        }

        $gained = $clip->fresh()->views_total - $before;

        return back()->with('status', $gained > 0
            ? number_format($gained, 0, ',', ' ').' vues de plus depuis le dernier relevé.'
            : 'Relevé effectué : aucune vue supplémentaire pour l’instant.');
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
