<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Top clippeurs</x-slot>
        <x-slot name="description">Gains cumulés et taux d'invalidation</x-slot>

        @if ($clippers->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Aucun clip rémunéré pour le moment.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="py-2 text-left font-medium">Clippeur</th>
                            <th class="py-2 text-right font-medium">Vues</th>
                            <th class="py-2 text-right font-medium">Gagné</th>
                            <th class="py-2 text-right font-medium">Invalidés</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($clippers as $clipper)
                            <tr>
                                <td class="py-2">
                                    <a href="{{ $resourceUrl($clipper->id) }}"
                                       class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $clipper->name }}
                                    </a>
                                    @if ($clipper->is_banned)
                                        <span class="ml-1 text-xs font-medium text-danger-600 dark:text-danger-400">banni</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right tabular-nums">
                                    {{ number_format($clipper->views, 0, ',', ' ') }}
                                </td>
                                <td class="py-2 text-right font-medium tabular-nums">
                                    {{ number_format($clipper->earned_cents / 100, 2, ',', ' ') }} €
                                </td>
                                {{-- Le taux d'invalidation révèle les profils qui coûtent du temps de modération. --}}
                                <td @class([
                                    'py-2 text-right tabular-nums',
                                    'text-danger-600 dark:text-danger-400 font-medium' => $clipper->invalidation_rate >= 20,
                                    'text-gray-500 dark:text-gray-400' => $clipper->invalidation_rate < 20,
                                ])>
                                    {{ $clipper->invalidation_rate }} %
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
