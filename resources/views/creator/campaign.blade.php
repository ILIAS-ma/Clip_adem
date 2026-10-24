@php
    use App\Support\Money;

    $ended = $campaign->hasEnded();
    $cover = $campaign->coverUrl();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-center gap-4">
                @if ($cover)
                    <img src="{{ $cover }}" alt="" class="h-14 w-14 flex-none rounded-xl object-cover ring-1 ring-ink-700 sm:h-16 sm:w-16 {{ $ended ? 'grayscale' : '' }}">
                @endif

                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $creator->name }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <h1 class="font-display text-xl font-bold text-ink-50 sm:text-2xl">{{ $campaign->title }}</h1>
                        <span @class(['chip-neutral' => $ended, 'chip-ok' => ! $ended])>{{ $ended ? 'Terminée' : $campaign->status->label() }}</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('creator.dashboard') }}" wire:navigate class="btn-ghost text-sm">← Mes campagnes</a>
                <a href="{{ route('messages.index') }}" wire:navigate class="btn-ghost text-sm">Support</a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-4 px-4 py-5 sm:space-y-6 sm:px-6 sm:py-8 lg:px-8">

        {{-- Campagne finie : on le dit, et on rappelle que ses résultats restent là. --}}
        @if ($ended)
            <div class="rounded-2xl border border-ink-600 bg-ink-900 p-4 sm:p-5">
                <p class="font-display text-lg font-bold text-ink-50">Cette campagne est terminée</p>
                <p class="mt-1 text-sm leading-relaxed text-ink-300">
                    @if ($campaign->ends_at)
                        Elle s'est arrêtée le {{ $campaign->ends_at->format('d/m/Y à H\hi') }}.
                    @endif
                    Les statistiques restent consultables, et les vues continuent d'être mises à jour
                    tant que les clips sont en ligne.
                </p>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-6">
            <x-stat label="Vues générées" :value="Money::views($overview['views'])" tone="money" />
            <x-stat label="Clips" :value="number_format($overview['clips'], 0, ',', ' ')" />
            <x-stat label="Clippeurs" :value="number_format($overview['clippers'], 0, ',', ' ')" />
            <x-stat label="Meilleur clip" :value="Money::compactViews($overview['best'])" hint="vues sur un seul clip" />
            <x-stat label="Moyenne / clip" :value="Money::compactViews($overview['average'])" hint="vues par clip" />

            @if ($campaign->isContest())
                <x-stat label="Récompenses" :value="$campaign->prizePoolCents() > 0 ? Money::wholeEuros($campaign->prizePoolCents()) : 'Lots'" />
            @else
                <x-stat label="Dépensé" :value="Money::euros($campaign->spent_cents)"
                        :hint="$campaign->consumedPercent().' % de '.Money::euros($campaign->budget_total_cents)" />
            @endif
        </div>

        {{-- Le podium officiel d'un classement terminé : ce qui a été annoncé et récompensé. --}}
        @if ($podium)
            <div class="card p-5 sm:p-6">
                <h2 class="font-display text-lg font-bold text-ink-50">Le podium final</h2>
                <p class="mt-0.5 text-sm text-ink-400">Le classement arrêté à la date de fin, avec les récompenses.</p>

                <div class="mt-5 grid gap-6 lg:grid-cols-2">
                    @foreach ($podium as $key => $rows)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">{{ \App\Enums\ContestMetric::from($key)->title() }}</p>
                            <ol class="mt-3 space-y-2">
                                @foreach ($rows as $row)
                                    <li class="flex items-center gap-3 rounded-xl bg-ink-900 px-3 py-2.5 ring-1 ring-ink-800">
                                        <span @class([
                                            'flex h-8 w-8 flex-none items-center justify-center rounded-full text-sm font-bold tabular',
                                            'bg-amber-400 text-ink-950' => $row['rank'] === 1,
                                            'bg-ink-200 text-ink-950' => $row['rank'] === 2,
                                            'bg-amber-700 text-white' => $row['rank'] === 3,
                                        ])>{{ $row['rank'] }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate font-semibold text-ink-50">{{ $row['name'] }}</span>
                                            <span class="block text-xs tabular text-ink-400">{{ $row['score'] }}</span>
                                        </span>
                                        @if ($row['reward'])
                                            <span class="flex-none rounded-full bg-brand-500/15 px-2.5 py-1 text-xs font-bold tabular text-brand-300">{{ $row['reward'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($overview['clips'] > 0)
            @include('creator.partials.analytics')
        @endif

        <div class="grid gap-4 sm:gap-6 lg:grid-cols-3">

            <div class="space-y-4 sm:space-y-6 lg:col-span-2">
                <div class="card">
                    <div class="border-b border-ink-700 px-5 py-4 sm:px-6">
                        <h2 class="font-display text-lg font-bold text-ink-50">Les clips</h2>
                        <p class="mt-0.5 text-sm text-ink-400">
                            Classés par nombre de vues
                            @if ($clipsTotal > $clips->count())
                                · les {{ $clips->count() }} premiers sur {{ number_format($clipsTotal, 0, ',', ' ') }}
                            @endif
                        </p>
                    </div>

                    @if ($clips->isEmpty())
                        <p class="px-6 py-12 text-center text-sm text-ink-300">
                            Aucun clip validé pour le moment.
                        </p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs uppercase tracking-wide text-ink-400">
                                        <th class="px-5 py-3 text-left font-semibold sm:px-6">Clippeur</th>
                                        <th class="px-3 py-3 text-left font-semibold">Plateforme</th>
                                        <th class="px-3 py-3 text-right font-semibold">Vues</th>
                                        @unless ($campaign->isContest())
                                            <th class="px-5 py-3 text-right font-semibold sm:px-6">Coût</th>
                                        @endunless
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-ink-800">
                                    @foreach ($clips as $clip)
                                        <tr>
                                            {{-- Pseudo uniquement : le créateur n'a pas à connaître
                                                 l'identité civile ni les coordonnées des clippeurs. --}}
                                            <td class="px-5 py-3 font-medium text-ink-100 sm:px-6">
                                                {{ $clip->user?->displayName() }}
                                            </td>
                                            <td class="px-3 py-3">
                                                <a href="{{ $clip->url }}" target="_blank" rel="noopener"
                                                   class="text-ink-300 underline-offset-2 hover:text-ink-50 hover:underline">
                                                    {{ $clip->platform->label() }}
                                                </a>
                                            </td>
                                            <td class="px-3 py-3 text-right tabular text-ink-100">
                                                {{ Money::views($clip->views_total) }}
                                            </td>
                                            @unless ($campaign->isContest())
                                                <td class="px-5 py-3 text-right font-semibold tabular text-ink-50 sm:px-6">
                                                    {{ Money::euros($clip->earned_cents) }}
                                                </td>
                                            @endunless
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                @if ($campaign->brief)
                    <div class="card p-5 sm:p-6">
                        <h2 class="font-display text-lg font-bold text-ink-50">Le brief donné aux clippeurs</h2>
                        <p class="mt-3 whitespace-pre-line leading-relaxed text-ink-200">{{ $campaign->brief }}</p>

                        @if ($campaign->required_hashtags)
                            <div class="mt-4 flex flex-wrap gap-1.5">
                                @foreach ($campaign->required_hashtags as $hashtag)
                                    <span class="chip bg-brand-500/15 text-brand-300">{{ $hashtag }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="space-y-4 sm:space-y-6">
                <div class="card p-5 sm:p-6">
                    @unless ($campaign->isContest())
                        <x-budget-bar :campaign="$campaign" :remaining="$remainingCents" />
                    @endunless

                    <dl @class(['space-y-3 text-sm', 'mt-6 border-t border-ink-700 pt-5' => ! $campaign->isContest()])>
                        @foreach ($campaign->rates->where('is_enabled', true) as $rate)
                            <div class="flex items-baseline justify-between gap-2">
                                <dt class="text-ink-400">{{ $rate->platform->label() }}</dt>
                                <dd class="font-display font-bold tabular text-ink-50">
                                    @if ($campaign->isContest())
                                        <span class="text-sm font-medium text-ink-300">acceptée</span>
                                    @else
                                        {{ Money::rate($rate->rate_per_1k_cents) }}<span class="text-xs font-medium text-ink-400"> /1000</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach

                        @if ($campaign->isContest())
                            {{-- Le créateur finance des prix, pas des vues : il
                                 doit lire ce qu'il a promis, place par place. --}}
                            @forelse ($campaign->rewards() as $index => $reward)
                                <div class="flex justify-between gap-2 {{ $index === 0 ? 'border-t border-ink-700 pt-3' : '' }}">
                                    <dt class="text-ink-400">{{ $index === 0 ? '1re' : ($index + 1).'e' }} place</dt>
                                    <dd class="text-right tabular text-ink-100">{{ $reward->display() }}</dd>
                                </div>
                            @empty
                                <div class="flex justify-between gap-2 border-t border-ink-700 pt-3">
                                    <dt class="text-ink-400">Récompense</dt>
                                    <dd class="text-ink-100">Pour la gloire</dd>
                                </div>
                            @endforelse
                        @endif

                        @if ($campaign->starts_at)
                            <div class="flex justify-between gap-2">
                                <dt class="text-ink-400">Début</dt>
                                <dd class="text-ink-100">{{ $campaign->starts_at->format('d/m/Y') }}</dd>
                            </div>
                        @endif

                        @if ($campaign->ends_at)
                            <div class="flex justify-between gap-2">
                                <dt class="text-ink-400">Fin</dt>
                                <dd class="text-ink-100">{{ $campaign->ends_at->format('d/m/Y') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                @if ($perPlatform->isNotEmpty() && ! $campaign->isContest())
                    <div class="card p-5 sm:p-6">
                        <h2 class="font-display text-lg font-bold text-ink-50">Par plateforme</h2>

                        <ul class="mt-4 space-y-4">
                            @foreach ($perPlatform as $row)
                                @php
                                    $platform = \App\Enums\Platform::from($row->platform);
                                    $share = $campaign->spent_cents > 0
                                        ? round($row->spent_cents / $campaign->spent_cents * 100)
                                        : 0;
                                @endphp
                                <li>
                                    <div class="flex items-baseline justify-between text-sm">
                                        <span class="font-medium text-ink-100">{{ $platform->label() }}</span>
                                        <span class="tabular text-ink-50">{{ Money::euros((int) $row->spent_cents) }}</span>
                                    </div>
                                    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-ink-700">
                                        <div class="h-full rounded-full bg-brand-500" style="width: {{ $share }}%"></div>
                                    </div>
                                    <p class="mt-1 text-xs tabular text-ink-400">
                                        {{ Money::views((int) $row->views) }} vues · {{ $share }} %
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card p-5 sm:p-6">
                    <h2 class="font-display text-base font-bold text-ink-50">Une question ?</h2>
                    <p class="mt-1 text-sm text-ink-300">L'équipe te répond directement depuis le site.</p>
                    <a href="{{ route('messages.index') }}" wire:navigate class="btn-ghost mt-4 w-full justify-center">Contacter le support</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
