{{-- Les lignes de prix d'un clippeur : campagne, classement, place, prix et état. --}}
    <ul class="divide-y divide-ink-700">
        @foreach ($prizes['rows'] as $row)
            <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1.5 px-4 py-3 sm:flex-nowrap sm:px-6 sm:py-3.5">
                <div class="w-full min-w-0 sm:w-auto">
                    <p class="text-sm font-semibold text-ink-100 sm:truncate">{{ $row['campaign'] }}</p>
                    <p class="mt-0.5 text-xs text-ink-400">{{ $row['board'] }} · {{ $row['rank'] === 1 ? '1re' : $row['rank'].'e' }} place</p>
                </div>
                <div class="flex w-full shrink-0 items-center justify-between gap-3 sm:w-auto sm:justify-end">
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
