<?php

// Complète la traduction française de Filament, incomplète dans cette version :
// sans ces clés, l'admin affiche des noms techniques (« filament-tables::table.loading »).
// Fusionné avec les fichiers du paquet par Laravel, qui restent la référence.

return [
    'column_manager' => [
        'actions' => [
            'reorder' => [
                'label' => 'Réordonner la colonne',
            ],
        ],
    ],
    'columns' => [
        'icon' => [
            'boolean' => [
                'true' => 'Oui',
                'false' => 'Non',
            ],
        ],
        'select' => [
            'loading_message' => 'Chargement…',
            'no_options_message' => 'Aucune option disponible.',
            'no_search_results_message' => 'Aucune option ne correspond à votre recherche.',
            'placeholder' => 'Choisir une option',
            'searching_message' => 'Recherche…',
            'search_prompt' => 'Commencez à taper pour rechercher',
        ],
    ],
    'actions' => [
        'reorder_record' => [
            'label' => 'Déplacer l’élément :key',
        ],
        'toggle_record_content' => [
            'label' => 'Déplier/replier l’élément :key',
        ],
    ],
    'loading' => 'Chargement…',
    'result_count' => '{0} Aucun résultat|{1} :count résultat|[2,*] :count résultats',
];
