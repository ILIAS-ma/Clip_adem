@props(['values', 'labels', 'unit' => ' vues'])

@php
    use App\Support\Money;
    use Illuminate\Support\Carbon;

    /*
     * Une courbe en aire, sans bibliothèque : du SVG qui s'étire à la largeur de
     * sa carte, donc aussi lisible sur un téléphone que sur un grand écran.
     * L'infobulle suit le doigt ou la souris et dit quel jour, et combien.
     */
    $values = array_values($values);
    $labels = array_values($labels);
    $count = count($values);
    $max = max(1, $count ? max($values) : 1);
    $width = 600;
    $height = 200;

    $points = [];
    foreach ($values as $i => $value) {
        $x = $count > 1 ? $i / ($count - 1) * $width : $width / 2;
        $y = $height - ($value / $max) * ($height - 6);
        $points[] = round($x, 1).','.round($y, 1);
    }

    $line = $points ? 'M'.implode(' L', $points) : '';
    $area = $points ? $line.' L'.$width.','.$height.' L0,'.$height.' Z' : '';
    $shown = array_map(fn ($day) => Carbon::parse($day)->format('d/m'), $labels);
    $gradient = 'area-'.substr(md5(json_encode($values)), 0, 8);
@endphp

@if ($count < 2)
    <p class="rounded-xl bg-ink-900 p-4 text-sm text-ink-300">Pas encore assez de relevés pour tracer une courbe.</p>
@else
    <div {{ $attributes->merge(['class' => 'select-none']) }}
         x-data="{
             i: null,
             n: {{ $count }},
             max: {{ $max }},
             values: @js($values),
             days: @js($shown),
             unit: @js($unit),
             move(event) {
                 const box = this.$refs.plot.getBoundingClientRect();
                 const x = (event.touches ? event.touches[0].clientX : event.clientX) - box.left;
                 this.i = Math.max(0, Math.min(this.n - 1, Math.round(x / box.width * (this.n - 1))));
             },
             fmt(v) { return new Intl.NumberFormat('fr-FR').format(v); },
         }">
        <div class="flex gap-3">
            {{-- Les repères de l'axe vertical --}}
            <div class="flex h-44 flex-none flex-col justify-between text-right text-[10px] tabular text-ink-500 sm:h-56" aria-hidden="true">
                <span>{{ Money::compactViews($max) }}</span>
                <span>{{ Money::compactViews((int) ($max / 2)) }}</span>
                <span>0</span>
            </div>

            <div class="relative h-44 min-w-0 flex-1 sm:h-56" x-ref="plot"
                 @mousemove="move($event)" @mouseleave="i = null"
                 @touchstart.passive="move($event)" @touchmove.passive="move($event)" @touchend="i = null">
                <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" class="absolute inset-0 h-full w-full overflow-visible" role="img"
                     aria-label="Évolution : de {{ number_format($values[0], 0, ',', ' ') }} à {{ number_format($values[$count - 1], 0, ',', ' ') }}{{ $unit }}">
                    <defs>
                        <linearGradient id="{{ $gradient }}" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#CC3F45" stop-opacity="0.45" />
                            <stop offset="100%" stop-color="#CC3F45" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    @foreach ([0, 0.5, 1] as $row)
                        <line x1="0" x2="{{ $width }}" y1="{{ $height - $row * ($height - 6) }}" y2="{{ $height - $row * ($height - 6) }}"
                              stroke="currentColor" class="text-ink-700" stroke-width="1" stroke-dasharray="3 5" vector-effect="non-scaling-stroke" />
                    @endforeach
                    <path d="{{ $area }}" fill="url(#{{ $gradient }})" />
                    <path d="{{ $line }}" fill="none" stroke="#E0575D" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                </svg>

                {{-- Repère et infobulle, positionnés en pourcentage de la zone --}}
                <template x-if="i !== null">
                    <div class="pointer-events-none absolute inset-0">
                        <div class="absolute inset-y-0 w-px bg-ink-400/50" :style="`left: ${n > 1 ? i / (n - 1) * 100 : 50}%`"></div>
                        <div class="absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-ink-950 bg-brand-400"
                             :style="`left: ${n > 1 ? i / (n - 1) * 100 : 50}%; top: ${(1 - values[i] / max) * 97}%`"></div>
                        <div class="absolute top-0 z-10 -translate-y-full whitespace-nowrap rounded-lg bg-ink-800 px-2.5 py-1.5 text-xs text-ink-100 shadow-lg ring-1 ring-ink-700"
                             :style="`left: ${Math.min(Math.max(n > 1 ? i / (n - 1) * 100 : 50, 14), 86)}%; transform: translate(-50%, -4px)`">
                            <span class="text-ink-400" x-text="days[i]"></span>
                            <span class="ms-1 font-semibold tabular" x-text="fmt(values[i]) + unit"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="mt-2 flex justify-between ps-9 text-[11px] text-ink-500" aria-hidden="true">
            <span>{{ $shown[0] }}</span>
            <span class="hidden sm:inline">{{ $shown[intdiv($count, 2)] }}</span>
            <span>{{ $shown[$count - 1] }}</span>
        </div>
    </div>
@endif
