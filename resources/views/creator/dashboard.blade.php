@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Espace créateur</p>
                <h1 class="mt-1 font-display text-2xl font-bold text-ink-50">{{ $creator->name }}</h1>
            </div>

            @unless ($creator->is_active)
                <span class="chip-wait">Fiche en attente de validation</span>
            @endunless
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        @if (session('status'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        @unless ($creator->is_active)
            <div class="alert-warn">
                <p class="font-semibold">Votre fiche n'est pas encore validée</p>
                <p class="mt-1 leading-relaxed">
                    Un administrateur doit la valider avant qu'une campagne puisse être lancée à
                    votre nom. Vous pouvez déjà compléter vos informations.
                </p>
            </div>
        @endunless

        {{--
            L'essentiel tient en une phrase et trois chiffres. Un créateur veut
            savoir combien de vues il a eues, ce que ça lui a coûté et s'il lui
            reste du budget — pas lire un tableau de bord d'analyste.
        --}}
        <div class="card p-6 sm:p-8">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Où vous en êtes</p>
            <p class="mt-2 font-display text-2xl font-bold leading-snug text-ink-50 sm:text-3xl">
                {{ $headline }}
            </p>

            <div class="mt-6 grid gap-6 border-t border-ink-700 pt-6 sm:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Vues</p>
                    <p class="mt-1 font-display text-2xl font-bold tabular text-brand-400">
                        {{ Money::views($summary['views']) }}
                    </p>
                    <p class="mt-1 text-xs leading-relaxed text-ink-400">
                        Comptées seulement une fois payées au clippeur.
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Dépensé</p>
                    <p class="mt-1 font-display text-2xl font-bold tabular text-ink-50">
                        {{ Money::euros($summary['spent_cents']) }}
                    </p>
                    <p class="mt-1 text-xs leading-relaxed text-ink-400">
                        Sur {{ Money::euros($summary['engaged_cents']) }} engagés ·
                        {{ Money::euros($summary['remaining_cents']) }} restants.
                    </p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Coût pour 1000 vues</p>
                    <p class="mt-1 font-display text-2xl font-bold tabular text-ink-50">
                        {{ $summary['real_cpm_cents'] === null ? '—' : Money::euros($summary['real_cpm_cents']) }}
                    </p>
                    <p class="mt-1 text-xs leading-relaxed text-ink-400">
                        Ce que vous payez réellement. Plus c'est bas, mieux la campagne tourne.
                    </p>
                </div>
            </div>
        </div>

        @if ($summary['views'] > 0)
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="card p-6">
                    <x-mini-bars :series="$daily->map(fn ($d) => $d['views'])"
                                 label="Vues des 30 derniers jours" unit=" vues" />
                </div>

                <div class="card p-6">
                    <x-mini-bars :series="$daily->map(fn ($d) => (int) round($d['cents'] / 100))"
                                 label="Dépense des 30 derniers jours" unit=" €" />
                </div>
            </div>
        @endif

        @if ($topClips->isNotEmpty())
            <div class="card">
                <div class="border-b border-ink-700 px-6 py-4">
                    <h2 class="font-display text-lg font-bold text-ink-50">Les clips qui marchent</h2>
                    <p class="mt-0.5 text-sm text-ink-400">
                        Classés sur les vues réellement rémunérées.
                    </p>
                </div>

                <ul class="divide-y divide-ink-700">
                    @foreach ($topClips as $clip)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-ink-50">
                                    {{ $clip->platform->label() }} · {{ $clip->campaign?->title }}
                                </p>
                                {{-- Le pseudo, jamais le nom réel ni le contact :
                                     le créateur n'a pas à démarcher les clippeurs
                                     en dehors de la plateforme. --}}
                                <p class="mt-0.5 text-sm text-ink-400">
                                    par {{ $clip->user?->displayName() }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="font-display font-bold tabular text-ink-50">
                                    {{ Money::views($clip->paid_views) }} vues
                                </p>
                                @if ($clip->url)
                                    <a href="{{ $clip->url }}" target="_blank" rel="noopener"
                                       class="text-xs font-semibold text-brand-400 underline underline-offset-2">
                                        Voir la publication
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="border-b border-ink-700 px-6 py-4">
                <h2 class="font-display text-lg font-bold text-ink-50">Mes campagnes</h2>
                <p class="mt-0.5 text-sm text-ink-400">
                    Les campagnes sont créées et pilotées par l'équipe. Vous en suivez les résultats.
                </p>
            </div>

            @if ($campaigns->isEmpty())
                <div class="px-6 py-14 text-center">
                    <p class="font-display text-base font-bold text-ink-50">Aucune campagne pour l'instant</p>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-ink-300">
                        Dès qu'une campagne sera lancée à votre nom, vous verrez ici les vues générées
                        et le budget consommé, en temps réel.
                    </p>
                </div>
            @else
                <ul class="divide-y divide-ink-700">
                    @foreach ($campaigns as $campaign)
                        <li>
                            <a href="{{ route('creator.campaigns.show', $campaign) }}"
                               class="block px-6 py-5 transition hover:bg-ink-800">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <p class="font-display text-base font-bold text-ink-50">
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
