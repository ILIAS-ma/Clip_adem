<?php

namespace App\Http\Controllers\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Http\Controllers\Controller;
use App\Models\Clip;
use App\Services\Social\ClipSyncService;
use App\Support\Social\ClipAnalysisPresenter;
use Illuminate\Http\JsonResponse;
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

    /**
     * Relevé JSON complet d'un clip — même circuit que refresh() (API
     * officielle du compte lié, même délai de garde), mais renvoie le
     * détail exploitable par l'interface plutôt qu'un message flash.
     *
     * Jamais de scraping de l'URL soumise : les chiffres viennent du compte
     * du clippeur, déjà lié en OAuth au moment de la soumission.
     */
    public function analyze(Request $request, Clip $clip, ClipSyncService $sync): JsonResponse
    {
        abort_unless($clip->user_id === $request->user()->getKey(), 403);

        $metrics = $sync->syncClipWithMetrics($clip);

        // Le clip a pu être relevé récemment (délai de garde) sans que ce
        // soit une erreur : on renvoie alors les dernières données connues,
        // déjà persistées, plutôt qu'un échec qui ferait croire à un lien
        // cassé.
        if ($metrics === null && ! $clip->last_synced_at) {
            return response()->json(ClipAnalysisPresenter::error(
                'Vidéo introuvable ou pas encore accessible sur la plateforme liée.'
            ), 422);
        }

        return response()->json(ClipAnalysisPresenter::success($clip->fresh(), $metrics));
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
