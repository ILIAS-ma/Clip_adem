@php use App\Support\Money; @endphp

<div class="space-y-6">

    <div class="rounded-lg bg-white p-4 shadow sm:p-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-medium text-gray-700">Rechercher</label>
                <input id="search" type="search" wire:model.live.debounce.300ms="search"
                       placeholder="Titre ou artiste"
                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>

            <div>
                <label for="platform" class="block text-xs font-medium text-gray-700">Plateforme</label>
                <select id="platform" wire:model.live="platform"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">Toutes</option>
                    @foreach ($platforms as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="artist" class="block text-xs font-medium text-gray-700">Artiste</label>
                <select id="artist" wire:model.live="artist"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">Tous</option>
                    @foreach ($artists as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="minRate" class="block text-xs font-medium text-gray-700">Cachet minimum</label>
                <div class="relative mt-1">
                    <input id="minRate" type="number" step="0.10" min="0" wire:model.live.debounce.500ms="minRate"
                           placeholder="0,50"
                           class="block w-full rounded-md border-gray-300 pe-16 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <span class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3 text-xs text-gray-400">€/1000</span>
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-gray-100 pt-4">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model.live="onlyOpen"
                       class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                Uniquement les campagnes ouvertes
            </label>

            <button type="button" wire:click="resetFilters"
                    class="text-sm text-gray-500 underline hover:text-gray-700">
                Réinitialiser
            </button>

            <span class="ms-auto text-sm text-gray-500 tabular-nums">
                {{ $campaigns->total() }} campagne{{ $campaigns->total() > 1 ? 's' : '' }}
            </span>
        </div>
    </div>

    @if ($campaigns->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-12 text-center">
            <h3 class="text-base font-semibold text-gray-900">Aucune campagne ne correspond</h3>
            <p class="mt-2 text-sm text-gray-500">Élargissez vos filtres ou revenez plus tard.</p>
        </div>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($campaigns as $campaign)
                @php
                    $left = $remaining[$campaign->id];
                    $closed = $left <= 0 || $campaign->status->value !== 'active';
                @endphp

                <a href="{{ route('campaigns.show', $campaign) }}" wire:navigate
                   @class([
                       'flex flex-col rounded-lg bg-white p-5 shadow transition hover:shadow-md',
                       'opacity-60' => $closed,
                   ])>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold leading-snug text-gray-900">{{ $campaign->title }}</h3>
                            <p class="text-sm text-gray-500">{{ $campaign->artist?->name }}</p>
                        </div>

                        <span @class([
                            'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-emerald-100 text-emerald-800' => $campaign->status->value === 'active' && ! $closed,
                            'bg-red-100 text-red-800' => $left <= 0,
                            'bg-gray-100 text-gray-600' => $campaign->status->value !== 'active' && $left > 0,
                        ])>
                            {{ $left <= 0 ? 'Épuisée' : $campaign->status->label() }}
                        </span>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach ($campaign->rates->where('is_enabled', true) as $rate)
                            <span class="rounded bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">
                                {{ $rate->platform->label() }} · {{ Money::rate($rate->rate_per_1k_cents) }}/1000
                            </span>
                        @endforeach
                    </div>

                    <x-budget-bar class="mt-auto pt-4" :campaign="$campaign" :remaining="$left" />
                </a>
            @endforeach
        </div>

        <div>{{ $campaigns->links() }}</div>
    @endif
</div>
