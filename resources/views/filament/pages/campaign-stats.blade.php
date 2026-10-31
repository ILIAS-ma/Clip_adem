<x-filament-panels::page>
    <style>
        /* Téléphone : cartes à la place du tableau, bouton pleine largeur, chiffres clés sur deux colonnes. */
        .cs-cards { display: none; }
        @media (max-width: 640px) {
            .cs-table { display: none; }
            .cs-cards { display: grid; gap: .6rem; }
            .cs-actions { flex-direction: column; align-items: stretch !important; }
            .cs-actions > * { max-width: none !important; width: 100%; }
            .cs-actions .fi-btn { width: 100%; justify-content: center; }
            .cs-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        }
    </style>

    {{--
        Mise en page en styles en ligne : le back-office n'a pas de thème compilé à part,
        une classe Tailwind absente de la feuille de Filament ne s'appliquerait pas.
    --}}
    @php
        $campaign = $this->campaign();
        $stats = $this->stats();
        $card = 'border:1px solid rgba(128,128,128,.25);background:rgba(128,128,128,.07);border-radius:.85rem;';
    @endphp

    <div style="display:grid;gap:1.25rem;">

        {{-- Le choix de la campagne et le téléchargement --}}
        <div class="cs-actions" style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:end;justify-content:space-between;">
            <div style="min-width:260px;flex:1;max-width:460px;">
                <label class="text-sm text-gray-500 dark:text-gray-400" for="campaign-choice">Campagne</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select id="campaign-choice" wire:model.live="campaignId">
                        @foreach ($this->campaigns() as $id => $title)
                            <option value="{{ $id }}">#{{ $id }} · {{ $title }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>

            @if ($campaign)
                <x-filament::button wire:click="export" icon="heroicon-o-arrow-down-tray" wire:loading.attr="disabled" wire:target="export">
                    Télécharger l'Excel
                </x-filament::button>
            @endif
        </div>

        @if ($stats)
            <p class="text-sm text-gray-500 dark:text-gray-400" style="margin:0;">
                Le fichier contient : le résumé, les classements (vues et clips), le détail par clippeur, tous les clips avec leur lien,
                l'activité jour par jour et l'historique de modération.
            </p>

            {{-- Les chiffres clés --}}
            <div class="cs-kpis" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;">
                @foreach ($stats['kpis'] as $kpi)
                    <div style="{{ $card }}padding:.85rem 1rem;">
                        <div class="text-gray-500 dark:text-gray-400" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">{{ $kpi['label'] }}</div>
                        <div style="font-size:1.55rem;font-weight:700;line-height:1.2;margin-top:.15rem;font-variant-numeric:tabular-nums;">{{ $kpi['value'] }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Le détail par clippeur --}}
            <x-filament::section heading="Clippeurs" :description="count($stats['clippers']).' clippeur(s) ont soumis des clips'">
                @if ($stats['clippers'] === [])
                    <p class="text-sm text-gray-500 dark:text-gray-400" style="margin:0;">Aucun clip n'a encore été soumis sur cette campagne.</p>
                @else
                    <div class="cs-table" style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                            <thead>
                                <tr class="text-gray-500 dark:text-gray-400" style="text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">
                                    <th style="padding:.4rem .6rem .4rem 0;">Clippeur</th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Soumis</th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Validés</th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Refusés</th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Vues</th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Moy. / clip</th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Meilleur clip</th>
                                    <th style="padding:.4rem 0 .4rem .6rem;text-align:right;">Signalés</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_slice($stats['clippers'], 0, 25) as $row)
                                    <tr style="border-top:1px solid rgba(128,128,128,.18);">
                                        <td style="padding:.5rem .6rem .5rem 0;font-weight:600;">
                                            {{ $row['name'] }}@if ($row['pseudo'] && $row['pseudo'] !== $row['name'])<span class="text-gray-500 dark:text-gray-400" style="font-weight:400;"> · {{ $row['pseudo'] }}</span>@endif
                                        </td>
                                        <td style="padding:.5rem .6rem;text-align:right;font-variant-numeric:tabular-nums;">{{ $row['submitted'] }}</td>
                                        <td style="padding:.5rem .6rem;text-align:right;font-variant-numeric:tabular-nums;">{{ $row['approved'] }}</td>
                                        <td style="padding:.5rem .6rem;text-align:right;font-variant-numeric:tabular-nums;">{{ $row['rejected'] ?: '—' }}</td>
                                        <td style="padding:.5rem .6rem;text-align:right;font-variant-numeric:tabular-nums;font-weight:700;">{{ number_format($row['views'], 0, ',', ' ') }}</td>
                                        <td style="padding:.5rem .6rem;text-align:right;font-variant-numeric:tabular-nums;">{{ number_format($row['average'], 0, ',', ' ') }}</td>
                                        <td style="padding:.5rem .6rem;text-align:right;font-variant-numeric:tabular-nums;">{{ number_format($row['best'], 0, ',', ' ') }}</td>
                                        <td style="padding:.5rem 0 .5rem .6rem;text-align:right;{{ $row['flags'] ? 'color:rgb(var(--danger-500));font-weight:700;' : '' }}">{{ $row['flags'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{-- Téléphone : une carte par clippeur --}}
                    <div class="cs-cards">
                        @foreach (array_slice($stats['clippers'], 0, 25) as $row)
                            <div style="{{ $card }}padding:.75rem .9rem;">
                                <div style="font-weight:700;word-break:break-word;">
                                    {{ $row['name'] }}@if ($row['pseudo'] && $row['pseudo'] !== $row['name'])<span class="text-gray-500 dark:text-gray-400" style="font-weight:400;"> · {{ $row['pseudo'] }}</span>@endif
                                </div>
                                <div style="font-size:1.35rem;font-weight:800;margin:.2rem 0 .5rem;font-variant-numeric:tabular-nums;">
                                    {{ number_format($row['views'], 0, ',', ' ') }} <span class="text-gray-500 dark:text-gray-400" style="font-size:.8rem;font-weight:500;">vues</span>
                                </div>
                                <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.4rem;font-size:.8rem;">
                                    <div><div class="text-gray-500 dark:text-gray-400">Validés</div><strong>{{ $row['approved'] }}</strong> <span class="text-gray-500 dark:text-gray-400">/ {{ $row['submitted'] }}</span></div>
                                    <div><div class="text-gray-500 dark:text-gray-400">Moy. / clip</div><strong>{{ number_format($row['average'], 0, ',', ' ') }}</strong></div>
                                    <div><div class="text-gray-500 dark:text-gray-400">Meilleur</div><strong>{{ number_format($row['best'], 0, ',', ' ') }}</strong></div>
                                </div>
                                @if ($row['rejected'] || $row['flags'])
                                    <div style="margin-top:.5rem;display:flex;flex-wrap:wrap;gap:.4rem;font-size:.75rem;font-weight:600;">
                                        @if ($row['rejected'])<span style="padding:.15rem .5rem;border-radius:999px;background:rgba(128,128,128,.2);">{{ $row['rejected'] }} refusé{{ $row['rejected'] > 1 ? 's' : '' }}</span>@endif
                                        @if ($row['flags'])<span style="padding:.15rem .5rem;border-radius:999px;background:rgba(var(--danger-500),.15);color:rgb(var(--danger-500));">⚠ {{ $row['flags'] }} signalé{{ $row['flags'] > 1 ? 's' : '' }}</span>@endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if (count($stats['clippers']) > 25)
                        <p class="text-sm text-gray-500 dark:text-gray-400" style="margin:.75rem 0 0;">
                            Les 25 premiers sont affichés ; le fichier Excel contient les {{ count($stats['clippers']) }}.
                        </p>
                    @endif
                @endif
            </x-filament::section>

            {{-- L'activité jour par jour --}}
            @if ($stats['days'] !== [])
                <x-filament::section heading="Activité par jour">
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                            <thead>
                                <tr class="text-gray-500 dark:text-gray-400" style="text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">
                                    <th style="padding:.4rem .6rem .4rem 0;">Jour</th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Clips soumis</th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Clippeurs actifs</th>
                                    <th style="padding:.4rem 0 .4rem .6rem;text-align:right;">Vues actuelles</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stats['days'] as $day)
                                    <tr style="border-top:1px solid rgba(128,128,128,.18);">
                                        <td style="padding:.5rem .6rem .5rem 0;font-weight:600;">{{ $day['day'] }}</td>
                                        <td style="padding:.5rem .6rem;text-align:right;font-variant-numeric:tabular-nums;">{{ $day['clips'] }}</td>
                                        <td style="padding:.5rem .6rem;text-align:right;font-variant-numeric:tabular-nums;">{{ $day['clippers'] }}</td>
                                        <td style="padding:.5rem 0 .5rem .6rem;text-align:right;font-variant-numeric:tabular-nums;">{{ number_format($day['views'], 0, ',', ' ') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Aucune campagne.</p>
        @endif
    </div>
</x-filament-panels::page>
