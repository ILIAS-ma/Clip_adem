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
    <div class="lg:grid lg:min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">

        {{-- Panneau de marque : il porte la proposition de valeur pendant que
             l'utilisateur remplit le formulaire. Masqué sur mobile, où il
             pousserait le champ e-mail sous la ligne de flottaison. --}}
        <aside class="relative hidden overflow-hidden bg-ink-900 p-12 lg:flex lg:flex-col lg:justify-between">
            <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-500/10 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-money-500/10 blur-3xl"></div>

            <a href="{{ route('home') }}" class="relative">
                <x-brand-mark tone="light" />
            </a>

            <div class="relative max-w-md">
                <h1 class="font-display text-4xl font-bold leading-[1.1] text-white">
                    Vos clips font la promo,<br>
                    <span class="text-brand-400">vos vues font le reste.</span>
                </h1>
                <p class="mt-5 text-base leading-relaxed text-ink-300">
                    Rejoignez une campagne, publiez sur TikTok, YouTube ou Instagram, et soyez payé
                    selon les vues générées.
                </p>

                <dl class="mt-10 grid grid-cols-2 gap-6 border-t border-white/10 pt-8">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-widest text-ink-400">Rémunération</dt>
                        <dd class="mt-1 font-display text-2xl font-bold text-white">Aux 1000 vues</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-widest text-ink-400">Versement</dt>
                        <dd class="mt-1 font-display text-2xl font-bold text-white">PayPal</dd>
                    </div>
                </dl>
            </div>

            {{-- Dit d'emblée ce qui surprend le plus : le budget est fini. --}}
            <p class="relative max-w-md text-sm leading-relaxed text-ink-400">
                Le budget d'une campagne est limité et se consomme au fil des vues, premier arrivé
                premier servi.
            </p>
        </aside>

        <main class="flex min-h-screen flex-col justify-center px-6 py-12 sm:px-12 lg:px-16">
            <div class="mx-auto w-full max-w-md">
                <a href="{{ route('home') }}" class="mb-10 inline-block lg:hidden">
                    <x-brand-mark />
                </a>

                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
