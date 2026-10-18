<div class="space-y-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        @if ($campaign->contestIsOver())
            Classement arrêté{{ $campaign->ends_at ? ' au '.$campaign->ends_at->format('d/m/Y H:i') : '' }} : vues relevées jusqu'à cette date.
        @else
            Classement en direct, sur les vues des clips validés.
        @endif
        @if ($campaign->prizes_paid_at)
            Récompenses attribuées le {{ $campaign->prizes_paid_at->format('d/m/Y') }}.
        @endif
    </p>

    @if ($standings->isEmpty())
        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Aucun clip validé avec des vues pour l'instant.</p>
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
                @foreach ($standings as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="py-2 pe-3 font-semibold">{{ $row->rank }}</td>
                        <td class="py-2 pe-3">{{ $row->name }}</td>
                        <td class="py-2 pe-3 text-right">{{ $row->clips }}</td>
                        <td class="py-2 pe-3 text-right font-semibold">{{ number_format($row->views, 0, ',', ' ') }}</td>
                        <td class="py-2 text-right">{{ $campaign->rewardFor($row->rank)?->display() ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
