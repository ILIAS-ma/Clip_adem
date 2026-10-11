{{--
    Gabarit du mode créateur.

    Plein écran, sa propre grille à deux colonnes, sa propre feuille de style
    claire. Il ne reprend pas la mise en page du site, et c'est délibéré : la
    colonne latérale noire du site plus celle de l'outil feraient deux barres
    côte à côte, chacune annonçant une navigation différente. Un mode prend
    l'écran, puis on le quitte.

    La sortie est donc toujours visible, en bas de la barre : un mode sans
    porte de sortie visible se referme en tâtonnant.
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
    <div class="app">
        <aside class="sidebar">
            <div class="brand">
                {{-- Un monogramme, jamais une image ni un emoji. --}}
                <span class="logo">C</span>
                <div class="brand-text">
                    <h1>Mode créateur</h1>
                    <p>Repérer · Couper · Publier</p>
                </div>
            </div>

            <nav class="nav" aria-label="Sections">
                <a href="{{ route('discovery.index') }}" class="tab active">Découverte</a>
            </nav>

            <div class="side-scroll">
                {{ $rail ?? '' }}
            </div>

            {{-- L'état du compte, puis la sortie. Dans cet ordre : on vérifie
                 qu'on est en mesure de travailler avant de penser à partir. --}}
            <div class="status">
                {{ $status ?? '' }}
            </div>

            <a href="{{ route('dashboard') }}" class="btn ghost full">
                <svg class="ico" viewBox="0 0 16 16" aria-hidden="true">
                    <path d="M9.5 3.5 5 8l4.5 4.5" />
                </svg>
                Quitter le mode
            </a>
        </aside>

        <main class="main">
            <div class="stack">
                {{ $slot }}
            </div>
        </main>
    </div>

    {{-- Les scripts de Livewire — et Alpine avec eux — sont injectés
         d'office (AppServiceProvider::boot, forceAssetInjection) : les
         appeler ici en ferait deux copies, et la seconde instance d'Alpine
         casserait tout en silence. --}}
</body>
</html>
