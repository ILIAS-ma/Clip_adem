<?php

namespace App\View\Composers;

use Illuminate\View\View;

/**
 * Les rubriques de navigation, fournies aux deux vues qui les affichent.
 *
 * La colonne latérale et le tiroir mobile montrent la même navigation. La
 * liste a d'abord vécu dans une vue incluse par les deux — et ne marchait
 * pas : `@include` exécute la vue dans son propre contexte, les variables
 * qu'elle définit ne remontent pas au parent.
 *
 * Un composer la donne aux deux sans qu'aucune ne la possède. Deux copies
 * finiraient par diverger, et c'est sur mobile que l'oubli se verrait en
 * dernier — personne ne teste le téléphone en premier.
 */
class NavigationComposer
{
    public function compose(View $view): void
    {
        $user = auth()->user();

        if (! $user) {
            $view->with(['navHome' => url('/'), 'navLinks' => []]);

            return;
        }

        $home = $user->isCreator() ? route('creator.dashboard') : route('dashboard');

        $links = $user->isCreator()
            ? [
                [
                    'route' => 'creator.dashboard',
                    'pattern' => 'creator.dashboard',
                    'label' => 'Mes campagnes',
                    'icon' => 'M3 11l8-8 8 8M5 10v9a1 1 0 001 1h3v-6h4v6h3a1 1 0 001-1v-9',
                ],
                [
                    'route' => 'creator.profile.edit',
                    'pattern' => 'creator.profile.*',
                    'label' => 'Ma fiche',
                    'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                ],
            ]
            : [
                [
                    'route' => 'dashboard',
                    'pattern' => 'dashboard',
                    'label' => 'Tableau de bord',
                    'icon' => 'M4 5a1 1 0 011-1h5v7H4V5zm0 9h6v6H5a1 1 0 01-1-1v-5zm10-10h5a1 1 0 011 1v4h-6V4zm0 7h6v8a1 1 0 01-1 1h-5v-9z',
                ],
                [
                    'route' => 'campaigns.index',
                    'pattern' => 'campaigns.*',
                    'label' => 'Campagnes',
                    'icon' => 'M3 10v4a1 1 0 001 1h3l5 4V5L7 9H4a1 1 0 00-1 1zm13.5-3a5 5 0 010 10M19 4a9 9 0 010 16',
                ],
                [
                    'route' => 'clips.index',
                    'pattern' => 'clips.*',
                    'label' => 'Mes clips',
                    'icon' => 'M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zm0 4h16M8 5v4m8-4v4m-6 4.5l4 2.5-4 2.5v-5z',
                ],
                [
                    'route' => 'accounts.index',
                    'pattern' => 'accounts.*',
                    'label' => 'Mes comptes',
                    'icon' => 'M10 13a4 4 0 005.66 0l3-3a4 4 0 10-5.66-5.66l-1 1M14 11a4 4 0 00-5.66 0l-3 3a4 4 0 105.66 5.66l1-1',
                ],
                [
                    'route' => 'earnings.index',
                    'pattern' => 'earnings.*',
                    'label' => 'Revenus',
                    'icon' => 'M3 8a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm13 4a2 2 0 11-4 0 2 2 0 014 0z',
                ],
                [
                    'route' => 'referrals.index',
                    'pattern' => 'referrals.*',
                    'label' => 'Parrainage',
                    'icon' => 'M15 11a3 3 0 11-6 0 3 3 0 016 0zm-9 9a6 6 0 0112 0M18 8h4m-2-2v4',
                ],
                [
                    'route' => 'leaderboard.index',
                    'pattern' => 'leaderboard.*',
                    'label' => 'Classement',
                    'icon' => 'M4 20h4v-7H4v7zm6 0h4V5h-4v15zm6 0h4v-11h-4v11z',
                ],
                [
                    'route' => 'achievements.index',
                    'pattern' => 'achievements.*',
                    'label' => 'Succès',
                    'icon' => 'M12 3l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.2l5.9-.9L12 3z',
                ],
            ];

        $view->with(['navHome' => $home, 'navLinks' => $links]);
    }
}
