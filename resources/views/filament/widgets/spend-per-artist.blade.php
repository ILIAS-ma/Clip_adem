<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Dépenses par artiste</x-slot>
        <x-slot name="description">CPM réel : coût effectif pour 1000 vues</x-slot>

        @if ($artists->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Aucune dépense enregistrée pour le moment.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="py-2 text-left font-medium">Artiste</th>
                            <th class="py-2 text-right font-medium">Dépensé</th>
                            <th class="py-2 text-right font-medium">Vues</th>
                            <th class="py-2 text-right font-medium">CPM réel</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-ink-700">
                        @foreach ($artists as $artist)
                            <tr>
                                <td class="py-2">
                                    <a href="{{ $resourceUrl($artist->id) }}"
                                       class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $artist->name }}
                                    </a>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $artist->campaigns_count }} campagne{{ $artist->campaigns_count > 1 ? 's' : '' }}
                                        · budget {{ number_format($artist->budget_cents / 100, 0, ',', ' ') }} €
                                    </div>
                                </td>
                                <td class="py-2 text-right font-medium tabular-nums">
                                    {{ number_format($artist->spent_cents / 100, 2, ',', ' ') }} €
                                </td>
                                <td class="py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">
                                    {{ number_format($artist->views, 0, ',', ' ') }}
                                </td>
                                <td class="py-2 text-right tabular-nums">
                                    {{ $artist->real_cpm_cents === null
                                        ? '—'
                                        : number_format($artist->real_cpm_cents / 100, 2, ',', ' ') . ' €' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
