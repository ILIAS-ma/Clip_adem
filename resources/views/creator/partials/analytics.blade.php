@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;

    $leader = max(1, $topClippers->max('views') ?? 1);
    $biggestBucket = max(1, collect($distribution)->max('count'));
@endphp

{{--
    L'audience, en graphiques. Le même bloc sert au tableau de bord (toutes les
    campagnes) et à la page d'une campagne : on lit les vues des clips eux-mêmes,
    donc il parle aussi d'une campagne terminée, ou sans paiement aux vues.
--}}
<div class="space-y-4 sm:space-y-6">

    {{-- Évolution des vues --}}
    <div class="card p-5 sm:p-6">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="font-display text-lg font-bold text-ink-50">Évolution des vues</h2>
                <p class="mt-0.5 text-sm text-ink-400">Le total des vues de tous les clips, jour après jour.</p>
            </div>
            @if ($timeline['peak_day'])
                <p class="rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-300 ring-1 ring-brand-500/20">
                    Meilleur jour : {{ Carbon::parse($timeline['peak_day'])->format('d/m') }}
                    · +{{ Money::compactViews($timeline['peak_gain']) }} vues
                </p>
            @endif
        </div>

        <div class="mt-5">
            <x-chart-area :values="$timeline['cumulative']" :labels="$timeline['labels']" unit=" vues" />
        </div>

        @if (count($timeline['gains']) > 1)
            <div class="mt-6 border-t border-ink-800 pt-5">
                <x-mini-bars :series="array_combine($timeline['labels'], $timeline['gains'])" :height="72"
                             label="Vues gagnées chaque jour" unit=" vues" />
            </div>
        @endif
    </div>

    <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">

        {{-- Les clippeurs qui portent la campagne --}}
        <div class="card p-5 sm:p-6">
            <h2 class="font-display text-lg font-bold text-ink-50">Les clippeurs les plus actifs</h2>
            <p class="mt-0.5 text-sm text-ink-400">Classés sur les vues de leurs clips.</p>

            @if ($topClippers->isEmpty())
                <p class="mt-6 text-sm text-ink-300">Aucun clip validé pour le moment.</p>
            @else
                <ol class="mt-5 space-y-4">
                    @foreach ($topClippers as $index => $clipper)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="flex min-w-0 items-center gap-2.5">
                                    <span @class([
                                        'flex h-6 w-6 flex-none items-center justify-center rounded-full text-xs font-bold tabular',
                                        'bg-amber-400 text-ink-950' => $index === 0,
                                        'bg-ink-200 text-ink-950' => $index === 1,
                                        'bg-amber-700 text-white' => $index === 2,
                                        'bg-ink-800 text-ink-300' => $index > 2,
                                    ])>{{ $index + 1 }}</span>
                                    <span class="truncate font-medium text-ink-50">{{ $clipper->name }}</span>
                                </span>
                                <span class="flex-none font-semibold tabular text-ink-100">{{ Money::compactViews($clipper->views) }} vues</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-ink-800">
                                <div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400" style="width: {{ max(2, (int) round($clipper->views / $leader * 100)) }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-ink-400">{{ $clipper->clips }} clip{{ $clipper->clips > 1 ? 's' : '' }} · meilleur : {{ Money::compactViews($clipper->best) }} vues</p>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        {{-- Portée par quelques vidéos virales, ou par beaucoup de clips moyens ? --}}
        <div class="card p-5 sm:p-6">
            <h2 class="font-display text-lg font-bold text-ink-50">Répartition des clips</h2>
            <p class="mt-0.5 text-sm text-ink-400">Combien de clips dans chaque tranche de vues.</p>

            <ul class="mt-5 space-y-3.5">
                @foreach ($distribution as $bucket)
                    <li>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="text-ink-200">{{ $bucket['label'] }}</span>
                            <span class="tabular text-ink-100"><span class="font-semibold">{{ number_format($bucket['count'], 0, ',', ' ') }}</span> <span class="text-ink-500">· {{ $bucket['share'] }} %</span></span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-ink-800">
                            <div class="h-full rounded-full bg-brand-500/80" style="width: {{ $bucket['count'] > 0 ? max(2, (int) round($bucket['count'] / $biggestBucket * 100)) : 0 }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($platforms->count() > 0)
                <div class="mt-6 flex flex-wrap gap-2 border-t border-ink-800 pt-5">
                    @foreach ($platforms as $row)
                        <span class="chip bg-ink-800 text-ink-200">
                            {{ \App\Enums\Platform::from($row->platform)->label() }}
                            <span class="ms-1 tabular text-ink-400">{{ Money::compactViews($row->views) }} vues · {{ $row->clips }} clips</span>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Les clips publiés par jour --}}
    @if (count($posts['values']) > 1)
        <div class="card p-5 sm:p-6">
            <x-mini-bars :series="array_combine($posts['labels'], $posts['values'])" :height="72"
                         label="Clips publiés chaque jour" unit=" clips" />
        </div>
    @endif

    {{-- Les clips les plus vus, en vignettes --}}
    @if ($topClips->isNotEmpty())
        <div class="card p-5 sm:p-6">
            <h2 class="font-display text-lg font-bold text-ink-50">Les clips les plus vus</h2>
            <p class="mt-0.5 text-sm text-ink-400">Touche une vidéo pour l'ouvrir sur la plateforme.</p>

            <ul class="mt-5 grid grid-cols-3 gap-2 sm:grid-cols-6 sm:gap-3">
                @foreach ($topClips as $index => $clip)
                    <li>
                        <a href="{{ $clip->url }}" target="_blank" rel="noopener noreferrer"
                           class="group relative block aspect-[9/16] overflow-hidden rounded-xl bg-ink-800 ring-1 ring-ink-700 transition hover:ring-brand-500">
                            @if ($clip->thumbnail_url)
                                <img src="{{ $clip->thumbnail_url }}" alt="" loading="lazy" referrerpolicy="no-referrer"
                                     onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">
                            @endif
                            <span aria-hidden="true" class="absolute inset-0 bg-gradient-to-t from-ink-950/95 via-ink-950/10 to-transparent"></span>
                            <span class="absolute left-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-ink-950/80 text-xs font-bold text-white ring-1 ring-white/20">{{ $index + 1 }}</span>
                            <span class="absolute inset-x-1.5 bottom-1.5">
                                <span class="block text-sm font-bold tabular text-white">{{ Money::compactViews($clip->views_total) }} vues</span>
                                {{-- Le pseudo, jamais le nom réel ni le contact. --}}
                                <span class="block truncate text-[11px] text-ink-300">{{ $clip->user?->displayName() }}</span>
                            </span>
                            <span class="sr-only">Voir la publication de {{ $clip->user?->displayName() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
