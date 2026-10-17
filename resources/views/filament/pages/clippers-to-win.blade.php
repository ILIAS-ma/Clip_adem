<x-filament-panels::page>
    @php
        $signals = $this->signals();
        $counts = collect(\App\Support\Clippers\RetentionSignal::levels())
            ->map(fn ($label, $level) => ['label' => $label, 'count' => $signals->where('level', $level)->count()]);
    @endphp

    {{-- Les trois compteurs d'abord : on sait en un coup d'œil s'il y a
         urgence, avant de lire la moindre ligne. --}}
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($counts as $level => $item)
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $item['count'] }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @switch($level)
                        @case(\App\Support\Clippers\RetentionSignal::AT_RISK) Postent encore, ne gagnent rien. @break
                        @case(\App\Support\Clippers\RetentionSignal::DROPPING) Gagnaient, gagnent nettement moins. @break
                        @default Déjà payés, ne postent plus.
                    @endswitch
                </p>
            </div>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
