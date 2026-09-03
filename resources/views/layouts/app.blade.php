<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|bricolage-grotesque:600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div class="min-h-screen">
        @include('layouts.navigation')

        @php
            // Une seule requête pour le bandeau, sur toutes les pages : un jeton
            // mort est la panne la plus silencieuse du système, elle ne doit pas
            // dépendre de la page où l'on se trouve. Un artiste n'a pas de
            // comptes réseaux : la requête ne le concerne pas.
            $needsReconnect = auth()->check() && auth()->user()->isClipper()
                ? auth()->user()->socialAccounts()->where('needs_reconnect', true)->count()
                : 0;
        @endphp

        @if ($needsReconnect > 0 && ! request()->routeIs('accounts.*'))
            <div class="bg-red-600">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2.5 text-sm text-white sm:px-6 lg:px-8">
                    <span class="font-semibold">
                        {{ $needsReconnect }} compte{{ $needsReconnect > 1 ? 's' : '' }} à reconnecter
                    </span>
                    <span class="text-white/80">Vos vues ne sont plus comptées dessus.</span>
                    <a href="{{ route('accounts.index') }}" class="ms-auto font-semibold underline underline-offset-2">
                        Reconnecter
                    </a>
                </div>
            </div>
        @endif

        @isset($header)
            <header class="border-b border-ink-100 bg-white">
                <div class="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main class="pb-16">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
