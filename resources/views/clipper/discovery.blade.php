<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Découverte</p>
                <h1 class="mt-1 font-display text-2xl font-bold text-ink-50">Trouver le moment à clipper</h1>
                <p class="mt-1 max-w-2xl text-ink-300">
                    Les clips les plus vus des streamers qui ont une campagne ouverte ici —
                    donc ceux pour lesquels vous serez payé.
                </p>
            </div>

            @if ($simule)
                <span class="chip-wait">Résultats de démonstration</span>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        @if (! $utilisable)
            <div class="alert-warn">
                <p class="font-semibold">La recherche n'est pas encore branchée sur Twitch</p>
                <p class="mt-1 leading-relaxed">
                    Il manque les identifiants de l'application Twitch de la plateforme.
                    Mieux vaut cet écran vide qu'une liste de clips inventés : vous perdriez
                    du temps à chercher des moments qui n'existent pas.
                </p>
            </div>
        @elseif ($simule)
            <div class="alert-warn">
                <p class="font-semibold">Ces clips sont inventés</p>
                <p class="mt-1 leading-relaxed">
                    Le pilote simulé tourne faute d'identifiants Twitch : les titres, les vues
                    et les durées sont crédibles mais faux. Les liens mènent à la page de clips
                    de la chaîne, pas au moment affiché.
                </p>
            </div>
        @endif

        @livewire('clip-discovery')

        {{--
            L'outil de montage.

            Rien à télécharger depuis cet écran : le dépôt s'ouvre dans une
            nouvelle page, et c'est tout. Le rendu vertical reste sur la
            machine du clippeur — il occupe un processeur entier une à deux
            minutes, et le faire sur le serveur qui sert aussi le site le
            rendrait lent pour tout le monde dès deux clippeurs simultanés.

            La carte se soulève et son liseré s'éclaire au survol : le
            mouvement annonce qu'on quitte le site, avant le clic.
        --}}
        <a href="https://github.com/ILIAS-ma/fetch_clip" target="_blank" rel="noopener"
           class="depart-card group block p-6 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Montage vertical</p>

                    <h2 class="mt-1.5 flex items-center gap-2 font-display text-lg font-bold text-ink-50">
                        FetchClip
                        {{-- La flèche avance d'un cheveu au survol : assez pour
                             dire « ça part ailleurs », pas assez pour sauter. --}}
                        <span class="depart-arrow" aria-hidden="true">↗</span>
                    </h2>

                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-ink-300">
                        Récupère le mp4, le recadre en 9:16, ajoute vos textes et sort un fichier
                        prêt à téléverser. Ouvre la page du projet dans un nouvel onglet.
                    </p>
                </div>

                <ol class="depart-steps">
                    <li><span>1</span> Copier les pseudos trouvés ci-dessus</li>
                    <li><span>2</span> Télécharger, monter en 9:16</li>
                    <li><span>3</span> Publier, puis coller l'adresse dans la campagne</li>
                </ol>
            </div>

            <p class="hint mt-6">
                Un clip n'existe sur Twitch que si la chaîne autorise le clipping : c'est un
                réglage que le streamer peut couper. Citez la chaîne source dans votre légende.
            </p>
        </a>

    </div>
</x-app-layout>
