{{-- Les lignes de prix d'un clippeur : campagne, classement, place, prix et état. --}}
    <ul class="divide-y divide-ink-700">
        @foreach ($prizes['rows'] as $row)
            <li class="flex items-center justify-between gap-4 px-6 py-3.5">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-ink-100">{{ $row['campaign'] }}</p>
                    <p class="mt-0.5 text-xs text-ink-400">{{ $row['board'] }} · {{ $row['rank'] === 1 ? '1re' : $row['rank'].'e' }} place</p>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span class="font-display text-base font-bold tabular text-ink-50">{{ $row['reward'] }}</span>
                    <span @class([
                        'chip-ok' => $row['state'] === 'awarded',
                        'chip-wait' => $row['state'] === 'pending',
                        'chip-neutral' => $row['state'] === 'live',
                    ])>{{ match ($row['state']) {
                        'awarded' => 'Versé',
                        'pending' => 'À verser',
                        default => 'Provisoire',
                    } }}</span>
                </div>
            </li>
        @endforeach
    </ul>
