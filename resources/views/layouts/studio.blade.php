{{--
    Gabarit du mode créateur : le document, rien de plus.

    Les deux colonnes sont rendues par le composant Livewire et non par ce
    gabarit, pour une raison précise : le rail porte le classement des directs,
    et chaque ligne y est cliquable — elle charge les clips de la chaîne. Un
    rail posé dans un slot de gabarit vit hors du composant, donc ne se
    rafraîchit pas et ne peut rien déclencher. C'était le défaut de la version
    précédente : un classement figé au chargement, décoratif.

    Plein écran et sa propre feuille de style claire. La colonne noire du site
    plus celle de l'outil feraient deux barres côte à côte, chacune annonçant
    une navigation différente. Un mode prend l'écran, puis on le quitte.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Mode créateur' }} — {{ config('app.name') }}</title>

    <meta name="theme-color" content="#ffffff">

    <x-favicon />

    @vite(['resources/css/studio.css', 'resources/js/studio.js'])
</head>
<body>
    {{ $slot }}

    {{-- Les scripts de Livewire — et Alpine avec eux — sont injectés d'office
         (AppServiceProvider::boot, forceAssetInjection) : les appeler ici en
         ferait deux copies, et la seconde instance d'Alpine casserait tout en
         silence. --}}
</body>
</html>
