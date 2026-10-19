@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-ink-50">Classement</h1>
        <p class="mt-1 text-ink-300">Les clippeurs qui rapportent le plus : cette semaine, sur la saison, depuis toujours et par catégorie.</p>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-4 px-4 py-5 sm:space-y-6 sm:px-6 sm:py-8 lg:px-8">

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="border-b border-ink-700 px-6 py-4">
                    <h2 class="font-display text-lg font-bold text-ink-50">Cette semaine</h2>
                    <p class="mt-0.5 text-sm text-ink-300">Vues gagnées sur les 7 derniers jours.</p>
                </div>

                @if ($topThisWeek->isEmpty())
                    <p class="px-6 py-10 text-center text-sm text-ink-300">Personne n'a encore de vues créditées cette semaine.</p>
                @else
                    <ol class="divide-y divide-ink-700">
                        @foreach ($topThisWeek as $index => $row)
                            <li @class([
                                'flex items-center gap-4 px-6 py-3.5',
                                'bg-brand-500/10' => $row->id === auth()->id(),
                            ])>
                                <span class="w-6 flex-none text-sm font-semibold tabular text-ink-400">{{ $index + 1 }}</span>
                                <span class="min-w-0 flex-1 truncate font-medium text-ink-50">
                                    {{ $row->name }}
                                    @if ($row->id === auth()->id())
                                        <span class="text-xs font-normal text-brand-400">(vous)</span>
                                    @endif
                                </span>
                                <span class="flex-none text-sm font-semibold tabular text-brand-400">{{ Money::views($row->views) }} vues</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            <div class="card">
                <div class="border-b border-ink-700 px-6 py-4">
                    <h2 class="font-display text-lg font-bold text-ink-50">Depuis toujours</h2>
                    <p class="mt-0.5 text-sm text-ink-300">Gains cumulés sur toute la carrière.</p>
                </div>

                @if ($topAllTime->isEmpty())
                    <p class="px-6 py-10 text-center text-sm text-ink-300">Aucun clip rémunéré pour le moment.</p>
                @else
                    <ol class="divide-y divide-ink-700">
                        @foreach ($topAllTime as $index => $row)
                            <li @class([
                                'flex items-center gap-4 px-6 py-3.5',
                                'bg-brand-500/10' => $row->id === auth()->id(),
                            ])>
                                <span class="w-6 flex-none text-sm font-semibold tabular text-ink-400">{{ $index + 1 }}</span>
                                <span class="min-w-0 flex-1 truncate font-medium text-ink-50">
                                    {{ $row->name }}
                                    @if ($row->id === auth()->id())
                                        <span class="text-xs font-normal text-brand-400">(vous)</span>
                                    @endif
                                </span>
                                <span class="flex-none text-sm font-semibold tabular text-ink-50">{{ Money::euros($row->earned_cents) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>

        @if ($season)
            <div class="card" id="saison">
                <div class="border-b border-ink-700 px-6 py-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
                        <div>
                            <h2 class="font-display text-lg font-bold text-ink-50">{{ $season->name }}</h2>
                            <p class="mt-0.5 text-sm text-ink-300">
                                Du {{ $season->starts_on->translatedFormat('j F') }} au {{ $season->ends_on->translatedFormat('j F Y') }}
                                ·
                                @if ($season->isRunning())
                                    <span class="font-semibold text-brand-400">{{ $season->daysLeft() }} {{ $season->daysLeft() > 1 ? 'jours restants' : 'dernier jour' }}</span>
                                @else
                                    <span class="text-ink-400">terminée</span>
                                @endif
                            </p>
                            @if ($season->prize)
                                <p class="mt-1 text-sm text-ink-200"><span class="font-semibold text-brand-400">Récompense :</span> {{ $season->prize }}</p>
                            @endif
                        </div>

                        @if ($seasons->count() > 1)
                            <nav class="flex flex-wrap gap-2" aria-label="Saisons">
                                @foreach ($seasons as $item)
                                    <a href="{{ route('leaderboard.index', array_filter(['saison' => $item->id, 'categorie' => $category?->slug])) }}#saison"
                                       @if ($item->is($season)) aria-current="page" @endif
                                       @class([
                                           'rounded-full px-3 py-1 text-xs font-medium transition',
                                           'bg-brand-500 text-white' => $item->is($season),
                                           'bg-ink-800 text-ink-300 hover:bg-ink-700 hover:text-ink-50' => ! $item->is($season),
                                       ])>{{ $item->name }}</a>
                                @endforeach
                            </nav>
                        @endif
                    </div>
                </div>

                @if ($topInSeason->isEmpty())
                    <p class="px-6 py-10 text-center text-sm text-ink-300">Personne n'a encore gagné pendant cette saison. La première place est libre.</p>
                @else
                    <ol class="divide-y divide-ink-700">
                        @foreach ($topInSeason as $index => $row)
                            <li @class([
                                'flex items-center gap-4 px-6 py-3.5',
                                'bg-brand-500/10' => $row->id === auth()->id(),
                            ])>
                                <span class="w-6 flex-none text-sm font-semibold tabular text-ink-400">{{ $index + 1 }}</span>
                                <span class="min-w-0 flex-1 truncate font-medium text-ink-50">
                                    {{ $row->name }}
                                    @if ($row->id === auth()->id())
                                        <span class="text-xs font-normal text-brand-400">(vous)</span>
                                    @endif
                                </span>
                                <span class="w-24 flex-none text-right text-sm font-semibold tabular text-brand-400 sm:w-32">{{ Money::views($row->views) }} vues</span>
                                <span class="w-24 flex-none text-right text-sm font-semibold tabular text-ink-50 sm:w-32">{{ Money::euros($row->earned_cents) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endif

        @if ($topReferrers->isNotEmpty())
            <div class="card" id="parrains">
                <div class="border-b border-ink-700 px-6 py-4">
                    <h2 class="font-display text-lg font-bold text-ink-50">Top parrains</h2>
                    <p class="mt-0.5 text-sm text-ink-300">
                        Ceux qui gagnent le plus en faisant venir d'autres clippeurs.
                        <a href="{{ route('referrals.index') }}" wire:navigate class="text-brand-400 underline underline-offset-2">Mon lien de parrainage</a>
                    </p>
                </div>

                <ol class="divide-y divide-ink-700">
                    @foreach ($topReferrers as $index => $row)
                        <li @class([
                            'flex items-center gap-4 px-6 py-3.5',
                            'bg-brand-500/10' => $row->id === auth()->id(),
                        ])>
                            <span class="w-6 flex-none text-sm font-semibold tabular text-ink-400">{{ $index + 1 }}</span>
                            <span class="min-w-0 flex-1 truncate font-medium text-ink-50">
                                {{ $row->name }}
                                @if ($row->id === auth()->id())
                                    <span class="text-xs font-normal text-brand-400">(vous)</span>
                                @endif
                            </span>
                            <span class="w-24 flex-none text-right text-sm tabular text-ink-300 sm:w-32">{{ $row->referrals }} filleul{{ $row->referrals > 1 ? 's' : '' }}</span>
                            <span class="w-24 flex-none text-right text-sm font-semibold tabular text-ink-50 sm:w-32">{{ Money::euros($row->earned_cents) }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if ($categories->isNotEmpty())
            <div class="card" id="par-categorie">
                <div class="border-b border-ink-700 px-6 py-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                        <div>
                            <h2 class="font-display text-lg font-bold text-ink-50">Par catégorie</h2>
                            <p class="mt-0.5 text-sm text-ink-300">Vues et gains cumulés depuis toujours, sur toutes les campagnes de la catégorie.</p>
                        </div>
                        @if ($categoryTotals)
                            <p class="text-sm text-ink-300">
                                <span class="font-semibold tabular text-ink-50">{{ Money::views($categoryTotals['views']) }}</span> vues
                                · <span class="font-semibold tabular text-ink-50">{{ Money::euros($categoryTotals['earned_cents']) }}</span> versés
                            </p>
                        @endif
                    </div>

                    <nav class="mt-4 flex flex-wrap gap-2" aria-label="Catégories">
                        @foreach ($categories as $item)
                            <a href="{{ route('leaderboard.index', array_filter(['categorie' => $item->slug, 'saison' => $season?->id])) }}#par-categorie"
                               @if ($item->is($category)) aria-current="page" @endif
                               @class([
                                   'rounded-full px-3.5 py-1.5 text-sm font-medium transition',
                                   'bg-brand-500 text-white' => $item->is($category),
                                   'bg-ink-800 text-ink-300 hover:bg-ink-700 hover:text-ink-50' => ! $item->is($category),
                               ])>
                                {{ $item->name }}
                            </a>
                        @endforeach
                    </nav>
                </div>

                @if ($topInCategory->isEmpty())
                    <p class="px-6 py-10 text-center text-sm text-ink-300">Aucune vue ni aucun gain dans cette catégorie pour le moment.</p>
                @else
                    <div class="flex items-center gap-4 border-b border-ink-700 px-6 py-2 text-xs font-medium uppercase tracking-wide text-ink-400">
                        <span class="w-6 flex-none">#</span>
                        <span class="min-w-0 flex-1">Clippeur</span>
                        <span class="w-24 flex-none text-right sm:w-32">Vues</span>
                        <span class="w-24 flex-none text-right sm:w-32">Gains</span>
                    </div>
                    <ol class="divide-y divide-ink-700">
                        @foreach ($topInCategory as $index => $row)
                            <li @class([
                                'flex items-center gap-4 px-6 py-3.5',
                                'bg-brand-500/10' => $row->id === auth()->id(),
                            ])>
                                <span class="w-6 flex-none text-sm font-semibold tabular text-ink-400">{{ $index + 1 }}</span>
                                <span class="min-w-0 flex-1 truncate font-medium text-ink-50">
                                    {{ $row->name }}
                                    @if ($row->id === auth()->id())
                                        <span class="text-xs font-normal text-brand-400">(vous)</span>
                                    @endif
                                </span>
                                <span class="w-24 flex-none text-right text-sm font-semibold tabular text-brand-400 sm:w-32">{{ Money::views($row->views) }}</span>
                                <span class="w-24 flex-none text-right text-sm font-semibold tabular text-ink-50 sm:w-32">{{ Money::euros($row->earned_cents) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
