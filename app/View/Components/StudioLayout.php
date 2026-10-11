<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Le gabarit du mode créateur.
 *
 * Distinct d'`AppLayout` parce qu'il ne partage rien avec lui : ni la
 * palette, ni la grille, ni la feuille de style. Un mode prend l'écran — la
 * colonne du site plus celle de l'outil feraient deux barres côte à côte,
 * chacune annonçant une navigation différente.
 */
class StudioLayout extends Component
{
    public function render(): View
    {
        return view('layouts.studio');
    }
}
