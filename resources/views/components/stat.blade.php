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

<div {{ $attributes->merge(['class' => 'card p-5 '.($highlight ? 'ring-2 ring-brand-500' : '')]) }}>
    <p class="text-sm font-medium text-ink-400">{{ $label }}</p>
    <p class="mt-1.5 font-display text-2xl font-bold tabular {{ $valueTone }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-ink-400">{{ $hint }}</p>
    @endif
</div>
