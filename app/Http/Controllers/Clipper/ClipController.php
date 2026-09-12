<?php

namespace App\Http\Controllers\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\Clip;
use App\Services\Social\ClipSyncService;
use App\Services\Social\SocialProviderManager;
use App\Support\Social\ClipAnalysisPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClipController extends Controller
{
    public function index(Request $request, CampaignBudgetService $budget, SocialProviderManager $providers): View
    {
        $clipper = $request->user();

        // Filtres facultatifs, lus depuis la query string : l'URL reste
        // partageable et le bouton retour du navigateur fonctionne, ce qu'un
        // état Livewire seul n'aurait pas donné pour un simple filtre.
        $clips = Clip::where('user_id', $clipper->getKey())
            ->with(['campaign.creator', 'socialAccount'])
            ->when($request->filled('campagne'), fn ($q) => $q->where('campaign_id', $request->integer('campagne')))
            ->when($request->filled('plateforme'), fn ($q) => $q->where('platform', $request->string('plateforme')))
            ->when($request->filled('statut'), fn ($q) => $q->where('status', $request->string('statut')))
            ->latest('submitted_at')
            ->get();

        return view('clipper.clips.index', [
            'clips' => $clips,
            'filters' => $request->only(['campagne', 'plateforme', 'statut']),

            // Seules les plateformes réellement branchées sont proposées au
            // filtre : même raison que pour le choix de compte à lier
            // (SocialAccountController) — un clip existant sur une
            // plateforme simulée reste filtrable via l'URL, juste pas via
            // ce menu.
            'platformOptions' => collect(Platform::cases())
                ->reject(fn (Platform $p) => $providers->isSimulated($p)
                    && ! config('clipping.show_simulated_platforms'))
                ->values(),

            // Options des filtres : uniquement les campagnes où ce clippeur a
            // effectivement un clip, pas tout le catalogue.
            'campaignOptions' => Clip::where('user_id', $clipper->getKey())
                ->with('campaign:id,title')
                ->get()
                ->pluck('campaign')
                ->filter()
                ->unique('id')
                ->sortBy('title'),

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

        $outcome = $sync->refreshClip($clip);

        // Chaque raison a son message : annoncer « déjà relevé » à quelqu'un
        // dont le compte est à reconnecter le fait attendre pour rien.
        if (! $outcome->succeeded()) {
            return back()->with('status', $outcome->message(
                (int) config('clipping.sync.manual_cooldown_minutes'),
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
