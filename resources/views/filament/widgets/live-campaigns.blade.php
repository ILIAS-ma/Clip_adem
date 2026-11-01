@if ($campaigns !== [])
    <x-filament-widgets::widget>
        <x-filament::section>
            <x-slot name="heading">Campagnes en cours</x-slot>
            <x-slot name="description">{{ count($campaigns) }} campagne{{ count($campaigns) > 1 ? 's' : '' }} active{{ count($campaigns) > 1 ? 's' : '' }}</x-slot>

            <div class="ad-camps">
                @foreach ($campaigns as $campaign)
                    <div class="ad-camp">
                        <div>
                            <div class="t">{{ $campaign['title'] }}</div>
                            <div class="s">
                                {{ $campaign['kind'] }} · {{ $campaign['ends'] }}
                                @if ($campaign['soon'])<span class="ad-pill">bientôt fini</span>@endif
                            </div>
                        </div>

                        <div class="figs">
                            <div><b>{{ number_format($campaign['clips'], 0, ',', ' ') }}</b><span>clips</span></div>
                            <div><b>{{ number_format($campaign['views'], 0, ',', ' ') }}</b><span>vues</span></div>
                            <div><b>{{ $campaign['today'] }}</b><span>aujourd'hui</span></div>
                        </div>

                        @if ($campaign['top'] !== [])
                            <ol>
                                @foreach ($campaign['top'] as $row)
                                    <li>
                                        <span><span class="ad-muted">{{ $loop->iteration }}.</span> {{ $row['name'] }}</span>
                                        <strong style="font-variant-numeric:tabular-nums;">{{ number_format($row['views'], 0, ',', ' ') }}</strong>
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        <div class="links">
                            <a href="{{ $campaign['stats'] }}" class="ad-link">Statistiques</a>
                            <a href="{{ $campaign['edit'] }}" class="ad-link">Gérer</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </x-filament-widgets::widget>
@endif
