@php
    use App\Enums\ContestMetric;
    use Illuminate\Support\Carbon;

    $stats = $report['stats'];
    $boards = $report['boards'];
    $flagged = $report['flagged'];

    // Médailles : or, argent, bronze — le même repère que sur le site des clippeurs.
    $medals = [1 => ['#F5C542', '#4a3500'], 2 => ['#CBD5E1', '#1e293b'], 3 => ['#CD7F32', '#ffffff']];
    $card = 'border:1px solid rgba(128,128,128,.25);background:rgba(128,128,128,.07);border-radius:.85rem;';
    $muted = 'color:rgb(var(--gray-500));';
@endphp

{{--
    Le classement, lu par l'équipe : à qui l'on verse quoi, sur combien de clips, et qui a
    essayé de faire compter ce qui ne compte pas. Le site n'a pas de thème propre pour
    l'administration : la mise en page est en styles directs, pour s'afficher à coup sûr.
--}}
<div x-data="{ board: @js($boards[0]['metric']->value) }" style="display:grid;gap:1.25rem;">

    <p class="text-sm text-gray-500 dark:text-gray-400" style="margin:0;">
        @if ($campaign->contestIsOver())
            Classements arrêtés{{ $campaign->ends_at ? ' au '.$campaign->ends_at->format('d/m/Y H:i') : '' }} : seuls les relevés et les clips antérieurs comptent.
        @else
            Classements en direct, sur les clips validés et publiés pendant la campagne.
        @endif
        @if ($campaign->prizes_paid_at)
            Récompenses attribuées le {{ $campaign->prizes_paid_at->format('d/m/Y') }}.
        @endif
    </p>

    {{-- Les chiffres de la campagne --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;">
        @foreach ([
            ['Participants', number_format($stats['participants'], 0, ',', ' '), null],
            ['Clips comptés', number_format($stats['clips'], 0, ',', ' '), null],
            ['Vues comptées', number_format($stats['views'], 0, ',', ' '), null],
            ['Récompenses', $stats['prize_pool_cents'] > 0 ? number_format($stats['prize_pool_cents'] / 100, 0, ',', ' ').' €' : 'Lots', null],
            ['Clips écartés', number_format($stats['refused'], 0, ',', ' '), $stats['refused'] > 0 ? 'danger' : null],
        ] as [$label, $value, $tone])
            <div style="{{ $card }}padding:.85rem 1rem;{{ $tone === 'danger' ? 'border-color:rgb(var(--danger-500));background:rgba(var(--danger-500),.1);' : '' }}">
                <div style="font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;{{ $tone === 'danger' ? 'color:rgb(var(--danger-500));' : $muted }}">{{ $label }}</div>
                <div style="font-size:1.55rem;font-weight:700;line-height:1.2;margin-top:.15rem;font-variant-numeric:tabular-nums;">{{ $value }}</div>
                @if ($label === 'Clips écartés')
                    <div style="font-size:.72rem;{{ $muted }}">publiés hors campagne</div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Les signalés : ceux dont des clips ont été refusés parce qu'ils n'ont pas été faits pendant la campagne --}}
    @if ($flagged !== [])
        <div style="border:1px solid rgb(var(--danger-500));background:rgba(var(--danger-500),.08);border-radius:.85rem;padding:.9rem 1rem;">
            <div style="font-weight:700;color:rgb(var(--danger-500));">
                ⚠ {{ count($flagged) }} clippeur{{ count($flagged) > 1 ? 's' : '' }} signalé{{ count($flagged) > 1 ? 's' : '' }}
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400" style="margin:.2rem 0 .6rem;">
                Des vidéos publiées avant le début de la campagne ont été soumises, puis refusées.
            </p>
            <ul style="list-style:none;margin:0;padding:0;display:grid;gap:.4rem;">
                @foreach ($flagged as $person)
                    <li style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;">
                        <span>
                            @if ($person->url)
                                <a href="{{ $person->url }}" style="font-weight:600;text-decoration:underline;">{{ $person->name }}</a>
                            @else
                                <span style="font-weight:600;">{{ $person->name }}</span>
                            @endif
                            @if ($person->pseudo)<span style="{{ $muted }}"> · {{ $person->pseudo }}</span>@endif
                            @if ($person->banned)<span style="color:rgb(var(--danger-500));font-weight:600;"> · banni</span>@endif
                        </span>
                        <span style="font-size:.85rem;{{ $muted }}">
                            {{ $person->count }} clip{{ $person->count > 1 ? 's' : '' }} refusé{{ $person->count > 1 ? 's' : '' }}
                            · {{ Carbon::parse($person->last_at)->format('d/m H:i') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Un onglet par classement --}}
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        @foreach ($boards as $b)
            <button type="button" x-on:click="board = @js($b['metric']->value)"
                    :style="board === @js($b['metric']->value)
                        ? 'background:rgb(var(--primary-600));color:#fff;border-color:rgb(var(--primary-600));'
                        : 'background:transparent;border-color:rgba(128,128,128,.35);'"
                    style="padding:.45rem 1rem;border-radius:999px;border:1px solid;font-size:.875rem;font-weight:600;cursor:pointer;">
                {{ $b['metric']->title() }}
            </button>
        @endforeach
    </div>

    @foreach ($boards as $b)
        @php $metric = $b['metric']; @endphp

        <div x-show="board === @js($metric->value)" @unless ($loop->first) x-cloak @endunless style="display:grid;gap:1rem;">
            <p class="text-sm text-gray-500 dark:text-gray-400" style="margin:0;">
                Classés sur {{ $metric === ContestMetric::Views ? 'toutes les vues de leurs clips' : 'le nombre de clips' }}
                @if ($metric === ContestMetric::Posts && $campaign->postMinViews() > 0)
                    ayant atteint {{ number_format($campaign->postMinViews(), 0, ',', ' ') }} vues
                @endif.
            </p>

            @if ($b['podium'] === [])
                <p class="text-sm text-gray-500 dark:text-gray-400" style="padding:1rem 0;">Personne n'est encore classé.</p>
            @else
                {{-- Le podium --}}
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:.75rem;align-items:end;">
                    @foreach ($b['podium'] as $row)
                        @php [$bg, $fg] = $medals[$row->rank]; @endphp
                        <div style="{{ $card }}padding:1rem;{{ $row->rank === 1 ? 'border-color:#F5C542;box-shadow:0 0 0 1px #F5C542 inset;padding-block:1.35rem;' : '' }}">
                            <div style="display:flex;align-items:center;gap:.6rem;">
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:2.1rem;height:2.1rem;border-radius:999px;background:{{ $bg }};color:{{ $fg }};font-weight:800;">{{ $row->rank }}</span>
                                <div style="min-width:0;">
                                    @if ($row->url)
                                        <a href="{{ $row->url }}" style="font-weight:700;text-decoration:underline;word-break:break-word;">{{ $row->name }}</a>
                                    @else
                                        <span style="font-weight:700;word-break:break-word;">{{ $row->name }}</span>
                                    @endif
                                    @if ($row->pseudo)<div style="font-size:.75rem;{{ $muted }}">{{ $row->pseudo }}</div>@endif
                                </div>
                            </div>
                            <div style="font-size:1.45rem;font-weight:800;margin-top:.7rem;font-variant-numeric:tabular-nums;">{{ $metric->formatScore($row->score) }}</div>
                            <div style="font-size:.78rem;{{ $muted }}">
                                {{ number_format($row->views, 0, ',', ' ') }} vues · {{ $row->clips }} clip{{ $row->clips > 1 ? 's' : '' }}
                            </div>
                            <div style="margin-top:.65rem;display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;">
                                @if ($row->reward)
                                    <span style="padding:.2rem .6rem;border-radius:999px;background:rgba(var(--primary-500),.15);color:rgb(var(--primary-600));font-size:.8rem;font-weight:700;">{{ $row->reward }}</span>
                                @endif
                                @if ($row->flagged > 0)
                                    <span style="padding:.2rem .6rem;border-radius:999px;background:rgba(var(--danger-500),.15);color:rgb(var(--danger-500));font-size:.75rem;font-weight:700;">
                                        ⚠ {{ $row->flagged }} refusé{{ $row->flagged > 1 ? 's' : '' }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- La suite du classement --}}
                @if ($b['rows'] !== [])
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                            <thead>
                                <tr style="text-align:left;{{ $muted }}font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;">
                                    <th style="padding:.4rem .6rem .4rem 0;">#</th>
                                    <th style="padding:.4rem .6rem;">Clippeur</th>
                                    <th style="padding:.4rem .6rem;min-width:140px;"></th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Clips</th>
                                    <th style="padding:.4rem .6rem;text-align:right;">Vues</th>
                                    <th style="padding:.4rem 0 .4rem .6rem;text-align:right;">Récompense</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($b['rows'] as $row)
                                    <tr style="border-top:1px solid rgba(128,128,128,.18);">
                                        <td style="padding:.55rem .6rem .55rem 0;font-weight:700;font-variant-numeric:tabular-nums;">{{ $row->rank }}</td>
                                        <td style="padding:.55rem .6rem;">
                                            @if ($row->url)
                                                <a href="{{ $row->url }}" style="font-weight:600;text-decoration:underline;">{{ $row->name }}</a>
                                            @else
                                                <span style="font-weight:600;">{{ $row->name }}</span>
                                            @endif
                                            @if ($row->pseudo)<span style="{{ $muted }}"> · {{ $row->pseudo }}</span>@endif
                                            @if ($row->flagged > 0)
                                                <span style="margin-left:.35rem;padding:.1rem .45rem;border-radius:999px;background:rgba(var(--danger-500),.15);color:rgb(var(--danger-500));font-size:.7rem;font-weight:700;">⚠ {{ $row->flagged }}</span>
                                            @endif
                                        </td>
                                        <td style="padding:.55rem .6rem;">
                                            <div style="height:.45rem;border-radius:999px;background:rgba(128,128,128,.2);overflow:hidden;">
                                                <div style="height:100%;border-radius:999px;background:rgb(var(--primary-500));width:{{ max(2, (int) round($row->score / $b['leader'] * 100)) }}%;"></div>
                                            </div>
                                        </td>
                                        <td style="padding:.55rem .6rem;text-align:right;font-variant-numeric:tabular-nums;{{ $metric === ContestMetric::Posts ? 'font-weight:700;' : '' }}">{{ $row->clips }}</td>
                                        <td style="padding:.55rem .6rem;text-align:right;font-variant-numeric:tabular-nums;{{ $metric === ContestMetric::Views ? 'font-weight:700;' : '' }}">{{ number_format($row->views, 0, ',', ' ') }}</td>
                                        <td style="padding:.55rem 0 .55rem .6rem;text-align:right;">{{ $row->reward ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    @endforeach
</div>
