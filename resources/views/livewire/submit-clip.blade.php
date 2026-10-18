@php use App\Support\Money; @endphp

<div class="mt-4 space-y-5">
    {{--
        Le chemin du téléphone : ses vidéos récentes en vignettes, un toucher
        pour soumettre. Pas de lien à copier, et aucune vidéo d'un autre compte
        ne peut s'y glisser.
    --}}
    @if ($groups === null)
        <button type="button" wire:click="loadVideos" wire:loading.attr="disabled" wire:target="loadVideos"
                class="btn-primary w-full py-3 text-base">
            <span wire:loading.remove wire:target="loadVideos" class="inline-flex items-center gap-2">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zm6 4.5l4 2.5-4 2.5v-5z" />
                </svg>
                Choisir une de mes vidéos
            </span>
            <span wire:loading wire:target="loadVideos" class="inline-flex items-center gap-2">
                <x-loader size="sm" tone="dark" label="Chargement de vos vidéos" />
                Chargement de vos vidéos…
            </span>
        </button>
    @else
        <div class="space-y-4">
            @if ($pickError)
                <div class="alert-danger">{{ $pickError }}</div>
            @endif

            @forelse ($groups as $group)
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">
                            {{ ucfirst($group['platform']) }}@if ($group['handle']) · &#64;{{ $group['handle'] }}@endif
                        </p>
                        <button type="button" wire:click="loadVideos" class="text-xs font-medium text-ink-400 hover:text-ink-100">Actualiser</button>
                    </div>

                    @if ($group['error'])
                        <p class="mt-2 rounded-xl bg-ink-900 p-3 text-sm text-ink-300">
                            Impossible de récupérer vos vidéos pour l'instant. Collez le lien juste en dessous.
                        </p>
                    @elseif ($group['videos'] === [])
                        <p class="mt-2 rounded-xl bg-ink-900 p-3 text-sm text-ink-300">
                            Aucune nouvelle vidéo publique sur ce compte. Publiez-la d'abord sur l'application, puis touchez « Actualiser ».
                        </p>
                    @else
                        <ul class="mt-2 grid grid-cols-3 gap-2">
                            @foreach ($group['videos'] as $video)
                                <li wire:key="video-{{ $group['account_id'] }}-{{ $video['id'] }}">
                                    <button type="button"
                                            wire:click="pick({{ $group['account_id'] }}, '{{ $video['id'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:confirm="Soumettre cette vidéo sur la campagne ?"
                                            class="group relative block aspect-[9/16] w-full overflow-hidden rounded-xl bg-ink-900 ring-1 ring-ink-700 transition hover:ring-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500">
                                        @if ($video['thumbnail'])
                                            <img src="{{ $video['thumbnail'] }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer"
                                                 onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">
                                        @endif
                                        <span aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-ink-950/90 via-transparent to-transparent"></span>
                                        <span class="absolute inset-x-1.5 bottom-1.5 text-left">
                                            <span class="block text-xs font-bold tabular text-white">{{ Money::compactViews($video['views']) }} vues</span>
                                            @if ($video['posted_at'])
                                                <span class="block text-[10px] text-ink-300">{{ \Illuminate\Support\Carbon::parse($video['posted_at'])->diffForHumans() }}</span>
                                            @endif
                                        </span>
                                        <span class="sr-only">{{ $video['caption'] ?? 'Vidéo' }} — soumettre</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @empty
                <p class="rounded-xl bg-ink-900 p-3 text-sm text-ink-300">
                    Le choix dans la liste n'est pas disponible pour ce compte : collez le lien juste en dessous.
                </p>
            @endforelse

            <div wire:loading wire:target="pick" class="flex items-center gap-2 text-sm text-ink-300">
                <x-loader size="sm" label="Soumission en cours" /> Soumission…
            </div>
        </div>
    @endif

    {{-- En secours : coller le lien, avec un bouton qui le colle d'un toucher. --}}
    <form wire:submit="submit" class="space-y-3 border-t border-ink-700 pt-4"
          x-data="{ async paste() { try { const text = await navigator.clipboard.readText(); if (text) { $wire.set('url', text.trim()); } } catch (e) { $refs.url.focus(); } } }">
        <label for="clip-url" class="text-xs font-semibold uppercase tracking-wide text-ink-400">Ou collez le lien</label>
        <div class="flex gap-2">
            <input id="clip-url" x-ref="url" type="url" wire:model="url" inputmode="url" autocomplete="off"
                   placeholder="https://www.tiktok.com/@…/video/…"
                   class="field min-w-0 flex-1 text-sm">
            <button type="button" @click="paste()" class="btn-ghost flex-none px-3">Coller</button>
        </div>
        <x-input-error class="mt-1" :messages="$errors->get('url')" />

        <button type="submit" class="btn-ghost w-full" wire:loading.attr="disabled" wire:target="submit">
            <span wire:loading.remove wire:target="submit">Soumettre ce lien</span>
            <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                <x-loader size="sm" label="Vérification en cours" />
                Vérification…
            </span>
        </button>
    </form>
</div>
