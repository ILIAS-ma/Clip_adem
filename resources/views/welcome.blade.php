<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Gagnez de l'argent avec vos clips</title>

    <meta name="theme-color" content="#101210">

    {{-- Pose la classe avant le rendu : le CSS d'animation au scroll ne
         s'active que si elle est présente, pour ne jamais bloquer le
         contenu à opacité 0 si le JS échoue. --}}
    <script>document.documentElement.classList.add('js')</script>

    <x-site-verification />

    <x-favicon />

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|bricolage-grotesque:600,700|fraunces:600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ink-900">
    <main>
        <section x-data="{ mobileNavOpen: false }"
                 x-effect="document.body.classList.toggle('overflow-hidden', mobileNavOpen)"
                 class="relative isolate min-h-screen overflow-hidden border-b border-ink-800 bg-ink-900">
            {{-- Halos doux façon aurore, à la place d'un trait qui coupait le
                 regard en diagonale : deux masses de couleur asymétriques,
                 sans ligne dure qui traverse le texte. --}}
            <div aria-hidden="true" class="pointer-events-none absolute -left-24 -top-32 h-[34rem] w-[34rem] rounded-full bg-brand-500/25 blur-[110px]"></div>
            <div aria-hidden="true" class="pointer-events-none absolute right-[-10rem] top-1/3 h-[28rem] w-[28rem] rounded-full bg-brand-300/15 blur-[100px]"></div>
            <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-0 h-[36rem] w-[56rem] -translate-x-1/2 rounded-full bg-brand-500/10 blur-[130px]"></div>

            {{-- Grille de points, masquée en dégradé radial : un repère
                 discret plutôt qu'une texture qui distrait. --}}
            <div aria-hidden="true"
                 class="pointer-events-none absolute inset-0 opacity-[.35]"
                 style="background-image: radial-gradient(rgba(147,206,46,.3) 1px, transparent 1px); background-size: 32px 32px; mask-image: radial-gradient(ellipse 55% 45% at 50% 15%, black, transparent);"></div>

            <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-ink-950"></div>

            {{-- Nav flottante : centrée dans l'en-tête plutôt que collée au
                 logo, pour qu'elle ne paraisse pas plaquée sur le côté sur
                 les grands écrans. --}}
            <header class="relative z-20">
                <div class="relative mx-auto flex max-w-6xl items-center justify-between px-6 pt-6">
                    <x-brand-mark />

                    <nav class="absolute left-1/2 hidden -translate-x-1/2 items-center gap-1.5 rounded-full bg-ink-50/5 px-3 py-2.5 ring-1 ring-ink-50/10 backdrop-blur md:flex">
                        <a href="#comment-ca-marche" class="rounded-full px-5 py-2.5 text-sm font-medium text-ink-200 transition-colors hover:text-ink-50">Comment ça marche</a>
                        <a href="#pour-les-createurs" class="rounded-full px-5 py-2.5 text-sm font-medium text-ink-200 transition-colors hover:text-ink-50">Pour les créateurs</a>
                        <a href="#faq" class="rounded-full px-5 py-2.5 text-sm font-medium text-ink-200 transition-colors hover:text-ink-50">FAQ</a>
                    </nav>

                    <a href="{{ route('login') }}"
                       class="hidden items-center gap-2 rounded-full bg-brand-500 px-5 py-2.5 text-sm font-semibold text-ink-950 transition-colors hover:bg-brand-400 md:inline-flex">
                        Se connecter
                    </a>

                    <button @click="mobileNavOpen = !mobileNavOpen"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-ink-50/5 ring-1 ring-ink-50/10 backdrop-blur md:hidden"
                            :aria-expanded="mobileNavOpen" aria-label="Ouvrir le menu">
                        <svg x-show="!mobileNavOpen" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-ink-100"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/></svg>
                        <svg x-show="mobileNavOpen" x-cloak xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-ink-100"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>

                {{-- Rideau : assombrit le reste de la page et referme le
                     tiroir au clic, comme n'importe quel panneau mobile. --}}
                <div x-show="mobileNavOpen" x-cloak
                     x-transition:enter="transition-opacity ease-out duration-300"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition-opacity ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     @click="mobileNavOpen = false"
                     class="fixed inset-0 z-40 bg-ink-950/70 backdrop-blur-sm md:hidden"></div>

                {{-- Tiroir : glisse depuis la droite plutôt qu'un menu qui
                     dépliait la page vers le bas — plus proche de ce qu'un
                     visiteur attend d'un menu mobile aujourd'hui. --}}
                <div x-show="mobileNavOpen" x-cloak
                     x-transition:enter="transition-transform ease-out duration-300"
                     x-transition:enter-start="translate-x-full"
                     x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition-transform ease-in duration-200"
                     x-transition:leave-start="translate-x-0"
                     x-transition:leave-end="translate-x-full"
                     class="fixed inset-y-0 right-0 z-50 flex w-[80%] max-w-xs flex-col bg-ink-900 ring-1 ring-ink-800 md:hidden">
                    <div class="flex items-center justify-between px-6 pt-6">
                        <x-brand-mark />
                        <button @click="mobileNavOpen = false"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-ink-50/5 ring-1 ring-ink-50/10"
                                aria-label="Fermer le menu">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-ink-100"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>

                    {{-- Le texte glisse de droite à gauche, dans le sens
                         d'arrivée du tiroir, avec un temps de retard : il ne
                         bouge qu'une fois le tiroir posé, pas en même temps
                         que lui. --}}
                    <nav class="mt-6 flex flex-1 flex-col px-3">
                        <a href="#comment-ca-marche" @click="mobileNavOpen = false"
                           class="stagger-item-x border-b border-ink-800 px-3 py-4 text-xl font-medium text-ink-200 hover:text-ink-50"
                           style="--stagger-delay: 280ms">Comment ça marche</a>
                        <a href="#pour-les-createurs" @click="mobileNavOpen = false"
                           class="stagger-item-x border-b border-ink-800 px-3 py-4 text-xl font-medium text-ink-200 hover:text-ink-50"
                           style="--stagger-delay: 340ms">Pour les créateurs</a>
                        <a href="#faq" @click="mobileNavOpen = false"
                           class="stagger-item-x border-b border-ink-800 px-3 py-4 text-xl font-medium text-ink-200 hover:text-ink-50"
                           style="--stagger-delay: 400ms">FAQ</a>

                        <a href="{{ route('login') }}"
                           class="stagger-item-x mt-auto mb-6 inline-flex items-center justify-center gap-2 rounded-full bg-brand-500 px-5 py-3.5 text-xl font-semibold text-ink-950"
                           style="--stagger-delay: 460ms">
                            Se connecter
                        </a>
                    </nav>
                </div>
            </header>

            <div class="relative z-10 mx-auto max-w-3xl px-6 pb-24 pt-20 text-center sm:pb-32 sm:pt-28">
                <h1 class="font-serif text-6xl font-semibold leading-[1.05] tracking-tight text-ink-50 sm:text-7xl lg:text-8xl">
                    @foreach (explode(' ', 'Vos clips font la promo,') as $index => $word)
                        <span class="hero-word" style="--word-delay: {{ $index * 80 }}ms">{{ $word }}</span>{{ $loop->last ? '' : ' ' }}
                    @endforeach
                    <br class="hidden sm:block">
                    <span class="hero-gradient-text bg-gradient-to-r from-brand-300 via-brand-500 to-brand-300 bg-clip-text text-transparent">vos vues font le reste.</span>
                </h1>

                <p class="animate-fade-slide-in-3 mx-auto mt-7 max-w-xl text-lg leading-relaxed text-ink-200" style="animation-delay: .55s">
                    Choisissez une campagne, publiez votre clip sur TikTok, YouTube ou Instagram,
                    et touchez un cachet à chaque palier de vues — jusqu'à épuisement du budget.
                </p>

                {{-- Un seul CTA fort (clippeur, le public principal), un
                     second en retrait (créateur), et le retour visiteur
                     glissé en simple lien plutôt que noyé dans la rangée. --}}
                <div class="animate-fade-slide-in-4 mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row sm:gap-4" style="animation-delay: .7s">
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center gap-2 rounded-full bg-brand-500 px-7 py-3.5 text-base font-semibold text-ink-950 shadow-glow transition-transform hover:-translate-y-0.5 hover:bg-brand-400">
                        Je suis clippeur
                    </a>
                    <a href="{{ route('register', ['profil' => 'creator']) }}"
                       class="inline-flex items-center gap-2 rounded-full bg-ink-50/10 px-7 py-3.5 text-base font-medium text-ink-50 ring-1 ring-ink-50/15 backdrop-blur transition-colors hover:bg-ink-50/15">
                        Je suis créateur
                    </a>
                </div>

                <a href="{{ route('login') }}" class="animate-fade-slide-in-4 mt-5 inline-block text-sm font-medium text-ink-400 underline-offset-4 hover:text-ink-100 hover:underline">
                    J'ai déjà un compte
                </a>

                {{-- Bandeau de plateformes supportées. --}}
                <div class="animate-fade-slide-in-4 mx-auto mt-16 max-w-lg border-t border-ink-800/80 pt-10">
                    <p class="text-xs font-medium uppercase tracking-wider text-ink-500">Publiez depuis vos comptes habituels</p>
                    <div class="mt-5 grid grid-cols-3 items-center justify-items-center gap-3">
                        <span class="flex h-12 w-full items-center justify-center gap-2 rounded-full text-ink-200 ring-1 ring-ink-800 transition-all hover:text-ink-50 hover:ring-brand-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 5.82s.51.5 0 0A4.278 4.278 0 0 1 15.54 3h-3.09v12.4a2.592 2.592 0 0 1-2.59 2.5c-1.42 0-2.6-1.16-2.6-2.6 0-1.72 1.66-3.01 3.37-2.48V9.66c-3.45-.46-6.47 2.22-6.47 5.64 0 3.33 2.76 5.7 5.69 5.7 3.14 0 5.69-2.55 5.69-5.7V9.01a7.35 7.35 0 0 0 4.3 1.38V7.3s-1.88.09-3.24-1.48Z"/></svg>
                            <span class="text-sm font-semibold">TikTok</span>
                        </span>
                        <span class="flex h-12 w-full items-center justify-center gap-2 rounded-full text-ink-200 ring-1 ring-ink-800 transition-all hover:text-ink-50 hover:ring-brand-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M21.58 7.19a2.75 2.75 0 0 0-1.94-1.95C17.9 4.75 12 4.75 12 4.75s-5.9 0-7.64.49A2.75 2.75 0 0 0 2.42 7.2 28.6 28.6 0 0 0 2 12a28.6 28.6 0 0 0 .42 4.81 2.75 2.75 0 0 0 1.94 1.95c1.74.49 7.64.49 7.64.49s5.9 0 7.64-.49a2.75 2.75 0 0 0 1.94-1.95A28.6 28.6 0 0 0 22 12a28.6 28.6 0 0 0-.42-4.81ZM10 15.02V8.98L15.27 12 10 15.02Z"/></svg>
                            <span class="text-sm font-semibold">YouTube</span>
                        </span>
                        <span class="flex h-12 w-full items-center justify-center gap-2 rounded-full text-ink-200 ring-1 ring-ink-800 transition-all hover:text-ink-50 hover:ring-brand-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="3.6"/><circle cx="17.4" cy="6.6" r="1" fill="currentColor" stroke="none"/></svg>
                            <span class="text-sm font-semibold">Instagram</span>
                        </span>
                    </div>
                </div>
            </div>

        </section>

        <section id="comment-ca-marche" class="relative mx-auto max-w-6xl px-6 py-24 sm:py-32">
            <div data-reveal class="mx-auto max-w-2xl text-center">
                <span class="chip bg-brand-500/15 text-brand-300">Le principe</span>
                <h2 class="mt-4 font-display text-3xl font-bold text-ink-50 sm:text-4xl">Comment ça marche</h2>
                <p class="mt-3 text-ink-200">Trois étapes, sans surprise, du choix de la campagne au virement PayPal.</p>
            </div>

            <ol class="mt-14 grid gap-6 md:grid-cols-3">
                @foreach ([
                    [
                        'Choisissez une campagne',
                        "Chaque campagne affiche son cachet pour 1000 vues, son brief, et le budget qu'il lui reste en temps réel.",
                    ],
                    [
                        'Publiez et soumettez',
                        "Vous publiez depuis votre propre compte, comme d'habitude. Il suffit ensuite de coller le lien de la publication.",
                    ],
                    [
                        'Suivez vos gains',
                        'Vos vues sont relevées automatiquement et créditées au fil du temps. Retrait sur PayPal dès 20 €.',
                    ],
                ] as $index => [$title, $text])
                    <li data-reveal style="--reveal-delay: {{ $index * 120 }}ms"
                        class="card relative overflow-hidden bg-ink-800 p-6 transition-transform hover:-translate-y-1 hover:shadow-lifted">
                        <span class="font-serif text-4xl font-semibold text-brand-500/70">{{ sprintf('%02d', $index + 1) }}</span>

                        <h3 class="mt-4 font-display text-lg font-bold text-ink-50">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-200">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        <section id="chiffres" class="border-t border-ink-800 bg-ink-800">
            <div class="mx-auto grid max-w-6xl gap-8 px-6 py-20 sm:grid-cols-3">
                @foreach ([
                    ['TikTok · YouTube · Instagram', 'Liez vos comptes en un clic'],
                    ['Aux 1000 vues', 'Cachet annoncé avant de publier'],
                    ['PayPal', 'Retrait à partir de 20 €'],
                ] as $index => [$value, $label])
                    <div data-reveal style="--reveal-delay: {{ $index * 120 }}ms">
                        <p class="font-display text-2xl font-bold text-ink-50">{{ $value }}</p>
                        <p class="mt-1 text-sm text-ink-200">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Photo pleine largeur : coupe le rythme des sections en grille et
             donne un visage réel à ce qui reste sinon très abstrait. --}}
        <section class="relative isolate overflow-hidden">
            <img src="https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?auto=format&fit=crop&w=1920&q=70"
                 alt="Studio d'enregistrement"
                 class="h-72 w-full object-cover sm:h-96">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 bg-gradient-to-t from-ink-900 via-ink-900/40 to-transparent"></div>
            <div data-reveal class="pointer-events-none absolute inset-x-0 bottom-0 mx-auto max-w-6xl px-6 pb-8">
                <p class="max-w-md font-display text-xl font-bold text-ink-50 sm:text-2xl">
                    Des artistes et des streamers qui misent sur de vrais clippeurs, pas sur des vues achetées.
                </p>
            </div>
        </section>

        {{-- Pourquoi rester : ce qui distingue la plateforme d'un simple
             concours, dit en avantages concrets plutôt qu'en superlatifs. --}}
        <section class="mx-auto max-w-6xl px-6 py-24 sm:py-32">
            <div data-reveal class="mx-auto max-w-2xl text-center">
                <span class="chip bg-brand-500/15 text-brand-300">Pourquoi cette plateforme</span>
                <h2 class="mt-4 font-display text-3xl font-bold text-ink-50 sm:text-4xl">Pensée pour les clippeurs</h2>
                <p class="mt-3 text-ink-200">Pas de followers minimum, pas de dossier à monter : juste des vues, comptées et payées.</p>
            </div>

            <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['Aucune audience minimum', "Un compte tout neuf peut publier : c'est la vue qui est payée, pas le nombre d'abonnés."],
                    ['Budget transparent', "Chaque campagne affiche son budget restant en temps réel, avant que vous ne publiiez quoi que ce soit."],
                    ['Vues vérifiées automatiquement', "Les compteurs sont relevés directement sur vos publications, sans déclaration manuelle à faire."],
                    ['Retrait dès 20 €', 'Les gains se cumulent au fil des vues et se retirent sur PayPal sans palier élevé à atteindre.'],
                ] as $index => [$title, $text])
                    <div data-reveal style="--reveal-delay: {{ $index * 100 }}ms" class="card bg-ink-800 p-6">
                        <h3 class="font-display text-base font-bold text-ink-50">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-200">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Second public de la plateforme : le créateur qui finance les
             campagnes. Traité à part, sans partager le CTA du clippeur. --}}
        <section id="pour-les-createurs" class="border-t border-ink-800 bg-ink-800">
            <div class="mx-auto grid max-w-6xl items-center gap-12 px-6 py-24 sm:py-32 lg:grid-cols-2">
                <div data-reveal>
                    <span class="chip bg-brand-500/15 text-brand-300">Pour les créateurs</span>
                    <h2 class="mt-4 font-display text-3xl font-bold text-ink-50 sm:text-4xl">Votre contenu, porté par de vrais clippeurs</h2>
                    <p class="mt-4 leading-relaxed text-ink-200">
                        Artiste ou streamer, lancez une campagne, fixez votre cachet aux 1000 vues et
                        votre budget total. Les clippeurs publient depuis leurs propres comptes TikTok,
                        YouTube et Instagram : vous ne payez que pour les vues réellement générées,
                        jamais à l'avance.
                    </p>

                    <ul class="mt-8 space-y-4 border-l-2 border-brand-500/40 pl-5">
                        @foreach ([
                            'Budget maîtrisé — vous ne dépensez jamais plus que ce que vous avez fixé',
                            'Diffusion sur trois plateformes sans gérer un seul compte vous-même',
                            'Suivi des clips et des vues consultable à tout moment depuis votre espace',
                        ] as $point)
                            <li class="text-sm leading-relaxed text-ink-200">{{ $point }}</li>
                        @endforeach
                    </ul>

                    <a href="{{ route('register', ['profil' => 'creator']) }}" class="btn-brand mt-8 px-6 py-3 text-base">
                        Lancer une campagne
                    </a>
                </div>

                <div data-reveal style="--reveal-delay: 120ms" class="relative">
                    <div aria-hidden="true" class="pointer-events-none absolute -inset-6 rounded-full bg-brand-500/10 blur-3xl"></div>

                    <img src="https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?auto=format&fit=crop&w=900&q=70"
                         alt="Artiste sur scène"
                         class="relative mb-4 h-48 w-full rounded-2xl object-cover shadow-lifted sm:h-56">

                    <div class="card relative space-y-4 bg-ink-900 p-6">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-semibold text-ink-50">Campagne — Lancement</span>
                            <span class="chip-ok">Active</span>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-xs text-ink-400">
                                <span>Budget consommé</span>
                                <span class="tabular">620 € / 1 000 €</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-ink-800">
                                <div class="h-full w-[62%] rounded-full bg-brand-500"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 border-t border-ink-800 pt-4">
                            <div>
                                <p class="text-xs text-ink-400">Cachet</p>
                                <p class="mt-1 font-display text-lg font-bold text-ink-50 tabular">8,00 € <span class="text-xs font-normal text-ink-400">/ 1000 vues</span></p>
                            </div>
                            <div>
                                <p class="text-xs text-ink-400">Clips publiés</p>
                                <p class="mt-1 font-display text-lg font-bold text-ink-50 tabular">37</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- FAQ : répond aux objections qui bloquent l'inscription plutôt
             que de laisser le visiteur partir avec sa question. --}}
        <section id="faq" class="mx-auto max-w-3xl px-6 py-24 sm:py-32">
            <div data-reveal class="text-center">
                <span class="chip bg-brand-500/15 text-brand-300">Questions fréquentes</span>
                <h2 class="mt-4 font-display text-3xl font-bold text-ink-50 sm:text-4xl">Encore une question ?</h2>
            </div>

            <div data-reveal x-data="{ open: 0 }" class="mt-12 divide-y divide-ink-800 overflow-hidden rounded-2xl border border-ink-800">
                @foreach ([
                    ['Comment mes vues sont-elles comptées ?', "Une fois votre clip soumis, la plateforme relève automatiquement le compteur de vues de votre publication à intervalles réguliers, sans action de votre part."],
                    ['Quand suis-je payé ?', "Vos gains sont crédités sur votre solde au fil des relevés de vues. Vous pouvez retirer sur PayPal dès que votre solde atteint 20 €."],
                    ['Que se passe-t-il quand le budget d\'une campagne est épuisé ?', "La campagne se ferme aux nouvelles soumissions. Les clips déjà publiés continuent d'être suivis, mais les vues generées après épuisement du budget ne sont plus rémunérées."],
                    ['Puis-je publier sur plusieurs plateformes pour la même campagne ?', "Oui, tant que le brief de la campagne l'autorise : chaque plateforme liée (TikTok, YouTube, Instagram) peut recevoir sa propre publication."],
                    ['Y a-t-il des frais pour les clippeurs ?', "Non, l'inscription et la participation aux campagnes sont gratuites pour les clippeurs. Seul le retrait via PayPal peut être soumis aux frais habituels de PayPal."],
                ] as $index => [$question, $answer])
                    <div>
                        <button type="button" @click="open = open === {{ $index }} ? null : {{ $index }}"
                                class="flex w-full items-center justify-between gap-4 bg-ink-800 px-6 py-5 text-left transition-colors hover:bg-ink-700/60"
                                :aria-expanded="open === {{ $index }}">
                            <span class="font-medium text-ink-50">{{ $question }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 class="flex-none text-ink-400 transition-transform duration-300" :class="open === {{ $index }} ? 'rotate-45' : ''">
                                <path d="M12 5v14"/><path d="M5 12h14"/>
                            </svg>
                        </button>
                        <div class="grid bg-ink-800 transition-[grid-template-rows] duration-300 ease-out" :class="open === {{ $index }} ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
                            <div class="overflow-hidden">
                                <p class="px-6 pb-5 text-sm leading-relaxed text-ink-200">{{ $answer }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="border-t border-ink-800 bg-ink-800">
            <div data-reveal class="relative mx-auto max-w-4xl overflow-hidden rounded-3xl px-8 py-16 text-center sm:px-16 sm:py-20">
                <div aria-hidden="true" class="pointer-events-none absolute left-1/2 top-0 h-64 w-[36rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-brand-500/20 blur-[100px]"></div>
                <div class="relative">
                    <h2 class="font-display text-3xl font-bold text-ink-50 sm:text-4xl">Prêt à monétiser vos clips ?</h2>
                    <p class="mx-auto mt-3 max-w-lg text-ink-200">Le budget des campagnes part au premier arrivé — inscrivez-vous et publiez avant qu'il ne s'épuise.</p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
                        <a href="{{ route('register') }}" class="btn-brand px-7 py-3.5 text-base shadow-glow">Je suis clippeur</a>
                        <a href="{{ route('register', ['profil' => 'creator']) }}" class="btn border border-ink-600 px-7 py-3.5 text-base text-ink-100 hover:bg-ink-800">Je suis créateur</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-ink-800 bg-ink-900">
        <div class="mx-auto max-w-6xl px-6 py-12">
            <div class="flex flex-wrap items-start justify-between gap-8">
                <div>
                    <x-brand-mark />
                    <p class="mt-3 max-w-xs text-sm leading-relaxed text-ink-400">
                        Rémunère les clippeurs aux vues générées sur des campagnes de créateurs, budget limité et transparent.
                    </p>
                </div>

                <nav class="flex flex-wrap gap-x-8 gap-y-3 text-sm text-ink-400">
                    <a href="#comment-ca-marche" class="hover:text-ink-100">Comment ça marche</a>
                    <a href="#pour-les-createurs" class="hover:text-ink-100">Pour les créateurs</a>
                    <a href="#faq" class="hover:text-ink-100">FAQ</a>
                    <a href="{{ route('login') }}" class="hover:text-ink-100">Se connecter</a>
                </nav>
            </div>

            <div class="mt-10 flex flex-col gap-3 border-t border-ink-800 pt-6 text-xs text-ink-500 sm:flex-row sm:items-center sm:justify-between">
                <p>{{ date('Y') }} — Plateforme de clipping</p>
                <p>Retrait via PayPal · Frais PayPal habituels applicables</p>
            </div>
        </div>
    </footer>
</body>
</html>
