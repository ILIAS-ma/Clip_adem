<?php

namespace App\Http\Controllers\Clipper;

use App\Enums\CampaignStatus;
use App\Enums\CreatorKind;
use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\Discovery\ClipFinderManager;
use Illuminate\View\View;

/**
 * L'écran du mode créateur.
 *
 * Le contrôleur ouvre la page et remplit le rail. Filtres, recherche et
 * sélection vivent dans le composant Livewire, parce qu'ils s'enchaînent sans
 * quitter l'écran — et parce que le rail, lui, ne doit pas bouger : il est
 * rendu hors du composant, un compteur qui s'y trouverait serait juste au
 * chargement puis faux ensuite.
 */
class DiscoveryController extends Controller
{
    public function __invoke(ClipFinderManager $finder): View
    {
        return view('clipper.discovery', [
            'simule' => ! $finder->isConfigured(),
            'utilisable' => $finder->isUsable(),
            'chainesPayees' => $this->chainesPayees(),
        ]);
    }

    /**
     * Les chaînes de streamers dont une campagne est ouverte, par cachet.
     *
     * Sur le cachet et non sur le budget : un budget de 10 000 € à 20 centimes
     * les mille vues rapporte moins qu'un budget de 500 € à deux euros, et
     * c'est le second qu'il faut montrer en tête.
     *
     * @return list<array{login: string, campagne: Campaign, cachet: int|null}>
     */
    protected function chainesPayees(): array
    {
        return Campaign::query()
            ->where('status', CampaignStatus::Active)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->with(['creator', 'rates'])
            ->get()
            ->filter(fn (Campaign $c) => $c->creator?->kind() === CreatorKind::Streamer)
            ->flatMap(fn (Campaign $c) => collect([
                $c->creator->twitch_handle,
                $c->creator->kick_handle,
            ])
                ->filter()
                ->unique()
                ->map(fn ($pseudo) => [
                    'login' => mb_strtolower($pseudo),
                    'campagne' => $c,
                    'cachet' => $c->rateFor(Platform::TikTok) ?? $c->rates->max('rate_per_1k_cents'),
                ]))
            ->sortByDesc('cachet')
            ->take(8)
            ->values()
            ->all();
    }
}
