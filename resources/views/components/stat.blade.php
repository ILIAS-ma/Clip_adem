@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'neutral',   // neutral | money | brand
    'highlight' => false,
])

@php
    /*
     * La couleur du chiffre porte son sens.
     *
     * Sur la charte sombre, le lime disait à la fois « marque » et « argent
     * acquis », et les deux tons pointaient donc la même couleur. Depuis que
     * la marque est rouge, les confondre afficherait les gains en rouge — la
     * couleur que tout le monde lit comme une perte.
     */
    $valueTone = match ($tone) {
        'money' => 'text-money-600',
        'brand' => 'text-brand-600',
        default => 'text-ink-950',
    };
@endphp

{{--
    `data-count-up` fait défiler le chiffre jusqu'à sa valeur. Le montant final
    reste écrit dans le HTML : sans JavaScript, la carte affiche simplement le
    bon nombre, et un lecteur d'écran n'entend jamais un total en train de
    bouger.
--}}
<div {{ $attributes->merge(['class' => 'stat-card card p-5 '.($highlight ? 'ring-2 ring-brand-500' : '')]) }}>
    <p class="text-sm font-medium text-ink-700">{{ $label }}</p>
    <p class="mt-1.5 font-display text-2xl font-bold tabular {{ $valueTone }}" data-count-up>{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-ink-700">{{ $hint }}</p>
    @endif
</div>
