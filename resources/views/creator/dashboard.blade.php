@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $creator->kind()->spaceLabel() }}</p>
                <h1 class="mt-1 font-display text-2xl font-bold text-ink-50">{{ $creator->name }}</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @unless ($creator->is_active)
                    <span class="chip-wait">Fiche en attente de validation</span>
                @endunless

                <a href="{{ route('messages.index') }}" wire:navigate class="btn-ghost text-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6l2.5-3H18a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v14z" /></svg>
                    Contacter le support
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-4 px-4 py-5 sm:space-y-6 sm:px-6 sm:py-8 lg:px-8">

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
            L'essentiel tient en une phrase et quelques chiffres. Les vues sont celles des
            clips eux-mêmes : elles existent aussi pour une campagne « classement », qui ne
            paie rien aux vues, et elles continuent de monter après la fin.
        --}}
        <div class="card relative overflow-hidden p-5 sm:p-8">
            <div aria-hidden="true" class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-brand-500/15 blur-3xl"></div>

            <div class="relative">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Où vous en êtes</p>
                <p class="mt-2 font-display text-xl font-bold leading-snug text-ink-50 sm:text-3xl">{{ $headline }}</p>
            </div>

            <div class="relative mt-6 grid grid-cols-2 gap-3 border-t border-ink-700 pt-6 sm:grid-cols-3 lg:grid-cols-6">
                <x-stat label="Vues" :value="Money::views($overview['views'])" tone="money" />
                <x-stat label="Clips" :value="number_format($overview['clips'], 0, ',', ' ')" />
                <x-stat label="Clippeurs" :value="number_format($overview['clippers'], 0, ',', ' ')" />
                <x-stat label="Meilleur clip" :value="Money::compactViews($overview['best'])" hint="vues sur un seul clip" />
                <x-stat label="Moyenne / clip" :value="Money::compactViews($overview['average'])" hint="vues par clip" />
                <x-stat label="Dépensé" :value="Money::euros($summary['spent_cents'])"
                        :hint="'sur '.Money::euros($summary['engaged_cents']).' engagés'" />
            </div>
        </div>

        @if ($overview['clips'] > 0)
            @include('creator.partials.analytics', [
                'timeline' => $timeline,
                'posts' => $posts,
                'distribution' => $distribution,
                'topClippers' => $topClippers,
                'topClips' => $topClips,
                'platforms' => $platforms,
            ])
        @endif

        {{-- Les campagnes : en cours d'abord, les terminées en dessous — grisées, mais à portée de clic. --}}
        <div class="card overflow-hidden">
            <div class="border-b border-ink-700 px-5 py-4 sm:px-6">
                <h2 class="font-display text-lg font-bold text-ink-50">Mes campagnes</h2>
                <p class="mt-0.5 text-sm text-ink-400">
                    Les campagnes sont créées et pilotées par l'équipe. Vous en suivez les résultats, même après leur fin.
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
                <ul class="divide-y divide-ink-800">
                    @foreach ($campaigns as $campaign)
                        @php $ended = $campaign->hasEnded(); @endphp
                        <li>
                            <a href="{{ route('creator.campaigns.show', $campaign) }}"
                               class="flex items-center gap-4 px-5 py-4 transition hover:bg-ink-800 sm:px-6 sm:py-5">
                                @if ($cover = $campaign->coverUrl())
                                    <img src="{{ $cover }}" alt="" loading="lazy"
                                         class="h-16 w-16 flex-none rounded-xl object-cover ring-1 ring-ink-700 sm:h-20 sm:w-20 {{ $ended ? 'grayscale' : '' }}">
                                @else
                                    <span class="flex h-16 w-16 flex-none items-center justify-center rounded-xl bg-ink-800 font-display text-2xl font-bold text-ink-500 ring-1 ring-ink-700 sm:h-20 sm:w-20">
                                        {{ mb_strtoupper(mb_substr($campaign->title, 0, 1)) }}
                                    </span>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-display text-base font-bold text-ink-50">{{ $campaign->title }}</p>
                                        <span @class([
                                            'chip-ok' => ! $ended && $campaign->status->value === 'active',
                                            'chip-danger' => ! $ended && $campaign->status->value === 'exhausted',
                                            'chip-wait' => ! $ended && $campaign->status->value === 'paused',
                                            'chip-neutral' => $ended || in_array($campaign->status->value, ['draft', 'completed', 'archived']),
                                        ])>{{ $ended ? 'Terminée' : $campaign->status->label() }}</span>
                                    </div>

                                    <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm text-ink-400">
                                        <span class="tabular">{{ number_format($campaign->clips_count, 0, ',', ' ') }} clip{{ $campaign->clips_count > 1 ? 's' : '' }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span class="tabular">{{ Money::compactViews((int) $campaign->views_sum) }} vues</span>
                                        @if ($campaign->ends_at)
                                            <span aria-hidden="true">·</span>
                                            <span>{{ $ended ? 'terminée le' : 'fin le' }} {{ $campaign->ends_at->format('d/m/Y') }}</span>
                                        @endif
                                    </p>

                                    @unless ($ended)
                                        <x-budget-bar class="mt-3 max-w-md" :campaign="$campaign" :remaining="$remaining[$campaign->id]" />
                                    @endunless
                                </div>

                                <svg class="hidden h-5 w-5 flex-none text-ink-600 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Le support, à portée de main : un créateur ne doit pas chercher à qui écrire. --}}
        <div class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <h2 class="font-display text-lg font-bold text-ink-50">Une question, un souci ?</h2>
                <p class="mt-1 text-sm text-ink-300">Écris directement à l'équipe : on te répond depuis le site.</p>
            </div>
            <a href="{{ route('messages.index') }}" wire:navigate class="btn-brand justify-center sm:flex-none">Contacter le support</a>
        </div>
    </div>
</x-app-layout>
