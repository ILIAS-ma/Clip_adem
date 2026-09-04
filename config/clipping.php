<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passages obligés du parcours
    |--------------------------------------------------------------------------
    |
    | Les suspendre permet de parcourir l'interface sans obstacle pendant le
    | développement. Le code des contrôles reste actif et testé — la suite de
    | tests les force à `true` — mais ils ne bloquent plus la navigation.
    |
    | À rétablir impérativement avant toute mise en ligne : sans vérification
    | d'e-mail, une adresse jetable rend le bannissement inopérant ; sans 2FA,
    | un compte admin compromis donne accès aux paiements.
    |
    */

    'onboarding' => [
        'require_email_verification' => env('REQUIRE_EMAIL_VERIFICATION', true),
        'require_complete_profile' => env('REQUIRE_COMPLETE_PROFILE', true),
        'require_admin_2fa' => env('REQUIRE_ADMIN_2FA', true),

        // Une fiche créateur créée depuis l'inscription publique naît inactive
        // et attend un administrateur. Suspendre ce contrôle la rend active
        // immédiatement — pratique pour parcourir l'espace créateur, mais à
        // rétablir avant l'ouverture : sans lui, n'importe qui apparaît au
        // catalogue sous le nom de scène qu'il veut.
        'require_creator_validation' => env('REQUIRE_CREATOR_VALIDATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Progression des clippeurs
    |--------------------------------------------------------------------------
    |
    | L'expérience se calcule sur les vues RÉMUNÉRÉES, jamais sur les vues
    | brutes : `clips.paid_views` ne compte que ce que le moteur de budget a
    | réellement crédité, et une invalidation le remet à zéro. Un niveau ne peut
    | donc pas servir à blanchir des vues achetées.
    |
    | Les seuils sont provisoires : à recaler sur les données réelles dès qu'il
    | y aura du volume.
    |
    */

    'progression' => [
        // Formule : vues payées, plus des bonus de régularité, moins un malus
        // qui rend une invalidation réellement coûteuse.
        'xp_per_approved_clip' => 2_000,
        'xp_per_campaign' => 5_000,
        'xp_penalty_per_invalidated_clip' => 20_000,

        // Fenêtre d'activité qui conditionne les avantages. Le niveau, lui,
        // reste acquis : c'est un trophée, pas un privilège.
        'activity_window_days' => 90,
    ],

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
    | Conformité au brief
    |--------------------------------------------------------------------------
    |
    | Les contrôles produisent un rapport, jamais une validation : la décision
    | reste manuelle.
    |
    */

    'compliance' => [
        'min_duration_seconds' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronisation des vues
    |--------------------------------------------------------------------------
    |
    | Cadence dégressive : un clip de moins de 48 h bouge vite, un clip d'un
    | mois ne bouge plus. Sans ça, la consommation de quota serait dix fois
    | supérieure pour la même information.
    |
    */

    'sync' => [
        // Un clip publié depuis moins de N heures est relevé toutes les
        // `fresh_interval_hours`.
        'fresh_window_hours' => 168,   // 7 jours
        'fresh_interval_hours' => 3,

        // Au-delà, une fois par jour…
        'mature_interval_hours' => 24,

        // …jusqu'à cet âge, après quoi on arrête de relever.
        'stop_after_days' => 30,

        // Un clip qui ne rapporte plus rien (budget épuisé, plafond atteint)
        // n'a pas besoin d'être relevé plus d'une fois par jour.
        'unpayable_interval_hours' => 24,
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
