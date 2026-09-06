<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#101210">
    <title>{{ $title ?? config('app.name') }}</title>

    <x-favicon />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|bricolage-grotesque:600,700|fraunces:600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-900">
    <x-onboarding-suspended-notice />

    <div class="lg:grid lg:min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">

        {{-- Panneau de marque : il porte la proposition de valeur pendant que
             l'utilisateur remplit le formulaire. Masqué sur mobile, où il
             pousserait le champ e-mail sous la ligne de flottaison.

             Repris de la landing page : halos lime asymétriques, grille de
             points discrète, titre en serif avec dégradé sur la deuxième
             ligne — la même signature visuelle plutôt qu'un aplat de couleur
             qui écraserait le reste de l'interface. --}}
        <aside class="relative isolate hidden overflow-hidden border-e border-ink-800 bg-ink-800 p-12 lg:flex lg:flex-col lg:justify-between">
            <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-brand-500/20 blur-[100px]"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-brand-300/12 blur-[100px]"></div>
            <div aria-hidden="true"
                 class="pointer-events-none absolute inset-0 opacity-[.3]"
                 style="background-image: radial-gradient(rgba(147,206,46,.3) 1px, transparent 1px); background-size: 32px 32px; mask-image: radial-gradient(ellipse 70% 60% at 30% 20%, black, transparent);"></div>

            <a href="{{ route('home') }}" class="relative">
                <x-brand-mark size="lg" />
            </a>

            <div class="animate-fade-slide-in-1 relative max-w-lg">
                <h1 class="font-serif text-5xl font-semibold leading-[1.1] tracking-tight text-ink-50">
                    Vos clips font la promo,<br>
                    <span class="bg-gradient-to-r from-brand-300 via-brand-500 to-brand-300 bg-clip-text text-transparent">vos vues font le reste.</span>
                </h1>
                <p class="mt-6 text-lg leading-relaxed text-ink-200">
                    Choisissez une campagne, publiez sur TikTok, YouTube ou Instagram, et touchez
                    un cachet à chaque palier de vues.
                </p>

                {{-- Un mot qui tourne plutôt qu'une liste de chiffres figée :
                     montre que la plateforme couvre plusieurs plateformes
                     sans occuper plus de place qu'une ligne de texte. --}}
                <div x-data="{ words: ['TikTok', 'YouTube', 'Instagram'], index: 0 }"
                     x-init="setInterval(() => index = (index + 1) % words.length, 2200)"
                     class="mt-12 flex flex-wrap items-baseline gap-x-2 border-t border-ink-700 pt-8 text-lg text-ink-200">
                    <span>Payé pour vos clips sur</span>
                    <span class="relative inline-grid">
                        <template x-for="(word, i) in words" :key="i">
                            <span x-show="index === i"
                                  x-transition:enter="transition ease-out duration-300"
                                  x-transition:enter-start="opacity-0 -translate-y-1"
                                  x-transition:enter-end="opacity-100 translate-y-0"
                                  x-transition:leave="transition ease-in duration-200"
                                  x-transition:leave-start="opacity-100 translate-y-0"
                                  x-transition:leave-end="opacity-0 translate-y-1"
                                  class="[grid-area:1/1] font-display font-bold text-brand-400"
                                  x-text="word"></span>
                        </template>
                    </span>
                </div>
            </div>

            <p class="animate-fade-slide-in-2 relative max-w-md text-sm leading-relaxed text-ink-400">
                Aucune audience minimum : c'est la vue qui est payée, pas le nombre d'abonnés.
            </p>
        </aside>

        <main class="flex min-h-screen flex-col justify-center bg-ink-900 px-6 py-12 sm:px-12 lg:px-16">
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
