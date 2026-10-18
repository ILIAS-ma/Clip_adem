<div class="space-y-6">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        @if ($campaign->contestIsOver())
            Classements arrêtés{{ $campaign->ends_at ? ' au '.$campaign->ends_at->format('d/m/Y H:i') : '' }} : seuls les relevés et les clips antérieurs comptent.
        @else
            Classements en direct, sur les clips validés.
        @endif
        @if ($campaign->prizes_paid_at)
            Récompenses attribuées le {{ $campaign->prizes_paid_at->format('d/m/Y') }}.
        @endif
    </p>

    @foreach ($boards as $board)
        <div>
            <h3 class="mb-2 text-base font-semibold text-gray-950 dark:text-white">{{ $board['metric']->title() }}</h3>

            @if ($board['standings']->isEmpty())
                <p class="py-4 text-sm text-gray-500 dark:text-gray-400">Personne n'est encore classé.</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="py-2 pe-3">#</th>
                            <th class="py-2 pe-3">Clippeur</th>
                            <th class="py-2 pe-3 text-right">Clips</th>
                            <th class="py-2 pe-3 text-right">Vues</th>
                            <th class="py-2 text-right">Récompense</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($board['standings'] as $row)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2 pe-3 font-semibold">{{ $row->rank }}</td>
                                <td class="py-2 pe-3">{{ $row->name }}</td>
                                <td @class(['py-2 pe-3 text-right', 'font-semibold' => $board['metric'] === \App\Enums\ContestMetric::Posts])>{{ $row->clips }}</td>
                                <td @class(['py-2 pe-3 text-right', 'font-semibold' => $board['metric'] === \App\Enums\ContestMetric::Views])>{{ number_format($row->views, 0, ',', ' ') }}</td>
                                <td class="py-2 text-right">{{ $campaign->rewardFor($row->rank, $board['metric'])?->display() ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
</div>
