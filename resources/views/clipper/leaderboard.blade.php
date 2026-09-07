@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-ink-50">Classement</h1>
        <p class="mt-1 text-ink-300">Les clippeurs qui rapportent le plus, cette semaine et depuis toujours.</p>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

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
    </div>
</x-app-layout>
