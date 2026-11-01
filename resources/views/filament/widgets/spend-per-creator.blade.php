<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Dépenses par créateur</x-slot>
        <x-slot name="description">CPM réel : coût effectif pour 1000 vues</x-slot>

        @if ($creators->isEmpty())
            <p class="ad-empty">Aucune dépense enregistrée pour le moment.</p>
        @else
            <div class="ad-wrap">
                <table class="ad-table">
                    <thead>
                        <tr>
                            <th>Créateur</th>
                            <th class="r">Dépensé</th>
                            <th class="r">Vues</th>
                            <th class="r">CPM réel</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($creators as $creator)
                            <tr>
                                <td class="main">
                                    <a href="{{ $resourceUrl($creator->id) }}" class="ad-link">{{ $creator->name }}</a>
                                    <div class="ad-muted" style="font-size:.75rem;font-weight:400;">
                                        {{ $creator->campaigns_count }} campagne{{ $creator->campaigns_count > 1 ? 's' : '' }}
                                        · budget {{ number_format($creator->budget_cents / 100, 0, ',', ' ') }} €
                                    </div>
                                </td>
                                <td class="r" data-label="Dépensé"><strong>{{ number_format($creator->spent_cents / 100, 2, ',', ' ') }} €</strong></td>
                                <td class="r ad-muted" data-label="Vues">{{ number_format($creator->views, 0, ',', ' ') }}</td>
                                <td class="r" data-label="CPM réel">{{ $creator->real_cpm_cents === null ? '—' : number_format($creator->real_cpm_cents / 100, 2, ',', ' ').' €' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
