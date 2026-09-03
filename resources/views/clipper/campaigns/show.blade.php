@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">
                    {{ $campaign->artist?->name }}
                </p>
                <h1 class="mt-1 font-display text-2xl font-bold text-ink-900">{{ $campaign->title }}</h1>
            </div>

            <a href="{{ route('campaigns.index') }}" wire:navigate class="btn-ghost">← Toutes les campagnes</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        @if (session('status'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">

            <div class="space-y-6 lg:col-span-2">
                <div class="card p-6 sm:p-8">
                    <h2 class="font-display text-lg font-bold text-ink-900">Le brief</h2>
                    <p class="mt-4 whitespace-pre-line leading-relaxed text-ink-600">{{ $campaign->brief }}</p>

                    @if ($campaign->required_hashtags)
                        <div class="mt-6">
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">
                                Hashtags obligatoires
                            </p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($campaign->required_hashtags as $hashtag)
                                    <span class="chip bg-ink-900 text-brand-300">{{ $hashtag }}</span>
                                @endforeach
                            </div>
                            <p class="hint">Leur absence sera signalée à la modération.</p>
                        </div>
                    @endif

                    @if ($campaign->audio_url || $campaign->assets_url)
                        <div class="mt-6 flex flex-wrap gap-3 border-t border-ink-100 pt-5">
                            @if ($campaign->audio_url)
                                <a href="{{ $campaign->audio_url }}" target="_blank" rel="noopener" class="btn-ghost">
                                    Son à utiliser
                                </a>
                            @endif
                            @if ($campaign->assets_url)
                                <a href="{{ $campaign->assets_url }}" target="_blank" rel="noopener" class="btn-ghost">
                                    Pack visuel
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($participations->isNotEmpty())
                    <div class="card p-6 sm:p-8">
                        <h2 class="font-display text-lg font-bold text-ink-900">Soumettre un clip</h2>

                        @if ($isOpen)
                            <p class="mt-2 text-sm text-ink-500">
                                Publiez depuis votre compte, puis collez ici l'adresse de la publication.
                            </p>
                            @livewire('submit-clip', ['campaign' => $campaign])
                        @else
                            <p class="mt-3 text-sm text-ink-500">
                                Cette campagne n'accepte plus de nouveaux clips. Ceux déjà soumis
                                continuent d'être suivis.
                            </p>
                        @endif
                    </div>
                @endif

                @if ($clips->isNotEmpty())
                    <div class="card">
                        <div class="border-b border-ink-100 px-6 py-4">
                            <h2 class="font-display text-lg font-bold text-ink-900">Mes clips sur cette campagne</h2>
                        </div>
                        <ul class="divide-y divide-ink-100">
                            @foreach ($clips as $clip)
                                <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                                    <div class="min-w-0">
                                        <a href="{{ route('clips.show', $clip) }}"
                                           class="block truncate font-semibold text-ink-900 underline-offset-2 hover:underline">
                                            {{ $clip->platform->label() }} · {{ $clip->external_post_id }}
                                        </a>
                                        <p class="mt-0.5 text-sm text-ink-400">
                                            {{ $clip->status->label() }}
                                            @if ($clip->rejection_reason)
                                                — {{ $clip->rejection_reason }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-display font-bold tabular text-ink-900">{{ Money::euros($clip->earned_cents) }}</p>
                                        <p class="text-xs tabular text-ink-400">{{ Money::views($clip->views_total) }} vues</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="card p-6">
                    <x-budget-bar :campaign="$campaign" :remaining="$remainingCents" />

                    <dl class="mt-6 space-y-3 border-t border-ink-100 pt-5 text-sm">
                        @foreach ($campaign->rates->where('is_enabled', true) as $rate)
                            <div class="flex items-baseline justify-between gap-2">
                                <dt class="text-ink-400">{{ $rate->platform->label() }}</dt>
                                <dd class="font-display font-bold tabular text-ink-900">
                                    {{ Money::rate($rate->rate_per_1k_cents) }}<span class="text-xs font-medium text-ink-400"> /1000</span>
                                </dd>
                            </div>
                        @endforeach

                        @if ($campaign->min_views_per_clip > 0)
                            <div class="flex justify-between gap-2 border-t border-ink-100 pt-3">
                                <dt class="text-ink-400">Vues minimum</dt>
                                <dd class="tabular text-ink-800">{{ Money::views($campaign->min_views_per_clip) }}</dd>
                            </div>
                        @endif

                        {{-- Les plafonds sont annoncés d'emblée : les découvrir
                             quand les gains cessent de monter fait croire à un bug. --}}
                        @if ($campaign->max_payout_per_clip_cents)
                            <div class="flex justify-between gap-2">
                                <dt class="text-ink-400">Plafond par clip</dt>
                                <dd class="tabular text-ink-800">{{ Money::euros($campaign->max_payout_per_clip_cents) }}</dd>
                            </div>
                        @endif

                        @if ($campaign->max_payout_per_clipper_cents)
                            <div class="flex justify-between gap-2">
                                <dt class="text-ink-400">Plafond par clippeur</dt>
                                <dd class="tabular text-ink-800">{{ Money::euros($campaign->max_payout_per_clipper_cents) }}</dd>
                            </div>
                        @endif

                        @if ($campaign->ends_at)
                            <div class="flex justify-between gap-2 border-t border-ink-100 pt-3">
                                <dt class="text-ink-400">Fin</dt>
                                <dd class="text-ink-800">{{ $campaign->ends_at->format('d/m/Y') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold text-ink-900">Participation</h2>

                    @forelse ($participations as $participation)
                        <div class="mt-4 rounded-xl bg-ink-50 p-3">
                            <p class="font-semibold text-ink-900">
                                {{ $participation->socialAccount?->platform->label() }}
                                @if ($participation->socialAccount?->handle)
                                    · &#64;{{ $participation->socialAccount->handle }}
                                @endif
                            </p>
                            <span class="{{ $participation->status->value === 'approved' ? 'chip-ok' : 'chip-wait' }} mt-1.5">
                                {{ $participation->status->label() }}
                            </span>
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-ink-500">Vous ne participez pas encore à cette campagne.</p>
                    @endforelse

                    @if ($isOpen)
                        <div class="mt-5">
                            @livewire('join-campaign', ['campaign' => $campaign])
                        </div>
                    @elseif ($participations->isEmpty())
                        <p class="mt-4 rounded-xl bg-ink-50 p-3 text-sm text-ink-500">
                            {{ $remainingCents <= 0
                                ? "Le budget de cette campagne est épuisé : elle n'accepte plus de participants."
                                : "Cette campagne n'accepte plus de nouveaux participants." }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
