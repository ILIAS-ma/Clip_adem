@php use App\Support\Money; @endphp

{{--
    La grille des vidéos, en feuille plein écran sur téléphone et en fenêtre
    sur ordinateur. Elle s'ouvre depuis n'importe quel bouton de la page qui
    émet `open-picker` ; on coche une ou plusieurs vidéos, on soumet.

    Placée à la racine de la page, hors des cartes : un parent avec un flou
    d'arrière-plan piégerait la position fixe.
--}}
<div x-data="{
        open: false,
        loaded: false,
        selected: [],
        toggle(key) {
            const i = this.selected.indexOf(key);
            i === -1 ? this.selected.push(key) : this.selected.splice(i, 1);
        },
        show() {
            this.open = true;
            if (! this.loaded) { this.loaded = true; $wire.loadVideos(); }
        },
     }"
     @open-picker.window="show()"
     @keydown.escape.window="open = false"
     x-effect="document.body.classList.toggle('overflow-hidden', open)">

    <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-end justify-center sm:items-center sm:p-6">
        <div x-show="open" x-transition.opacity @click="open = false" class="absolute inset-0 bg-ink-950/80 backdrop-blur-sm"></div>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-8 opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             role="dialog" aria-modal="true" aria-labelledby="picker-title"
             class="relative flex h-[100dvh] w-full flex-col bg-ink-950 sm:h-auto sm:max-h-[85vh] sm:max-w-lg sm:rounded-3xl sm:border sm:border-ink-700">

            {{-- En-tête --}}
            <div class="flex items-center gap-3 border-b border-ink-800 px-4 py-3" style="padding-top: max(0.75rem, env(safe-area-inset-top));">
                <div class="min-w-0 flex-1">
                    <h2 id="picker-title" class="font-display text-lg font-bold text-ink-50">Soumettre un clip</h2>
                    <p class="text-xs text-ink-400">Touchez vos vidéos pour les sélectionner.</p>
                </div>
                <button type="button" wire:click="refreshVideos" wire:loading.attr="disabled"
                        class="rounded-full p-2 text-ink-300 transition hover:bg-ink-800 hover:text-ink-50" aria-label="Actualiser">
                    <svg class="h-5 w-5" wire:loading.class="animate-spin" wire:target="refreshVideos" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.1 15a7 7 0 0011.9 2.9L20 15M4 9l3-2.9A7 7 0 0118.9 9" /></svg>
                </button>
                <button type="button" @click="open = false"
                        class="rounded-full p-2 text-ink-300 transition hover:bg-ink-800 hover:text-ink-50" aria-label="Fermer">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </div>

            {{-- Grille --}}
            <div class="flex-1 overflow-y-auto px-3 py-3" data-picker-scroller>
                @if ($pickError)
                    <div class="alert-danger mb-3 text-sm">{{ $pickError }}</div>
                @endif

                @if ($groups === null)
                    <ul class="grid grid-cols-3 gap-1.5" aria-hidden="true">
                        @for ($i = 0; $i < 9; $i++)
                            <li class="aspect-[9/16] animate-pulse rounded-xl bg-ink-800"></li>
                        @endfor
                    </ul>
                @else
                    @forelse ($groups as $group)
                        <div wire:key="group-{{ $group['account_id'] }}" class="mb-4">
                            @if (count($groups) > 1)
                                <p class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-ink-400">
                                    {{ ucfirst($group['platform']) }}@if ($group['handle']) · &#64;{{ $group['handle'] }}@endif
                                </p>
                            @endif

                            @if ($group['error'])
                                <p class="rounded-xl bg-ink-900 p-4 text-sm text-ink-300">
                                    Impossible de récupérer vos vidéos pour l'instant. Touchez « Actualiser » dans un moment.
                                </p>
                            @elseif ($group['videos'] === [])
                                <p class="rounded-xl bg-ink-900 p-4 text-sm text-ink-300">
                                    Aucune vidéo publique sur ce compte. Publiez votre clip sur TikTok, en public, puis touchez « Actualiser ».
                                </p>
                            @else
                                <ul class="grid grid-cols-3 gap-1.5">
                                    @foreach ($group['videos'] as $video)
                                        @php $key = $group['account_id'].':'.$video['id']; @endphp
                                        <li wire:key="video-{{ $key }}">
                                            <button type="button"
                                                    @if ($video['submitted']) disabled @else @click="toggle('{{ $key }}')" @endif
                                                    :aria-pressed="selected.includes('{{ $key }}') ? 'true' : 'false'"
                                                    :class="selected.includes('{{ $key }}') ? 'ring-[3px] ring-brand-500' : 'ring-1 ring-ink-800'"
                                                    class="relative block aspect-[9/16] w-full overflow-hidden rounded-xl bg-ink-900 text-left transition active:scale-[0.97] disabled:active:scale-100">
                                                @if ($video['thumbnail'])
                                                    <img src="{{ $video['thumbnail'] }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer"
                                                         onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">
                                                @endif
                                                <span aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-ink-950/90 via-transparent to-transparent"></span>

                                                <span class="absolute inset-x-1.5 bottom-1.5">
                                                    <span class="block text-xs font-bold tabular text-white">{{ Money::compactViews($video['views']) }} vues</span>
                                                    @if ($video['posted_at'])
                                                        <span class="block text-[10px] text-ink-300">{{ \Illuminate\Support\Carbon::parse($video['posted_at'])->format('d/m/Y') }}</span>
                                                    @endif
                                                </span>

                                                @if ($video['submitted'])
                                                    {{-- Déjà soumise : visible, pour que le clippeur sache où il en est. --}}
                                                    <span class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-brand-600/70 text-white">
                                                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                                                        <span class="text-xs font-bold">Soumis</span>
                                                    </span>
                                                @else
                                                    <span class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full border-2 text-xs font-bold"
                                                          :class="selected.includes('{{ $key }}') ? 'border-brand-500 bg-brand-500 text-white' : 'border-white/70 bg-ink-950/30 text-transparent'"
                                                          x-text="selected.includes('{{ $key }}') ? selected.indexOf('{{ $key }}') + 1 : ''"></span>
                                                @endif

                                                <span class="sr-only">{{ $video['caption'] ?? 'Vidéo' }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @empty
                        <p class="rounded-xl bg-ink-900 p-4 text-sm text-ink-300">
                            Ce compte ne permet pas encore de choisir ses vidéos. Liez votre compte TikTok depuis « Mes comptes ».
                        </p>
                    @endforelse

                    {{-- Tout l'historique : la suite se charge en arrivant en bas. --}}
                    @if (collect($groups)->contains('has_more', true))
                        <div wire:key="more-{{ $want }}"
                             x-init="new IntersectionObserver((entries, observer) => {
                                 if (entries[0].isIntersecting) { observer.disconnect(); $wire.loadMore(); }
                             }, { root: $el.closest('[data-picker-scroller]'), rootMargin: '400px' }).observe($el)"
                             class="flex justify-center py-4">
                            <button type="button" wire:click="loadMore" class="btn-ghost text-sm">
                                <span wire:loading.remove wire:target="loadMore">Voir plus de vidéos</span>
                                <span wire:loading wire:target="loadMore" class="inline-flex items-center gap-2"><x-loader size="sm" label="Chargement" /> Chargement…</span>
                            </button>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Validation --}}
            <div class="border-t border-ink-800 bg-ink-950 p-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                <button type="button"
                        :disabled="selected.length === 0"
                        @click="$wire.submitSelected(selected).then(() => { selected = [] })"
                        wire:loading.attr="disabled" wire:target="submitSelected"
                        class="btn-brand w-full py-3.5 text-base disabled:opacity-40">
                    <span wire:loading.remove wire:target="submitSelected"
                          x-text="selected.length === 0 ? 'Sélectionnez vos vidéos' : (selected.length === 1 ? 'Soumettre 1 vidéo' : 'Soumettre ' + selected.length + ' vidéos')">Sélectionnez vos vidéos</span>
                    <span wire:loading wire:target="submitSelected" class="inline-flex items-center gap-2">
                        <x-loader size="sm" tone="dark" label="Soumission en cours" /> Soumission…
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
