<?php

namespace App\Http\Controllers\Clipper;

use App\Contracts\CampaignBudgetService;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Clip;
use App\Services\Clippers\ClipperProgressionService;
use App\Services\Clips\ParticipationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CampaignBudgetService $budget, ParticipationService $participations): View
    {
        $clipper = $request->user();

        $clips = Clip::where('user_id', $clipper->getKey())
            ->with('campaign.creator')
            ->latest('submitted_at')
            ->get();

        return view('clipper.dashboard', [
            'clipper' => $clipper,
            'progression' => app(ClipperProgressionService::class)->for($clipper),
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

            /*
             * Le rail du tableau de bord : trois listes classées, lues d'un
             * coup d'œil. Elles répondent à des questions différentes de
             * celles des cartes — « où aller maintenant », « qu'est-ce qui
             * marche chez moi », « suis-je en état de participer » — et c'est
             * pour cela qu'elles ne se mélangent pas au reste.
             */
            'liveCampaigns' => Campaign::where('status', CampaignStatus::Active)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->with(['creator', 'rates'])
                ->withCount('clips')
                ->get()
                // Classées sur le meilleur cachet, pas sur la date : le
                // clippeur choisit où aller, et c'est le tarif qui décide.
                ->sortByDesc(fn (Campaign $c) => $c->rates->max('rate_per_1k_cents') ?? 0)
                ->take(6)
                ->values(),

            // Sur les vues rémunérées, jamais les vues brutes : c'est ce qui
            // a réellement rapporté, donc ce qu'il faut refaire.
            'topClips' => $clips->sortByDesc(fn (Clip $clip) => $clip->paid_views)
                ->take(5)
                ->values(),

            'accounts' => $clipper->socialAccounts()->get(),

            // Calendrier : campagnes déjà actives mais dont la diffusion n'a
            // pas commencé, pour anticiper plutôt que découvrir une campagne
            // déjà à moitié consommée par d'autres.
            'upcomingCampaigns' => Campaign::visibleToClippers()
                ->where('status', CampaignStatus::Active)
                ->whereNotNull('starts_at')
                ->where('starts_at', '>', now())
                ->with('creator')
                ->orderBy('starts_at')
                ->limit(4)
                ->get()
                ->map(fn (Campaign $campaign) => [
                    'campaign' => $campaign,
                    // Personnalisé : un niveau avec accès anticipé peut
                    // rejoindre avant que la campagne ne s'ouvre à tous.
                    'opensAt' => $participations->opensAtFor($campaign, $clipper),
                ]),
        ]);
    }
}
