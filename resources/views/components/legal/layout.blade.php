<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    <meta name="theme-color" content="#101210">

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
<body class="bg-ink-950 text-ink-100 antialiased">
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:py-16">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-sm text-ink-400 hover:text-ink-100">
            ← Retour à {{ config('app.name') }}
        </a>

        <h1 class="mt-8 font-display text-3xl font-bold text-ink-50 sm:text-4xl">{{ $title }}</h1>
        <p class="mt-2 text-sm text-ink-400">Dernière mise à jour : {{ $updatedAt }}</p>

        <div class="legal mt-10 space-y-8">
            {{ $slot }}
        </div>

        <footer class="mt-16 border-t border-ink-700 pt-6 text-sm text-ink-500">
            <p>
                Une question sur ce document ?
                <a href="mailto:{{ $contactEmail }}" class="text-brand-400 underline underline-offset-2">{{ $contactEmail }}</a>
            </p>
            <p class="mt-2">
                <a href="{{ route('legal.terms') }}" class="hover:text-ink-200">Conditions d'utilisation</a>
                <span aria-hidden="true"> · </span>
                <a href="{{ route('legal.privacy') }}" class="hover:text-ink-200">Politique de confidentialité</a>
            </p>
        </footer>
    </div>
</body>
</html>
