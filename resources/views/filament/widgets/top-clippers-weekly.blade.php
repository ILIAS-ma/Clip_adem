<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Top clippeurs — 7 derniers jours</x-slot>
        <x-slot name="description">Vues gagnées cette semaine, invalidations déduites</x-slot>

        @if ($clippers->isEmpty())
            <p class="ad-empty">Aucune vue créditée cette semaine.</p>
        @else
            <div class="ad-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>Clippeur</th>
                            <th class="r">Vues (7 j)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clippers as $clipper)
                            <tr>
                                <td class="main">
                                    <span class="ad-muted" style="font-weight:400;">{{ $loop->iteration }}.</span>
                                    <a href="{{ $resourceUrl($clipper->id) }}" class="ad-link">{{ $clipper->name }}</a>
                                    @if ($clipper->is_banned)<span class="ad-bad">banni</span>@endif
                                </td>
                                <td class="r" data-label="Vues (7 j)"><strong>{{ number_format($clipper->views, 0, ',', ' ') }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
