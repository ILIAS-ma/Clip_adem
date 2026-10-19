{{--
    Bouton d'aide flottant, sur toutes les pages clippeur.

    Messages existe dans le menu, mais personne ne pense à « Messages » quand
    il est bloqué : il cherche de l'aide. Ce bouton reste là, en bas à droite,
    et mène aux deux recours — écrire à l'équipe, ou relire la formation.
--}}
<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false"
     {{ $attributes->merge(['class' => 'fixed bottom-5 right-5 z-50 flex flex-col items-end gap-3']) }}
     style="bottom: calc(1.25rem + env(safe-area-inset-bottom, 0px));">

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="w-72 overflow-hidden rounded-2xl border border-ink-700 bg-ink-900 shadow-2xl">
        <div class="border-b border-ink-700 p-4">
            <p class="font-display font-bold text-ink-50">Besoin d'aide ?</p>
            <p class="mt-0.5 text-sm text-ink-300">Un souci, une question : l'équipe est là.</p>
        </div>
        <a href="{{ route('messages.index') }}" wire:navigate
           class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-ink-50 transition hover:bg-ink-800">
            <svg class="h-5 w-5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6l2.5-3H18a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v14z" /></svg>
            Écrire à l'équipe
        </a>
        <a href="{{ route('training.index') }}" wire:navigate
           class="flex items-center gap-3 border-t border-ink-800 px-4 py-3 text-sm font-medium text-ink-50 transition hover:bg-ink-800">
            <svg class="h-5 w-5 text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0v6m-6-9.2V16c0 1.1 2.7 3 6 3s6-1.9 6-3v-5.2" /></svg>
            Voir la formation
        </a>
    </div>

    <button type="button" @click="open = ! open" :aria-expanded="open ? 'true' : 'false'" aria-label="Besoin d'aide ?"
            class="flex h-11 items-center gap-2 rounded-full bg-brand-500 px-3 font-semibold text-white shadow-glow transition hover:bg-brand-400 sm:h-14 sm:px-5">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.2 9a3.8 3.8 0 017.3 1.3c0 2.5-3.5 3-3.5 5M12 18h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span class="hidden sm:inline">Aide</span>
    </button>
</div>
