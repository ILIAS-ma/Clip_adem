@props(['tone' => 'ink'])

{{-- Onde sonore stylisée : le sujet du produit, pas un logo générique. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-baseline gap-2']) }}>
    <svg viewBox="0 0 28 20" class="h-5 w-7 shrink-0" fill="none" aria-hidden="true">
        @foreach ([[2,7],[7,13],[12,19],[17,11],[22,5]] as $i => [$x, $h])
            <rect x="{{ $x }}" y="{{ (20 - $h) / 2 }}" width="3" height="{{ $h }}" rx="1.5"
                  class="{{ $i === 2 ? 'fill-brand-500' : ($tone === 'light' ? 'fill-white/70' : 'fill-ink-800') }}" />
        @endforeach
    </svg>
    <span class="font-display text-lg font-bold tracking-tight {{ $tone === 'light' ? 'text-white' : 'text-ink-900' }}">
        {{ config('app.name') }}
    </span>
</span>
