{{--
    Preuve de propriété du domaine, méthode « balise meta ».

    TikTok et Google proposent au choix un fichier déposé à la racine ou cette
    balise. Le fichier vit dans `public/` ; celle-ci est ici, pilotée par
    l'environnement, pour que changer de domaine — un tunnel de développement
    en change à chaque redémarrage — ne demande pas de toucher au code.

    Vide par défaut : rien n'est rendu tant qu'aucun jeton n'est configuré.
--}}
@if ($token = config('services.tiktok.site_verification'))
    <meta name="tiktok-developers-site-verification" content="{{ $token }}" />
@endif

@if ($google = config('services.google.site_verification'))
    <meta name="google-site-verification" content="{{ $google }}" />
@endif
