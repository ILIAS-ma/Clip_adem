<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Détection de vues suspectes
    |--------------------------------------------------------------------------
    |
    | Ces seuils ne bloquent rien automatiquement : ils remontent les clips en
    | tête de file de modération. Un faux positif coûte une vérification, un
    | faux négatif coûte de l'argent — d'où des seuils volontairement larges.
    |
    */

    'suspicious' => [
        // Bond entre deux relevés espacés de moins de N heures.
        'spike_window_hours' => 6,

        // Il faut à la fois un facteur de croissance et un volume absolu :
        // passer de 10 à 200 vues n'a rien de suspect.
        'spike_factor' => 5,
        'spike_min_views' => 10_000,

        // Vues accumulées dans l'heure qui suit la publication.
        'cold_start_minutes' => 60,
        'cold_start_views' => 50_000,

        // Rapport vues / abonnés du compte lié.
        'views_per_follower' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retraits
    |--------------------------------------------------------------------------
    */

    'payouts' => [
        // Montant minimum d'une demande de retrait, en centimes.
        'minimum_cents' => 1_000,

        // En dessous de ce montant, un retrait peut être validé
        // automatiquement si le clippeur n'a aucun incident de modération.
        'auto_approve_below_cents' => 5_000,

        // Nombre maximum d'items dans un lot PayPal Payouts.
        'batch_size' => 250,
    ],

];
