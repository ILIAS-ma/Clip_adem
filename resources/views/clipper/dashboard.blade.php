@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink-50">
                    Salut {{ $clipper->displayName() }}
                </h1>
                <p class="mt-1 text-ink-300">
                    {{ $openCampaigns > 0
                        ? $openCampaigns.' campagne'.($openCampaigns > 1 ? 's' : '').' ouverte'.($openCampaigns > 1 ? 's' : '').' en ce moment'
                        : 'Aucune campagne ouverte pour l\'instant' }}
                </p>
            </div>

            <a href="{{ route('campaigns.index') }}" wire:navigate class="btn-primary">Voir les campagnes</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">

        @if (session('status') && ! str_contains(session('status'), '-'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        @php
            $minimum = (int) config('clipping.payouts.minimum_cents');
            $atteint = $minimum > 0 ? min(100, intdiv($balanceCents * 100, $minimum)) : 100;
            $manque = max(0, $minimum - $balanceCents);
        @endphp

        {{--
            Un seul chiffre domine : celui qu'on vient consulter.

            Quatre cartes de même poids obligeaient à lire les quatre étiquettes
            pour trouver la seule qui répond à « combien puis-je retirer ». Les
            autres restent là — elles expliquent ce solde — mais en second plan.
        --}}
        <div class="grid gap-4 lg:grid-cols-5">

            <div class="hero-balance card relative overflow-hidden p-7 sm:p-8 lg:col-span-3">
                <p class="text-sm font-medium text-ink-300">Solde retirable</p>

                <p class="mt-2 font-display text-5xl font-extrabold tabular leading-none text-brand-400 sm:text-6xl"
                   data-count-up>{{ Money::euros($balanceCents) }}</p>

                @if ($manque > 0)
                    <div class="mt-6 max-w-sm">
                        {{-- La barre répond à « c'est encore loin ? », qu'un simple
                             « minimum 20 € » laisse calculer de tête. --}}
                        <div class="h-1.5 overflow-hidden rounded-full bg-ink-700">
                            <div class="h-full rounded-full bg-brand-500 transition-all duration-700"
                                 style="width: {{ $atteint }}%"></div>
                        </div>
                        <p class="mt-2 text-sm text-ink-300">
                            Encore <span class="font-semibold tabular text-ink-100">{{ Money::euros($manque) }}</span>
                            avant de pouvoir retirer.
                        </p>
                    </div>
                @else
                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <a href="{{ route('earnings.index') }}" wire:navigate class="btn-brand">Demander un retrait</a>
                        <span class="text-sm text-ink-300">Vous avez atteint le minimum.</span>
                    </div>
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-3 lg:col-span-2 lg:grid-cols-1">
                <x-stat label="Vues cumulées" :value="Money::views($views)"
                        :hint="$unpaidViews > 0 ? Money::views($unpaidViews).' non rémunérées' : null" />

                <x-stat label="Gains validés" :value="Money::euros($earnedCents)"
                        tone="money" hint="Définitivement acquis" />

                <x-stat label="Comptes liés" :value="$accountsCount"
                        :tone="$accountsCount === 0 ? 'brand' : 'neutral'"
                        :hint="$accountsCount === 0 ? 'Nécessaire pour participer' : null" />
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-level-card class="lg:col-span-2" :progression="$progression" />

            <div class="card p-6">
                <p class="text-sm font-medium text-ink-300">Comment gagner de l'XP</p>
                <ul class="mt-4 space-y-3 text-sm">
                    <li class="flex items-baseline justify-between gap-3">
                        <span class="text-ink-300">Chaque vue rémunérée</span>
                        <span class="font-semibold tabular text-ink-50">1 XP</span>
                    </li>
                    <li class="flex items-baseline justify-between gap-3">
                        <span class="text-ink-300">Clip validé</span>
                        <span class="font-semibold tabular text-brand-400">
                            +{{ number_format(config('clipping.progression.xp_per_approved_clip'), 0, ',', ' ') }}
                        </span>
                    </li>
                    <li class="flex items-baseline justify-between gap-3">
                        <span class="text-ink-300">Nouvelle campagne rejointe</span>
                        <span class="font-semibold tabular text-brand-400">
                            +{{ number_format(config('clipping.progression.xp_per_campaign'), 0, ',', ' ') }}
                        </span>
                    </li>
                    <li class="flex items-baseline justify-between gap-3 border-t border-ink-700 pt-3">
                        <span class="text-ink-300">Clip invalidé</span>
                        <span class="font-semibold tabular text-red-400">
                            −{{ number_format(config('clipping.progression.xp_penalty_per_invalidated_clip'), 0, ',', ' ') }}
                        </span>
                    </li>
                </ul>

                {{-- Seules les vues réellement payées comptent : dit d'emblée,
                     ça enlève tout intérêt à gonfler ses compteurs. --}}
                <p class="hint mt-4 border-t border-ink-700 pt-4">
                    Seules les vues effectivement rémunérées comptent. Un clip invalidé perd les
                    siennes.
                </p>
            </div>
        </div>

        @if ($upcomingCampaigns->isNotEmpty())
            {{-- Anticiper plutôt que découvrir une campagne déjà à moitié
                 consommée par d'autres au moment où elle s'ouvre. --}}
            <div class="card">
                <div class="border-b border-ink-700 px-6 py-4">
                    <h2 class="font-display text-lg font-bold text-ink-50">Campagnes à venir</h2>
                    <p class="mt-0.5 text-sm text-ink-300">Déjà budgétées, pas encore ouvertes.</p>
                </div>
                <ul class="divide-y divide-ink-700">
                    @foreach ($upcomingCampaigns as $item)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                            <div>
                                <p class="font-semibold text-ink-50">{{ $item['campaign']->title }}</p>
                                <p class="mt-0.5 text-sm text-ink-400">{{ $item['campaign']->creator?->name }}</p>
                            </div>
                            <span class="chip-wait">
                                Ouvre {{ $item['opensAt']->isFuture() ? $item['opensAt']->diffForHumans() : "aujourd'hui" }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($accountsCount === 0)
            {{-- Sans compte lié, rien n'est possible : c'est la première action
                 à proposer, pas une information noyée dans une liste. --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center gap-6 p-8">
                    <div class="flex-1 min-w-[16rem]">
                        <h2 class="font-display text-xl font-bold text-ink-50">Liez un compte pour commencer</h2>
                        <p class="mt-2 max-w-lg text-sm leading-relaxed text-ink-300">
                            TikTok. C'est par ce compte que vos vues seront relevées
                            et vos gains calculés — sans lui, vous ne pouvez pas rejoindre de campagne.
                        </p>
                    </div>
                    <a href="{{ route('accounts.index') }}" wire:navigate class="btn-brand">Lier un compte</a>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="flex items-center justify-between border-b border-ink-700 px-6 py-4">
                <h2 class="font-display text-lg font-bold text-ink-50">Mes clips</h2>
                @if ($clips->isNotEmpty())
                    <a href="{{ route('clips.index') }}" wire:navigate class="text-sm font-semibold text-ink-300 underline-offset-2 hover:text-ink-50 hover:underline">
                        Tout voir
                    </a>
                @endif
            </div>

            @if ($clips->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="font-display text-base font-bold text-ink-50">Aucun clip pour l'instant</p>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-ink-300">
                        Rejoignez une campagne, publiez votre clip, puis collez son lien.
                    </p>
                </div>
            @else
                <ul class="divide-y divide-ink-700">
                    @foreach ($clips->take(5) as $clip)
                        <li>
                            <a href="{{ route('clips.show', $clip) }}" wire:navigate
                               class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 transition hover:bg-ink-800">
                                <div class="min-w-0">
                                    <p class="font-semibold text-ink-50">{{ $clip->campaign?->title }}</p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-ink-400">
                                        <span>{{ $clip->platform->label() }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span class="{{ $clip->status->value === 'approved' ? 'chip-ok' : ($clip->status->value === 'pending_review' ? 'chip-wait' : 'chip-neutral') }}">
                                            {{ $clip->status->label() }}
                                        </span>
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="font-display font-bold tabular text-ink-50">{{ Money::euros($clip->earned_cents) }}</p>
                                    <p class="text-xs tabular text-ink-400">{{ Money::views($clip->views_total) }} vues</p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
