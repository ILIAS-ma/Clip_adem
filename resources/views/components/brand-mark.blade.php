@props(['showName' => null])

@php
    $logo = is_file(public_path('images/logo.png'));

    // Le logo officiel porte déjà son mot-symbole : répéter le nom à côté
    // ferait doublon. Sans logo, le nom devient le seul repère et s'affiche.
    $withName = $showName ?? ! $logo;
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    @if ($logo)
        <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}"
             class="h-9 w-9 shrink-0" width="36" height="36" loading="eager" decoding="async">
    @else
        {{-- Onde sonore stylisée : le sujet du produit, pas un logo générique. --}}
        <svg viewBox="0 0 28 20" class="h-5 w-7 shrink-0" fill="none" aria-hidden="true">
            @foreach ([[2, 7], [7, 13], [12, 19], [17, 11], [22, 5]] as $i => [$x, $h])
                <rect x="{{ $x }}" y="{{ (20 - $h) / 2 }}" width="3" height="{{ $h }}" rx="1.5"
                      class="{{ $i === 2 ? 'fill-brand-500' : 'fill-ink-200' }}" />
            @endforeach
        </svg>
    @endif

    @if ($withName)
        <span class="font-display text-lg font-bold tracking-tight text-ink-50">
            {{ config('app.name') }}
        </span>
    @endif
</span>
