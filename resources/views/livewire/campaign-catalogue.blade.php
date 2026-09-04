@php use App\Support\Money; @endphp

<div class="space-y-6">

    <div class="card p-4 sm:p-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="search" class="text-xs font-semibold uppercase tracking-wide text-ink-400">Rechercher</label>
                <input id="search" type="search" wire:model.live.debounce.300ms="search"
                       placeholder="Titre ou créateur" class="field mt-1.5 text-sm">
            </div>

            <div>
                <label for="platform" class="text-xs font-semibold uppercase tracking-wide text-ink-400">Plateforme</label>
                <select id="platform" wire:model.live="platform" class="field mt-1.5 text-sm">
                    <option value="">Toutes</option>
                    @foreach ($platforms as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="creator" class="text-xs font-semibold uppercase tracking-wide text-ink-400">Créateur</label>
                <select id="creator" wire:model.live="creator" class="field mt-1.5 text-sm">
                    <option value="">Tous</option>
                    @foreach ($creators as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="minRate" class="text-xs font-semibold uppercase tracking-wide text-ink-400">Cachet min.</label>
                <div class="relative mt-1.5">
                    <input id="minRate" type="number" step="0.10" min="0" wire:model.live.debounce.500ms="minRate"
                           placeholder="0,50" class="field pe-16 text-sm">
                    <span class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3 text-xs text-ink-500">€/1000</span>
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-ink-700 pt-4">
            <label class="flex items-center gap-2.5 text-sm text-ink-200">
                <input type="checkbox" wire:model.live="onlyOpen" class="rounded border-ink-700 text-ink-100 focus:ring-brand-500">
                Uniquement les campagnes ouvertes
            </label>

            <button type="button" wire:click="resetFilters"
                    class="text-sm font-medium text-ink-400 underline-offset-2 hover:text-ink-50 hover:underline">
                Réinitialiser
            </button>

            <span class="ms-auto text-sm tabular text-ink-400" wire:loading.class="opacity-40">
                {{ $campaigns->total() }} campagne{{ $campaigns->total() > 1 ? 's' : '' }}
            </span>
        </div>
    </div>

    @if ($campaigns->isEmpty())
        <div class="card px-6 py-16 text-center">
            <p class="font-display text-lg font-bold text-ink-50">Aucune campagne ne correspond</p>
            <p class="mx-auto mt-2 max-w-sm text-sm text-ink-300">
                Élargissez vos filtres, ou décochez « uniquement les campagnes ouvertes ».
            </p>
        </div>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
            @foreach ($campaigns as $campaign)
                @php
                    $left = $remaining[$campaign->id];
                    $closed = $left <= 0 || $campaign->status->value !== 'active';
                    $best = $campaign->rates->where('is_enabled', true)->max('rate_per_1k_cents');
                @endphp

                <a href="{{ route('campaigns.show', $campaign) }}" wire:navigate
                   @class([
                       'group flex flex-col card p-5 transition hover:-translate-y-0.5 hover:shadow-lifted',
                       'opacity-60' => $closed,
                   ])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">
                                {{ $campaign->creator?->name }}
                            </p>
                            <h2 class="mt-1 font-display text-lg font-bold leading-snug text-ink-50">
                                {{ $campaign->title }}
                            </h2>
                        </div>

                        <span @class([
                            'shrink-0',
                            'chip-ok' => ! $closed,
                            'chip-danger' => $left <= 0,
                            'chip-neutral' => $closed && $left > 0,
                        ])>{{ $left <= 0 ? 'Épuisée' : $campaign->status->label() }}</span>
                    </div>

                    {{-- Le cachet est le premier critère de choix : il mérite
                         d'être lisible sans lire la carte entière. --}}
                    @if ($best)
                        <p class="mt-4 font-display text-2xl font-bold tabular text-ink-50">
                            {{ Money::rate($best) }}
                            <span class="text-sm font-semibold text-ink-400">/ 1000 vues</span>
                        </p>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($campaign->rates->where('is_enabled', true) as $rate)
                            <span class="chip-neutral">{{ $rate->platform->label() }}</span>
                        @endforeach
                    </div>

                    <x-budget-bar class="mt-auto pt-5" :campaign="$campaign" :remaining="$left" />
                </a>
            @endforeach
        </div>

        <div>{{ $campaigns->links() }}</div>
    @endif
</div>
