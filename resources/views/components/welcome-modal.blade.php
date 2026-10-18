{{--
    Fenêtre de bienvenue, montrée une fois au clippeur qui découvre le site.

    Quatre étapes, un lien vers la formation, et surtout la porte de contact :
    au lancement, une question sans réponse est un clippeur qui part.
    Fermée, elle est retenue sur le compte (route welcome.seen) et ne revient pas.
--}}
<div x-data="{
        open: true,
        close() {
            this.open = false;

            return fetch(@js(route('welcome.seen')), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
            }).catch(() => {});
        },
        // Change de page seulement une fois la fermeture enregistrée : sinon
        // la page suivante, plus rapide, rouvrirait la fenêtre.
        go(url) {
            this.close().finally(() => { window.location.href = url; });
        },
     }"
     x-show="open"
     x-transition.opacity
     @keydown.escape.window="close()"
     class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="welcome-title">

    <div class="absolute inset-0 bg-ink-950/80 backdrop-blur-sm" @click="close()"></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         class="relative w-full max-w-lg overflow-hidden rounded-3xl border border-ink-700 bg-ink-900 shadow-2xl">

        <div aria-hidden="true" class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full bg-brand-500/30 blur-3xl"></div>

        <button type="button" @click="close()" aria-label="Fermer"
                class="absolute right-4 top-4 rounded-lg p-1.5 text-ink-400 transition hover:bg-ink-800 hover:text-ink-50">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>

        <div class="relative p-6 sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-brand-400">Bienvenue sur {{ config('app.name') }}</p>
            <h2 id="welcome-title" class="mt-2 font-display text-2xl font-bold leading-tight text-ink-50">
                Salut {{ auth()->user()->displayName() }}, prêt à être payé pour tes clips ?
            </h2>
            <p class="mt-2 text-sm leading-relaxed text-ink-300">
                Le site vient d'ouvrir : voici comment ça marche, en quatre étapes.
            </p>

            <ol class="mt-6 space-y-3">
                @foreach ([
                    ['Lie ton compte TikTok', 'Dans « Mes comptes » : c\'est ce qui permet de compter tes vues.'],
                    ['Choisis une campagne', 'Lis le brief, rejoins-la en un clic.'],
                    ['Poste et soumets ton clip', 'Publie sur ton compte, colle le lien sur la campagne.'],
                    ['Encaisse', 'Tes gains arrivent sur ton solde, retrait dès 20 €.'],
                ] as $index => [$title, $text])
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 flex-none items-center justify-center rounded-full bg-brand-500 text-sm font-bold text-white">{{ $index + 1 }}</span>
                        <div>
                            <p class="font-semibold text-ink-50">{{ $title }}</p>
                            <p class="text-sm text-ink-300">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            {{-- La porte de contact, mise en avant : au lancement, on veut
                 entendre chaque question plutôt que la deviner. --}}
            <div class="mt-6 rounded-2xl border border-brand-500/40 bg-brand-500/10 p-4">
                <p class="font-semibold text-ink-50">Un souci, une question ?</p>
                <p class="mt-0.5 text-sm text-ink-300">
                    Écris-nous à tout moment depuis <span class="font-semibold text-ink-100">Messages</span> : l'équipe te répond directement.
                </p>
            </div>

            <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('training.index') }}" @click.prevent="go($el.href)" class="btn-primary flex-1">Voir la formation</a>
                <a href="{{ route('messages.index') }}" @click.prevent="go($el.href)" class="btn-ghost flex-1">Contacter l'équipe</a>
            </div>

            <button type="button" @click="close()" class="mt-3 w-full text-center text-sm text-ink-400 hover:text-ink-100">
                C'est parti, je découvre
            </button>
        </div>
    </div>
</div>
