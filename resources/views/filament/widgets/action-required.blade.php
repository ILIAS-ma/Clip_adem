<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">À traiter</x-slot>
        <x-slot name="description">
            @if ($allClear)
                Rien n'attend l'équipe pour le moment.
            @else
                Ce qui attend une décision ou une action. Touche une tuile pour y aller.
            @endif
        </x-slot>

        <div class="ad-tiles">
            @foreach ($tiles as $tile)
                <a href="{{ $tile['url'] }}"
                   class="ad-tile {{ $tile['n'] === 0 ? 'is-calm' : ($tile['tone'] === 'danger' ? 'is-danger' : 'is-warn') }}">
                    <div class="n">{{ $tile['n'] }}</div>
                    <div class="l">{{ $tile['label'] }}</div>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
