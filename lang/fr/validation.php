<?php

/*
 * Messages de validation.
 *
 * Sans ce fichier, chaque formulaire du site affichait la clé brute :
 * « validation.required » au lieu de « Ce champ est obligatoire ». Laravel ne
 * livre plus ces traductions depuis la version 11 ; `php artisan lang:publish`
 * publie l'anglais, le français est à écrire.
 *
 * Deux partis pris de rédaction :
 *
 *  - On tutoie la contrainte, pas la personne : « doit contenir au moins
 *    :min caractères » dit quoi faire, là où « est invalide » laisse deviner.
 *  - Les noms de champs sont traduits en bas de fichier, dans `attributes`.
 *    Sans cela, un clippeur lit « Le champ paypal_email est obligatoire » :
 *    le nom de la colonne en base n'est pas du français.
 */

return [

    'accepted' => 'Le champ :attribute doit être accepté.',
    'accepted_if' => 'Le champ :attribute doit être accepté quand :other vaut :value.',
    'active_url' => 'Le champ :attribute doit être une URL valide.',
    'after' => 'Le champ :attribute doit être une date postérieure au :date.',
    'after_or_equal' => 'Le champ :attribute doit être une date postérieure ou égale au :date.',
    'alpha' => 'Le champ :attribute ne doit contenir que des lettres.',
    'alpha_dash' => 'Le champ :attribute ne doit contenir que des lettres, chiffres, tirets et underscores.',
    'alpha_num' => 'Le champ :attribute ne doit contenir que des lettres et des chiffres.',
    'any_of' => 'Le champ :attribute est invalide.',
    'array' => 'Le champ :attribute doit être une liste.',
    'array_keys' => 'Le champ :attribute doit contenir les entrées : :values.',
    'ascii' => 'Le champ :attribute ne doit contenir que des caractères alphanumériques et des symboles simples.',
    'base64' => 'Le champ :attribute doit être encodé en base64.',
    'before' => 'Le champ :attribute doit être une date antérieure au :date.',
    'before_or_equal' => 'Le champ :attribute doit être une date antérieure ou égale au :date.',

    'between' => [
        'array' => 'Le champ :attribute doit contenir entre :min et :max éléments.',
        'file' => 'Le fichier :attribute doit peser entre :min et :max kilo-octets.',
        'numeric' => 'Le champ :attribute doit être compris entre :min et :max.',
        'string' => 'Le champ :attribute doit contenir entre :min et :max caractères.',
    ],

    'boolean' => 'Le champ :attribute doit valoir vrai ou faux.',
    'can' => 'Le champ :attribute contient une valeur non autorisée.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'contains' => 'Il manque une valeur au champ :attribute.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => 'Le champ :attribute doit être une date valide.',
    'date_equals' => 'Le champ :attribute doit être une date égale au :date.',
    'date_format' => 'Le champ :attribute ne correspond pas au format :format.',
    'decimal' => 'Le champ :attribute doit comporter :decimal décimales.',
    'declined' => 'Le champ :attribute doit être refusé.',
    'declined_if' => 'Le champ :attribute doit être refusé quand :other vaut :value.',
    'different' => 'Les champs :attribute et :other doivent être différents.',
    'digits' => 'Le champ :attribute doit comporter :digits chiffres.',
    'digits_between' => 'Le champ :attribute doit comporter entre :min et :max chiffres.',
    'dimensions' => 'Les dimensions de l’image :attribute ne conviennent pas.',
    'distinct' => 'Le champ :attribute contient une valeur en double.',
    'doesnt_contain' => 'Le champ :attribute ne doit contenir aucune de ces valeurs : :values.',
    'doesnt_end_with' => 'Le champ :attribute ne doit pas se terminer par : :values.',
    'doesnt_start_with' => 'Le champ :attribute ne doit pas commencer par : :values.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'encoding' => 'Le champ :attribute doit être encodé en :encoding.',
    'ends_with' => 'Le champ :attribute doit se terminer par l’un de ces éléments : :values.',
    'enum' => 'La valeur choisie pour :attribute n’est pas valide.',
    'exists' => 'La valeur choisie pour :attribute n’existe pas.',
    'extensions' => 'Le fichier :attribute doit avoir l’une de ces extensions : :values.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'filled' => 'Le champ :attribute doit être renseigné.',

    'gt' => [
        'array' => 'Le champ :attribute doit contenir plus de :value éléments.',
        'file' => 'Le fichier :attribute doit peser plus de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être supérieur à :value.',
        'string' => 'Le champ :attribute doit contenir plus de :value caractères.',
    ],

    'gte' => [
        'array' => 'Le champ :attribute doit contenir au moins :value éléments.',
        'file' => 'Le fichier :attribute doit peser au moins :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :value.',
        'string' => 'Le champ :attribute doit contenir au moins :value caractères.',
    ],

    'hex_color' => 'Le champ :attribute doit être une couleur hexadécimale valide.',
    'image' => 'Le champ :attribute doit être une image.',
    'in' => 'La valeur choisie pour :attribute n’est pas valide.',
    'in_array' => 'Le champ :attribute doit exister dans :other.',
    'in_array_keys' => 'Le champ :attribute doit contenir au moins une de ces entrées : :values.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'ip' => 'Le champ :attribute doit être une adresse IP valide.',
    'ipv4' => 'Le champ :attribute doit être une adresse IPv4 valide.',
    'ipv6' => 'Le champ :attribute doit être une adresse IPv6 valide.',
    'json' => 'Le champ :attribute doit être du JSON valide.',
    'list' => 'Le champ :attribute doit être une liste.',
    'lowercase' => 'Le champ :attribute doit être en minuscules.',

    'lt' => [
        'array' => 'Le champ :attribute doit contenir moins de :value éléments.',
        'file' => 'Le fichier :attribute doit peser moins de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être inférieur à :value.',
        'string' => 'Le champ :attribute doit contenir moins de :value caractères.',
    ],

    'lte' => [
        'array' => 'Le champ :attribute ne doit pas contenir plus de :value éléments.',
        'file' => 'Le fichier :attribute ne doit pas peser plus de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être inférieur ou égal à :value.',
        'string' => 'Le champ :attribute ne doit pas contenir plus de :value caractères.',
    ],

    'mac_address' => 'Le champ :attribute doit être une adresse MAC valide.',

    'max' => [
        'array' => 'Le champ :attribute ne doit pas contenir plus de :max éléments.',
        'file' => 'Le fichier :attribute ne doit pas peser plus de :max kilo-octets.',
        'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
        'string' => 'Le champ :attribute ne doit pas contenir plus de :max caractères.',
    ],

    'max_digits' => 'Le champ :attribute ne doit pas comporter plus de :max chiffres.',
    'mimes' => 'Le fichier :attribute doit être de type : :values.',
    'mimetypes' => 'Le fichier :attribute doit être de type : :values.',

    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file' => 'Le fichier :attribute doit peser au moins :min kilo-octets.',
        'numeric' => 'Le champ :attribute doit valoir au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],

    'min_digits' => 'Le champ :attribute doit comporter au moins :min chiffres.',
    'missing' => 'Le champ :attribute doit être absent.',
    'missing_if' => 'Le champ :attribute doit être absent quand :other vaut :value.',
    'missing_unless' => 'Le champ :attribute doit être absent sauf si :other vaut :value.',
    'missing_with' => 'Le champ :attribute doit être absent quand :values est présent.',
    'missing_with_all' => 'Le champ :attribute doit être absent quand :values sont présents.',
    'multiple_of' => 'Le champ :attribute doit être un multiple de :value.',
    'not_in' => 'La valeur choisie pour :attribute n’est pas valide.',
    'not_regex' => 'Le format du champ :attribute n’est pas valide.',
    'numeric' => 'Le champ :attribute doit être un nombre.',

    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un caractère spécial.',
        'uncompromised' => 'Ce :attribute est apparu dans une fuite de données. Choisissez-en un autre.',
    ],

    'present' => 'Le champ :attribute doit être présent.',
    'present_if' => 'Le champ :attribute doit être présent quand :other vaut :value.',
    'present_unless' => 'Le champ :attribute doit être présent sauf si :other vaut :value.',
    'present_with' => 'Le champ :attribute doit être présent quand :values est présent.',
    'present_with_all' => 'Le champ :attribute doit être présent quand :values sont présents.',
    'prohibited' => 'Le champ :attribute est interdit.',
    'prohibited_if' => 'Le champ :attribute est interdit quand :other vaut :value.',
    'prohibited_if_accepted' => 'Le champ :attribute est interdit quand :other est accepté.',
    'prohibited_if_declined' => 'Le champ :attribute est interdit quand :other est refusé.',
    'prohibited_unless' => 'Le champ :attribute est interdit sauf si :other fait partie de :values.',
    'prohibits' => 'Le champ :attribute interdit la présence de :other.',
    'regex' => 'Le format du champ :attribute n’est pas valide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'required_array_keys' => 'Le champ :attribute doit contenir les entrées : :values.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'required_if_accepted' => 'Le champ :attribute est obligatoire quand :other est accepté.',
    'required_if_declined' => 'Le champ :attribute est obligatoire quand :other est refusé.',
    'required_unless' => 'Le champ :attribute est obligatoire sauf si :other fait partie de :values.',
    'required_with' => 'Le champ :attribute est obligatoire quand :values est présent.',
    'required_with_all' => 'Le champ :attribute est obligatoire quand :values sont présents.',
    'required_without' => 'Le champ :attribute est obligatoire quand :values est absent.',
    'required_without_all' => 'Le champ :attribute est obligatoire quand aucun de :values n’est présent.',
    'same' => 'Les champs :attribute et :other doivent être identiques.',

    'size' => [
        'array' => 'Le champ :attribute doit contenir :size éléments.',
        'file' => 'Le fichier :attribute doit peser :size kilo-octets.',
        'numeric' => 'Le champ :attribute doit valoir :size.',
        'string' => 'Le champ :attribute doit contenir :size caractères.',
    ],

    'starts_with' => 'Le champ :attribute doit commencer par l’un de ces éléments : :values.',
    'string' => 'Le champ :attribute doit être du texte.',
    'timezone' => 'Le champ :attribute doit être un fuseau horaire valide.',
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',
    'uploaded' => 'Le fichier :attribute n’a pas pu être envoyé. Vérifiez sa taille et réessayez.',
    'uppercase' => 'Le champ :attribute doit être en majuscules.',
    'url' => 'Le champ :attribute doit être une URL valide.',
    'ulid' => 'Le champ :attribute doit être un ULID valide.',
    'uuid' => 'Le champ :attribute doit être un UUID valide.',

    /*
     * Messages sur mesure, par champ et par règle.
     */
    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
     * Noms lisibles des champs.
     *
     * Sans eux, le message reprend le nom de la colonne : « Le champ
     * paypal_email est obligatoire » n'est pas du français, et « iban » en
     * minuscules ressemble à une faute.
     */
    'attributes' => [
        'first_name' => 'prénom',
        'last_name' => 'nom',
        'email' => 'adresse e-mail',
        'password' => 'mot de passe',
        'password_confirmation' => 'confirmation du mot de passe',
        'current_password' => 'mot de passe actuel',
        'pseudo' => 'pseudo',
        'country' => 'pays',
        'role' => 'rôle',
        'avatar' => 'photo de profil',

        'payout_method' => 'moyen de paiement',
        'paypal_email' => 'adresse PayPal',
        'iban' => 'IBAN',
        'bic' => 'BIC',
        'account_holder' => 'titulaire du compte',

        'title' => 'titre',
        'brief' => 'brief',
        'url' => 'lien de la vidéo',
        'platform' => 'plateforme',
        'required_hashtags' => 'hashtags obligatoires',

        'budget_total_cents' => 'budget total',
        'rate_per_1k_cents' => 'tarif aux 1000 vues',
        'min_views_per_clip' => 'vues minimales par clip',
        'max_payout_per_clip_cents' => 'plafond par clip',
        'max_payout_per_clipper_cents' => 'plafond par clippeur',
        'starts_at' => 'date de début',
        'ends_at' => 'date de fin',

        'amount_cents' => 'montant',
        'referral_code' => 'code de parrainage',
    ],

];
