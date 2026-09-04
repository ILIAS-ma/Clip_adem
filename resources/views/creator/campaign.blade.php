@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $creator->name }}</p>
                <h1 class="mt-1 font-display text-2xl font-bold text-ink-50">{{ $campaign->title }}</h1>
            </div>

            <a href="{{ route('creator.dashboard') }}" class="btn-ghost">← Mes campagnes</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat label="Budget total" :value="Money::euros($campaign->budget_total_cents)" />

            <x-stat label="Dépensé" :value="Money::euros($campaign->spent_cents)"
                    :hint="$campaign->consumedPercent().' % du budget'" />

            <x-stat label="Vues générées" :value="Money::views($views)" tone="money"
                    :hint="$clips->count().' clip'.($clips->count() > 1 ? 's' : '').' validé'.($clips->count() > 1 ? 's' : '')" />

            <x-stat label="Coût réel / 1000 vues"
                    :value="$views > 0 ? Money::euros(intdiv($campaign->spent_cents * 1000, $views)) : '—'"
                    highlight hint="Rendement effectif" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">

            <div class="space-y-6 lg:col-span-2">
                <div class="card">
                    <div class="border-b border-ink-700 px-6 py-4">
                        <h2 class="font-display text-lg font-bold text-ink-50">Les clips</h2>
                        <p class="mt-0.5 text-sm text-ink-400">Classés par nombre de vues.</p>
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
                                        <th class="px-6 py-3 text-left font-semibold">Clippeur</th>
                                        <th class="px-6 py-3 text-left font-semibold">Plateforme</th>
                                        <th class="px-6 py-3 text-right font-semibold">Vues</th>
                                        <th class="px-6 py-3 text-right font-semibold">Coût</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-ink-700">
                                    @foreach ($clips as $clip)
                                        <tr>
                                            {{-- Pseudo uniquement : le créateur n'a pas à connaître
                                                 l'identité civile ni les coordonnées des clippeurs. --}}
                                            <td class="px-6 py-3 font-medium text-ink-100">
                                                {{ $clip->user?->displayName() }}
                                            </td>
                                            <td class="px-6 py-3">
                                                <a href="{{ $clip->url }}" target="_blank" rel="noopener"
                                                   class="text-ink-300 underline-offset-2 hover:text-ink-50 hover:underline">
                                                    {{ $clip->platform->label() }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-3 text-right tabular text-ink-100">
                                                {{ Money::views($clip->views_total) }}
                                            </td>
                                            <td class="px-6 py-3 text-right font-semibold tabular text-ink-50">
                                                {{ Money::euros($clip->earned_cents) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                @if ($campaign->brief)
                    <div class="card p-6">
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

            <div class="space-y-6">
                <div class="card p-6">
                    <x-budget-bar :campaign="$campaign" :remaining="$remainingCents" />

                    <dl class="mt-6 space-y-3 border-t border-ink-700 pt-5 text-sm">
                        @foreach ($campaign->rates->where('is_enabled', true) as $rate)
                            <div class="flex items-baseline justify-between gap-2">
                                <dt class="text-ink-400">{{ $rate->platform->label() }}</dt>
                                <dd class="font-display font-bold tabular text-ink-50">
                                    {{ Money::rate($rate->rate_per_1k_cents) }}<span class="text-xs font-medium text-ink-400"> /1000</span>
                                </dd>
                            </div>
                        @endforeach

                        @if ($campaign->target_views)
                            <div class="flex justify-between gap-2 border-t border-ink-700 pt-3">
                                <dt class="text-ink-400">Objectif de vues</dt>
                                <dd class="tabular text-ink-100">{{ Money::views($campaign->target_views) }}</dd>
                            </div>
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

                @if ($perPlatform->isNotEmpty())
                    <div class="card p-6">
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
            </div>
        </div>
    </div>
</x-app-layout>
