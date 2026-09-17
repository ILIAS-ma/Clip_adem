@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'neutral',   // neutral | money | brand
    'highlight' => false,
])

@php
    // La couleur du chiffre porte son sens : vert = acquis, ambre = en attente.
    $valueTone = match ($tone) {
        'money' => 'text-brand-400',
        'brand' => 'text-brand-400',
        default => 'text-ink-50',
    };
@endphp

{{--
    `data-count-up` fait défiler le chiffre jusqu'à sa valeur. Le montant final
    reste écrit dans le HTML : sans JavaScript, la carte affiche simplement le
    bon nombre, et un lecteur d'écran n'entend jamais un total en train de
    bouger.
--}}
<div {{ $attributes->merge(['class' => 'stat-card card p-5 '.($highlight ? 'ring-2 ring-brand-500' : '')]) }}>
    <p class="text-sm font-medium text-ink-300">{{ $label }}</p>
    <p class="mt-1.5 font-display text-2xl font-bold tabular {{ $valueTone }}" data-count-up>{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-ink-300">{{ $hint }}</p>
    @endif
</div>
