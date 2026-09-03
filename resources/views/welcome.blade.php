<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Gagnez de l'argent avec vos clips</title>

    <meta name="theme-color" content="#080908">

    <x-favicon />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|bricolage-grotesque:600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="border-b border-ink-800 bg-ink-950">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <x-brand-mark />

            <nav class="flex items-center gap-3">
                <a href="{{ route('home') }}" class="btn-primary">Se connecter</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="relative overflow-hidden border-b border-ink-800 bg-ink-900">
            <div aria-hidden="true" class="pointer-events-none absolute -right-40 -top-40 h-[32rem] w-[32rem] rounded-full bg-brand-500/12 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-48 -left-40 h-[32rem] w-[32rem] rounded-full bg-brand-500/8 blur-3xl"></div>

            <div class="relative mx-auto max-w-6xl px-6 py-24 sm:py-32">
                <div class="max-w-3xl">
                    <span class="chip bg-brand-500/15 text-brand-300">Rémunéré aux 1000 vues</span>

                    <h1 class="mt-6 font-display text-5xl font-bold leading-[1.05] text-ink-50 sm:text-6xl">
                        Vos clips font la promo,<br>
                        <span class="text-brand-500">vos vues font le reste.</span>
                    </h1>

                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-ink-300">
                        Rejoignez une campagne d'artiste, publiez votre clip sur TikTok, YouTube ou
                        Instagram, et soyez payé selon les vues générées — jusqu'à épuisement du budget.
                    </p>

                    {{-- Deux publics, deux portes d'entrée : un artiste qui
                         cherche à faire promouvoir sa musique n'a rien à faire
                         dans un parcours d'inscription de clippeur. --}}
                    <div class="mt-10 flex flex-wrap items-center gap-4">
                        <a href="{{ route('register') }}" class="btn-brand px-6 py-3 text-base">
                            Je suis clippeur
                        </a>
                        <a href="{{ route('register', ['profil' => 'artist']) }}"
                           class="btn border border-ink-600 px-6 py-3 text-base text-ink-100 hover:bg-ink-800">
                            Je suis artiste
                        </a>
                        <a href="{{ route('home') }}" class="btn px-6 py-3 text-base text-ink-400 hover:text-ink-50">
                            J'ai déjà un compte
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-6 py-20">
            <h2 class="font-display text-3xl font-bold text-ink-50">Comment ça marche</h2>

            <ol class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['Choisissez une campagne', "Chaque campagne affiche son cachet pour 1000 vues, son brief, et le budget qu'il lui reste en temps réel."],
                    ['Publiez et soumettez', "Vous publiez depuis votre propre compte, comme d'habitude. Il suffit ensuite de coller le lien de la publication."],
                    ['Suivez vos gains', 'Vos vues sont relevées automatiquement et créditées au fil du temps. Retrait sur PayPal dès 10 €.'],
                ] as $index => [$title, $text])
                    <li class="card p-6">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-500 font-display text-sm font-bold text-ink-950">
                            {{ $index + 1 }}
                        </span>
                        <h3 class="mt-5 font-display text-lg font-bold text-ink-50">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-300">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>

            {{-- Le point qui surprend le plus les nouveaux clippeurs, dit
                 franchement plutôt que découvert quand les gains s'arrêtent. --}}
            <div class="alert-warn mt-12 max-w-3xl">
                <p class="font-semibold">Le budget est fini, et il part au premier arrivé</p>
                <p class="mt-1 leading-relaxed">
                    Chaque campagne dispose d'un budget limité qui se consomme au fil des vues.
                    Quand il atteint zéro, la campagne se ferme : vos vues continuent d'être comptées
                    mais ne sont plus rémunérées. Publier tôt compte.
                </p>
            </div>
        </section>

        <section class="border-t border-ink-800 bg-ink-900">
            <div class="mx-auto grid max-w-6xl gap-8 px-6 py-16 sm:grid-cols-3">
                @foreach ([
                    ['TikTok · YouTube · Instagram', 'Liez vos comptes en un clic'],
                    ['Aux 1000 vues', 'Cachet annoncé avant de publier'],
                    ['PayPal', 'Retrait à partir de 10 €'],
                ] as [$value, $label])
                    <div>
                        <p class="font-display text-2xl font-bold text-ink-50">{{ $value }}</p>
                        <p class="mt-1 text-sm text-ink-300">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </main>

    <footer class="border-t border-ink-800 bg-ink-950">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-8 text-sm text-ink-400">
            <x-brand-mark />
            <p>{{ date('Y') }} — Plateforme de clipping</p>
        </div>
    </footer>
</body>
</html>
