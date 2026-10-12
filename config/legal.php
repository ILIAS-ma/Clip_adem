<?php

/*
|--------------------------------------------------------------------------
| Mentions légales
|--------------------------------------------------------------------------
|
| Obligatoires pour tout site professionnel en France (loi pour la confiance
| dans l'économie numérique, art. 6). Lues du .env : l'identité de l'éditeur
| change avec sa situation — micro-entreprise, puis société — sans qu'il
| faille toucher au code, et le dépôt public n'a pas à porter une adresse
| personnelle.
|
*/

return [

    'publisher' => [
        // Nom et prénom, ou raison sociale.
        'name' => env('LEGAL_PUBLISHER_NAME'),

        // « Entrepreneur individuel », « SAS au capital de 1 000 € »…
        'status' => env('LEGAL_PUBLISHER_STATUS'),

        'siret' => env('LEGAL_SIRET'),

        // Le numéro de TVA intracommunautaire, s'il y en a un.
        'vat' => env('LEGAL_VAT'),

        'address' => env('LEGAL_ADDRESS'),
        'phone' => env('LEGAL_PHONE'),
        'email' => env('LEGAL_EMAIL', env('MAIL_FROM_ADDRESS')),

        // Le directeur de la publication : l'éditeur lui-même, ou le
        // représentant légal de la société.
        'director' => env('LEGAL_DIRECTOR', env('LEGAL_PUBLISHER_NAME')),
    ],

    // L'hébergeur du serveur. Informations publiques d'OVH.
    'host' => [
        'name' => env('LEGAL_HOST_NAME', 'OVH SAS'),
        'details' => env('LEGAL_HOST_DETAILS', 'SAS au capital de 50 000 000 € — RCS Lille Métropole 424 761 419'),
        'address' => env('LEGAL_HOST_ADDRESS', '2 rue Kellermann, 59100 Roubaix, France'),
        'phone' => env('LEGAL_HOST_PHONE', '1007'),
        'website' => env('LEGAL_HOST_WEBSITE', 'https://www.ovhcloud.com'),
    ],

];
