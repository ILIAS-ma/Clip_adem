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
            Le montage reste chez le clippeur, et il faut le dire franchement.
            Rendre une vidéo verticale demande une à deux minutes de calcul à
            plein régime ; le faire sur le serveur qui sert aussi le site le
            rendrait lent pour tout le monde dès deux clippeurs simultanés.
        --}}
        <div class="card p-6 sm:p-8">
            <h2 class="font-display text-lg font-bold text-ink-50">Télécharger et monter</h2>
            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-ink-300">
                Le téléchargement et le montage vertical se font sur votre machine, avec
                <span class="font-semibold text-ink-100">FetchClip</span> : il récupère le mp4,
                le recadre en 9:16, ajoute vos textes et sort un fichier prêt à téléverser.
                C'est volontaire — un rendu vidéo occupe un processeur entier pendant une à deux
                minutes, et le faire ici ralentirait le site pour tout le monde.
            </p>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <a href="https://github.com/ILIAS-ma/fetch_clip" target="_blank" rel="noopener"
                   class="btn-ghost">
                    Récupérer FetchClip ↗
                </a>
                <span class="text-sm text-ink-400">Node 18+ et yt-dlp. Aucune dépendance npm.</span>
            </div>

            <ol class="mt-6 space-y-2 border-t border-ink-700 pt-5 text-sm leading-relaxed text-ink-300">
                <li><span class="font-semibold text-ink-100">1.</span> Collez les pseudos de chaînes trouvés ci-dessus dans l'outil.</li>
                <li><span class="font-semibold text-ink-100">2.</span> Sélectionnez les clips, téléchargez, montez en 9:16.</li>
                <li><span class="font-semibold text-ink-100">3.</span> Publiez depuis votre compte, puis collez l'adresse de la publication dans la campagne.</li>
            </ol>

            <p class="hint mt-5">
                Un clip n'existe sur Twitch que si la chaîne autorise le clipping : c'est un
                réglage que le streamer peut couper. Citez la chaîne source dans votre légende.
            </p>
        </div>
    </div>
</x-app-layout>
