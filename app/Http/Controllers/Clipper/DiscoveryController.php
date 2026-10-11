<?php

namespace App\Http\Controllers\Clipper;

use App\Http\Controllers\Controller;
use App\Services\Discovery\ClipFinderManager;
use Illuminate\View\View;

/**
 * L'écran du mode créateur.
 *
 * Le contrôleur n'ouvre que la page. Tout le reste — le classement des
 * directs, la recherche, les filtres, la sélection, les fichiers — vit dans
 * le composant Livewire, y compris le rail : chacune de ses lignes est
 * cliquable, et un rail rendu hors du composant ne pourrait rien déclencher.
 *
 * Il reste utile pour une chose : dire si la recherche est réellement
 * branchée. L'écran doit le savoir avant qu'on ait cherché quoi que ce soit.
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
