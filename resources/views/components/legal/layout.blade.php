<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    <meta name="theme-color" content="#000000">

    {{-- Ces pages sont lues par des humains, mais aussi par les vérificateurs
         de TikTok et de Google, qui n'ont ni session ni cookie : elles restent
         donc publiques et sans état applicatif.

         Elles ne survivent pas pour autant à une panne de base : le pilote de
         session est `database`, et toute requête ouvre une session. Si une
         revue d'application échoue sur un 500 ici, regarder MySQL avant les
         vues. --}}
    <x-site-verification />

    <x-favicon />
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen flex-col bg-ink-950 text-ink-100 antialiased">
    {{-- Une vraie barre plutôt qu'un lien « retour » perdu en haut de page :
         on arrive souvent ici depuis un pied de page, et il faut pouvoir
         repartir vers son espace en un clic. --}}
    <header class="sticky top-0 z-30 border-b border-ink-800 bg-ink-950/85 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <x-brand-mark />
                <span class="font-display text-lg font-bold tracking-tight text-white">{{ config('app.name') }}</span>
            </a>

            <nav class="flex items-center gap-2 text-sm">
                @auth
                    <a href="{{ url(auth()->user()->role->homeRoute()) }}" class="btn-primary">Mon espace</a>
                @else
                    <a href="{{ route('login') }}" class="hidden px-3 py-2 text-ink-300 hover:text-ink-50 sm:inline">Se connecter</a>
                    <a href="{{ route('register') }}" class="btn-primary">Créer un compte</a>
                @endauth
            </nav>
        </div>
    </header>

    <div class="mx-auto w-full max-w-3xl flex-1 px-4 py-10 sm:px-6 lg:py-14">
        <nav class="flex flex-wrap gap-2 text-sm" aria-label="Documents légaux">
            @foreach ([
                'legal.notice' => 'Mentions légales',
                'legal.terms' => 'Conditions d\'utilisation',
                'legal.privacy' => 'Confidentialité',
            ] as $route => $libelle)
                <a href="{{ route($route) }}" @class([
                    'rounded-full border px-3 py-1 transition',
                    'border-brand-500/60 bg-brand-500/10 text-ink-50' => request()->routeIs($route),
                    'border-ink-700 text-ink-400 hover:border-ink-600 hover:text-ink-100' => ! request()->routeIs($route),
                ])>{{ $libelle }}</a>
            @endforeach
        </nav>

        <h1 class="mt-8 font-display text-3xl font-bold text-ink-50 sm:text-4xl">{{ $title }}</h1>
        <p class="mt-2 text-sm text-ink-400">Dernière mise à jour : {{ $updatedAt }}</p>

        <div class="legal mt-10 space-y-8">
            {{ $slot }}
        </div>

        <p class="mt-14 rounded-xl border border-ink-800 bg-ink-900 px-5 py-4 text-sm text-ink-400">
            Une question sur ce document ?
            <a href="mailto:{{ $contactEmail }}" class="text-brand-400 underline underline-offset-2">{{ $contactEmail }}</a>
        </p>
    </div>

    <x-site-footer />
</body>
</html>
