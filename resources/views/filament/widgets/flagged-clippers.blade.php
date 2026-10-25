@php use Illuminate\Support\Carbon; @endphp

<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
        <x-slot name="heading">Clippeurs signalés</x-slot>
        <x-slot name="description">
            Ces clippeurs ont soumis des vidéos publiées avant le début d'une campagne : elles ont été refusées et ne comptent pas au classement.
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="py-2 text-left font-medium">Clippeur</th>
                        <th class="py-2 text-right font-medium">Clips refusés</th>
                        <th class="py-2 pl-6 text-left font-medium">Campagne</th>
                        <th class="py-2 text-right font-medium">Dernier signalement</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="py-2">
                                @if ($row->url)
                                    <a href="{{ $row->url }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $row->name }}</a>
                                @else
                                    <span class="font-medium">{{ $row->name }}</span>
                                @endif
                                @if ($row->pseudo)
                                    <span class="text-gray-500 dark:text-gray-400"> · {{ $row->pseudo }}</span>
                                @endif
                                @if ($row->banned)
                                    <span class="ml-1 text-xs font-medium text-danger-600 dark:text-danger-400">banni</span>
                                @endif
                            </td>
                            <td class="py-2 text-right font-semibold tabular-nums text-danger-600 dark:text-danger-400">{{ $row->clips }}</td>
                            <td class="py-2 pl-6 text-gray-500 dark:text-gray-400">{{ $row->campaigns }}</td>
                            <td class="py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ Carbon::parse($row->last_at)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
