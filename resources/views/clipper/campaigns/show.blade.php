@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-baseline justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $campaign->title }}</h2>
                <p class="text-sm text-gray-500">{{ $campaign->artist?->name }}</p>
            </div>
            <a href="{{ route('campaigns.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-gray-700">
                ← Toutes les campagnes
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="rounded-md border-l-4 border-emerald-400 bg-emerald-50 p-4 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">

                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-lg bg-white p-6 shadow">
                        <h3 class="text-base font-semibold text-gray-900">Brief</h3>
                        <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-gray-700">{{ $campaign->brief }}</p>

                        @if ($campaign->required_hashtags)
                            <div class="mt-4">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Hashtags obligatoires</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($campaign->required_hashtags as $hashtag)
                                        <span class="rounded bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-800">{{ $hashtag }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($campaign->audio_url || $campaign->assets_url)
                            <div class="mt-4 flex flex-wrap gap-4 text-sm">
                                @if ($campaign->audio_url)
                                    <a href="{{ $campaign->audio_url }}" target="_blank" rel="noopener"
                                       class="text-emerald-700 underline">Son à utiliser</a>
                                @endif
                                @if ($campaign->assets_url)
                                    <a href="{{ $campaign->assets_url }}" target="_blank" rel="noopener"
                                       class="text-emerald-700 underline">Pack visuel</a>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($participations->isNotEmpty())
                        <div class="rounded-lg bg-white p-6 shadow">
                            <h3 class="text-base font-semibold text-gray-900">Soumettre un clip</h3>

                            @if ($isOpen)
                                @livewire('submit-clip', ['campaign' => $campaign])
                            @else
                                <p class="mt-3 text-sm text-gray-500">
                                    Cette campagne n'accepte plus de nouveaux clips. Vos clips déjà soumis continuent
                                    d'être suivis.
                                </p>
                            @endif
                        </div>
                    @endif

                    @if ($clips->isNotEmpty())
                        <div class="rounded-lg bg-white shadow">
                            <div class="border-b border-gray-100 px-6 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Mes clips sur cette campagne</h3>
                            </div>
                            <ul class="divide-y divide-gray-100">
                                @foreach ($clips as $clip)
                                    <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                                        <div class="min-w-0">
                                            <a href="{{ $clip->url }}" target="_blank" rel="noopener"
                                               class="block truncate text-sm font-medium text-emerald-700 hover:underline">
                                                {{ $clip->platform->label() }} · {{ $clip->external_post_id }}
                                            </a>
                                            <p class="text-xs text-gray-500">
                                                {{ $clip->status->label() }}
                                                @if ($clip->rejection_reason)
                                                    — {{ $clip->rejection_reason }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium tabular-nums text-gray-900">{{ Money::euros($clip->earned_cents) }}</p>
                                            <p class="text-xs tabular-nums text-gray-500">{{ Money::views($clip->views_total) }} vues</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="space-y-6">
                    <div class="rounded-lg bg-white p-6 shadow">
                        <x-budget-bar :campaign="$campaign" :remaining="$remainingCents" />

                        <dl class="mt-5 space-y-3 border-t border-gray-100 pt-5 text-sm">
                            @foreach ($campaign->rates->where('is_enabled', true) as $rate)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">{{ $rate->platform->label() }}</dt>
                                    <dd class="font-medium tabular-nums text-gray-900">{{ Money::rate($rate->rate_per_1k_cents) }} / 1000 vues</dd>
                                </div>
                            @endforeach

                            @if ($campaign->min_views_per_clip > 0)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Vues minimum</dt>
                                    <dd class="tabular-nums text-gray-900">{{ Money::views($campaign->min_views_per_clip) }}</dd>
                                </div>
                            @endif

                            {{-- Les plafonds sont affichés d'emblée : les découvrir quand
                                 les gains cessent de monter fait croire à un bug. --}}
                            @if ($campaign->max_payout_per_clip_cents)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Plafond par clip</dt>
                                    <dd class="tabular-nums text-gray-900">{{ Money::euros($campaign->max_payout_per_clip_cents) }}</dd>
                                </div>
                            @endif

                            @if ($campaign->max_payout_per_clipper_cents)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Plafond par clippeur</dt>
                                    <dd class="tabular-nums text-gray-900">{{ Money::euros($campaign->max_payout_per_clipper_cents) }}</dd>
                                </div>
                            @endif

                            @if ($campaign->ends_at)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Fin</dt>
                                    <dd class="text-gray-900">{{ $campaign->ends_at->format('d/m/Y') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow">
                        <h3 class="text-base font-semibold text-gray-900">Participation</h3>

                        @forelse ($participations as $participation)
                            <div class="mt-3 rounded-md bg-gray-50 p-3 text-sm">
                                <p class="font-medium text-gray-900">
                                    {{ $participation->socialAccount?->platform->label() }}
                                    @if ($participation->socialAccount?->handle)
                                        · &#64;{{ $participation->socialAccount->handle }}
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500">{{ $participation->status->label() }}</p>
                            </div>
                        @empty
                            <p class="mt-2 text-sm text-gray-500">Vous ne participez pas encore à cette campagne.</p>
                        @endforelse

                        @if ($isOpen)
                            <div class="mt-4">
                                @livewire('join-campaign', ['campaign' => $campaign])
                            </div>
                        @elseif ($participations->isEmpty())
                            <p class="mt-4 rounded-md bg-gray-50 p-3 text-sm text-gray-600">
                                {{ $remainingCents <= 0
                                    ? "Le budget de cette campagne est épuisé : elle n'accepte plus de participants."
                                    : "Cette campagne n'accepte plus de nouveaux participants." }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
