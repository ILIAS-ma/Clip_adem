@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink-900">
                    Salut {{ $clipper->displayName() }}
                </h1>
                <p class="mt-1 text-ink-500">
                    {{ $openCampaigns > 0
                        ? $openCampaigns.' campagne'.($openCampaigns > 1 ? 's' : '').' ouverte'.($openCampaigns > 1 ? 's' : '').' en ce moment'
                        : 'Aucune campagne ouverte pour l\'instant' }}
                </p>
            </div>

            <a href="{{ route('campaigns.index') }}" class="btn-primary">Voir les campagnes</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">

        @if (session('status') && ! str_contains(session('status'), '-'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat label="Vues cumulées" :value="Money::views($views)"
                    :hint="$unpaidViews > 0 ? Money::views($unpaidViews).' non rémunérées' : null" />

            <x-stat label="Gains validés" :value="Money::euros($earnedCents)"
                    tone="money" hint="Définitivement acquis" />

            <x-stat label="Solde retirable" :value="Money::euros($balanceCents)"
                    tone="money" highlight
                    :hint="'Retrait dès '.Money::euros(config('clipping.payouts.minimum_cents'))" />

            <x-stat label="Comptes liés" :value="$accountsCount"
                    :tone="$accountsCount === 0 ? 'brand' : 'neutral'"
                    :hint="$accountsCount === 0 ? 'Nécessaire pour participer' : null" />
        </div>

        @if ($accountsCount === 0)
            {{-- Sans compte lié, rien n'est possible : c'est la première action
                 à proposer, pas une information noyée dans une liste. --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center gap-6 p-8">
                    <div class="flex-1 min-w-[16rem]">
                        <h2 class="font-display text-xl font-bold text-ink-900">Liez un compte pour commencer</h2>
                        <p class="mt-2 max-w-lg text-sm leading-relaxed text-ink-500">
                            TikTok, YouTube ou Instagram. C'est par ce compte que vos vues seront relevées
                            et vos gains calculés — sans lui, vous ne pouvez pas rejoindre de campagne.
                        </p>
                    </div>
                    <a href="{{ route('accounts.index') }}" class="btn-brand">Lier un compte</a>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="flex items-center justify-between border-b border-ink-100 px-6 py-4">
                <h2 class="font-display text-lg font-bold text-ink-900">Mes clips</h2>
                @if ($clips->isNotEmpty())
                    <a href="{{ route('clips.index') }}" class="text-sm font-semibold text-ink-500 underline-offset-2 hover:text-ink-900 hover:underline">
                        Tout voir
                    </a>
                @endif
            </div>

            @if ($clips->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="font-display text-base font-bold text-ink-900">Aucun clip pour l'instant</p>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-ink-500">
                        Rejoignez une campagne, publiez votre clip, puis collez son lien.
                    </p>
                </div>
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach ($clips->take(5) as $clip)
                        <li>
                            <a href="{{ route('clips.show', $clip) }}"
                               class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 transition hover:bg-ink-50">
                                <div class="min-w-0">
                                    <p class="font-semibold text-ink-900">{{ $clip->campaign?->title }}</p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-ink-400">
                                        <span>{{ $clip->platform->label() }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span class="{{ $clip->status->value === 'approved' ? 'chip-ok' : ($clip->status->value === 'pending_review' ? 'chip-wait' : 'chip-neutral') }}">
                                            {{ $clip->status->label() }}
                                        </span>
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="font-display font-bold tabular text-ink-900">{{ Money::euros($clip->earned_cents) }}</p>
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
