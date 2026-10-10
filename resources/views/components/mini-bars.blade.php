@props(['series', 'label' => null, 'unit' => ''])

@php
    // Un graphique en barres sans dépendance : une bibliothèque de courbes
    // pour trente valeurs coûterait plus de kilo-octets que la page entière.
    $values = collect($series);
    $max = max(1, $values->max());
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if ($label)
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $label }}</p>
    @endif

    <div class="mt-3 flex h-24 items-end gap-[3px]">
        @foreach ($values as $day => $value)
            <div class="group relative flex-1">
                <div class="rounded-t bg-brand-500/70 transition group-hover:bg-brand-400"
                     style="height: {{ max(2, (int) round($value / $max * 96)) }}px"
                     role="presentation"></div>

                {{-- Infobulle en CSS : survoler une barre doit dire quel jour
                     et combien, sinon le graphique n'est qu'une décoration. --}}
                <span class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2
                             whitespace-nowrap rounded bg-ink-800 px-2 py-1 text-xs text-ink-100 shadow-lg
                             group-hover:block">
                    {{ \Illuminate\Support\Carbon::parse($day)->format('d/m') }} ·
                    <span class="tabular">{{ number_format($value, 0, ',', ' ') }}{{ $unit }}</span>
                </span>
            </div>
        @endforeach
    </div>

    <div class="mt-2 flex justify-between text-xs text-ink-500">
        <span>{{ \Illuminate\Support\Carbon::parse($values->keys()->first())->format('d/m') }}</span>
        <span>aujourd'hui</span>
    </div>
</div>
