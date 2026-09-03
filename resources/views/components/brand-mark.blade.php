@props(['tone' => 'light'])

@php
    // Le logo fourni prend le relais dès qu'il est déposé dans public/images ;
    // en son absence, la marque reste lisible grâce à l'onde de repli.
    $logo = public_path('images/logo.png');
    $hasLogo = is_file($logo);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    @if ($hasLogo)
        <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}"
             class="h-9 w-9 shrink-0 rounded-full" width="36" height="36">
    @else
        {{-- Onde sonore stylisée : le sujet du produit, pas un logo générique. --}}
        <svg viewBox="0 0 28 20" class="h-5 w-7 shrink-0" fill="none" aria-hidden="true">
            @foreach ([[2, 7], [7, 13], [12, 19], [17, 11], [22, 5]] as $i => [$x, $h])
                <rect x="{{ $x }}" y="{{ (20 - $h) / 2 }}" width="3" height="{{ $h }}" rx="1.5"
                      class="{{ $i === 2 ? 'fill-brand-500' : 'fill-ink-200' }}" />
            @endforeach
        </svg>
    @endif

    <span class="font-display text-lg font-bold tracking-tight text-ink-50">
        {{ config('app.name') }}
    </span>
</span>
