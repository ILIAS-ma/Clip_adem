<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use App\Services\Discovery\ClipFinderManager;
use Illuminate\View\View;

/**
 * L'écran de découverte.
 *
 * Le contrôleur ne fait qu'ouvrir la page : filtres, recherche et sélection
 * vivent dans le composant Livewire, parce qu'ils s'enchaînent sans quitter
 * l'écran. Il reste utile pour une seule chose — dire si la recherche est
 * réellement branchée, information que la page doit porter avant même qu'on
 * ait cherché quoi que ce soit.
 */
class DiscoveryController extends Controller
{
    public function __invoke(ClipFinderManager $finder): View
    {
        return view('clipper.discovery', [
            'simule' => ! $finder->isConfigured(),
            'utilisable' => $finder->isUsable(),
        ]);
    }
}
