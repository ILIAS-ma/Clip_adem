@php use Illuminate\Support\Carbon; @endphp

<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
        <x-slot name="heading">Clippeurs signalés</x-slot>
        <x-slot name="description">
            Vidéos publiées avant le début d'une campagne (refusées, elles ne comptent pas) ou qui ressemblent à une vidéo plus ancienne du même compte (reprise probable, à vérifier à la main : le clip reste validé).
        </x-slot>

        <div class="ad-wrap">
            <table class="ad-table">
                <thead>
                    <tr>
                        <th>Clippeur</th>
                        <th class="r">Clips refusés</th>
                        <th class="r">Reprises à vérifier</th>
                        <th>Campagne</th>
                        <th class="r">Dernier signalement</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="main">
                                @if ($row->url)
                                    <a href="{{ $row->url }}" class="ad-link">{{ $row->name }}</a>
                                @else
                                    {{ $row->name }}
                                @endif
                                @if ($row->pseudo && $row->pseudo !== $row->name)<span class="ad-muted" style="font-weight:400;"> · {{ $row->pseudo }}</span>@endif
                                @if ($row->banned)<span class="ad-bad">banni</span>@endif
                            </td>
                            <td class="r {{ $row->refused ? 'ad-danger' : 'ad-muted' }}" data-label="Clips refusés">{{ $row->refused }}</td>
                            <td class="r {{ $row->reuploads ? 'ad-warn' : 'ad-muted' }}" data-label="Reprises à vérifier">{{ $row->reuploads }}</td>
                            <td class="ad-muted" data-label="Campagne">{{ $row->campaigns }}</td>
                            <td class="r ad-muted" data-label="Dernier signalement">{{ Carbon::parse($row->last_at)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
