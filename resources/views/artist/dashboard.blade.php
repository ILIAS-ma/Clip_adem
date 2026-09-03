@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Espace artiste</p>
                <h1 class="mt-1 font-display text-2xl font-bold text-ink-900">{{ $artist->name }}</h1>
                <p class="mt-1 text-ink-500">
                    {{ $activeCampaigns > 0
                        ? $activeCampaigns.' campagne'.($activeCampaigns > 1 ? 's' : '').' en cours'
                        : 'Aucune campagne en cours' }}
                </p>
            </div>

            @unless ($artist->is_active)
                <span class="chip-wait">Fiche en attente de validation</span>
            @endunless
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">

        @if (session('status'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        @unless ($artist->is_active)
            <div class="alert-warn">
                <p class="font-semibold">Votre fiche n'est pas encore validée</p>
                <p class="mt-1 leading-relaxed">
                    Un administrateur doit la valider avant de pouvoir lancer des campagnes à votre nom.
                    Vous pouvez déjà compléter vos informations.
                </p>
            </div>
        @endunless

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat label="Budget engagé" :value="Money::euros($budgetEngaged)"
                    :hint="$campaigns->count().' campagne'.($campaigns->count() > 1 ? 's' : '')" />

            <x-stat label="Dépensé" :value="Money::euros($spentCents)"
                    :hint="Money::euros(max(0, $budgetEngaged - $spentCents)).' restants'" />

            <x-stat label="Vues générées" :value="Money::views($views)"
                    tone="money" :hint="$clipsCount.' clip'.($clipsCount > 1 ? 's' : '').' validé'.($clipsCount > 1 ? 's' : '')" />

            {{-- Le seul indicateur de rendement qui ait du sens : ce qui a
                 réellement été payé pour 1000 vues, à comparer au CPM annoncé
                 sur les campagnes. --}}
            <x-stat label="Coût réel / 1000 vues"
                    :value="$realCpmCents === null ? '—' : Money::euros($realCpmCents)"
                    highlight hint="Rendement effectif" />
        </div>

        <div class="card">
            <div class="border-b border-ink-100 px-6 py-4">
                <h2 class="font-display text-lg font-bold text-ink-900">Mes campagnes</h2>
                <p class="mt-0.5 text-sm text-ink-400">
                    Les campagnes sont créées et pilotées par l'équipe. Vous en suivez les résultats.
                </p>
            </div>

            @if ($campaigns->isEmpty())
                <div class="px-6 py-14 text-center">
                    <p class="font-display text-base font-bold text-ink-900">Aucune campagne pour l'instant</p>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-ink-500">
                        Dès qu'une campagne sera lancée à votre nom, vous verrez ici les vues générées
                        et le budget consommé, en temps réel.
                    </p>
                </div>
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach ($campaigns as $campaign)
                        <li>
                            <a href="{{ route('artist.campaigns.show', $campaign) }}"
                               class="block px-6 py-5 transition hover:bg-ink-50">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="font-display text-base font-bold text-ink-900">
                                            {{ $campaign->title }}
                                        </p>
                                        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-ink-400">
                                            <span class="tabular">{{ $campaign->clips_count }} clip{{ $campaign->clips_count > 1 ? 's' : '' }}</span>
                                            @if ($campaign->ends_at)
                                                <span aria-hidden="true">·</span>
                                                <span>fin le {{ $campaign->ends_at->format('d/m/Y') }}</span>
                                            @endif
                                        </p>
                                    </div>

                                    <span @class([
                                        'chip-ok' => $campaign->status->value === 'active',
                                        'chip-danger' => $campaign->status->value === 'exhausted',
                                        'chip-wait' => $campaign->status->value === 'paused',
                                        'chip-neutral' => in_array($campaign->status->value, ['draft', 'completed', 'archived']),
                                    ])>{{ $campaign->status->label() }}</span>
                                </div>

                                <x-budget-bar class="mt-4 max-w-md" :campaign="$campaign"
                                              :remaining="$remaining[$campaign->id]" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-app-layout>
