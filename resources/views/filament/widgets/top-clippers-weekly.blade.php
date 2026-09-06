<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Top clippeurs — 7 derniers jours</x-slot>
        <x-slot name="description">Vues gagnées cette semaine, invalidations déduites</x-slot>

        @if ($clippers->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Aucune vue créditée cette semaine.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="py-2 text-left font-medium">#</th>
                            <th class="py-2 text-left font-medium">Clippeur</th>
                            <th class="py-2 text-right font-medium">Vues (7j)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-ink-700">
                        @foreach ($clippers as $clipper)
                            <tr>
                                <td class="py-2 text-gray-500 dark:text-gray-400 tabular-nums">
                                    {{ $loop->iteration }}
                                </td>
                                <td class="py-2">
                                    <a href="{{ $resourceUrl($clipper->id) }}"
                                       class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $clipper->name }}
                                    </a>
                                    @if ($clipper->is_banned)
                                        <span class="ml-1 text-xs font-medium text-danger-600 dark:text-danger-400">banni</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right font-medium tabular-nums">
                                    {{ number_format($clipper->views, 0, ',', ' ') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
