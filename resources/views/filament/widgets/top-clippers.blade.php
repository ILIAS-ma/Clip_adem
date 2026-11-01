<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Top clippeurs</x-slot>
        <x-slot name="description">Gains cumulés et taux d'invalidation</x-slot>

        @if ($clippers->isEmpty())
            <p class="ad-empty">Aucun clip rémunéré pour le moment.</p>
        @else
            <div class="ad-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>Clippeur</th>
                            <th class="r">Vues</th>
                            <th class="r">Gagné</th>
                            <th class="r">Invalidés</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clippers as $clipper)
                            <tr>
                                <td class="main">
                                    <a href="{{ $resourceUrl($clipper->id) }}" class="ad-link">{{ $clipper->name }}</a>
                                    @if ($clipper->is_banned)<span class="ad-bad">banni</span>@endif
                                </td>
                                <td class="r" data-label="Vues">{{ number_format($clipper->views, 0, ',', ' ') }}</td>
                                <td class="r" data-label="Gagné"><strong>{{ number_format($clipper->earned_cents / 100, 2, ',', ' ') }} €</strong></td>
                                {{-- Le taux d'invalidation révèle les profils qui coûtent du temps de modération. --}}
                                <td class="r {{ $clipper->invalidation_rate >= 20 ? 'ad-danger' : 'ad-muted' }}" data-label="Invalidés">{{ $clipper->invalidation_rate }} %</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
