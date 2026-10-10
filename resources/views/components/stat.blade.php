@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'neutral',   // neutral | money | brand
    'highlight' => false,
])

@php
    /*
     * Les chiffres sont noirs, quel que soit leur sens.
     *
     * Sur fond clair, un montant coloré attire l'œil avant qu'on ait lu son
     * étiquette, et quatre cartes de teintes différentes se disputent
     * l'attention. Le noir laisse la hiérarchie à la taille et à la position.
     *
     * Le sens n'est pas perdu : il passe par l'étiquette et par la pastille,
     * qui gardent le vert pour l'acquis et l'ambre pour l'attente.
     */
    $valueTone = 'text-ink-950';

    // Le sens migre sur la précision du bas : un liseré et une teinte
    // discrète, là où le regard arrive après avoir lu le chiffre.
    $hintTone = match ($tone) {
        'money' => 'text-money-700',
        'brand' => 'text-brand-700',
        default => 'text-ink-500',
    };
@endphp

{{--
    `data-count-up` fait défiler le chiffre jusqu'à sa valeur. Le montant final
    reste écrit dans le HTML : sans JavaScript, la carte affiche simplement le
    bon nombre, et un lecteur d'écran n'entend jamais un total en train de
    bouger.
--}}
<div {{ $attributes->merge(['class' => 'stat-card card p-5 '.($highlight ? 'ring-2 ring-brand-500' : '')]) }}>
    <p class="text-sm font-medium text-ink-600">{{ $label }}</p>
    <p class="mt-1.5 font-display text-2xl font-bold tabular {{ $valueTone }}" data-count-up>{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs {{ $hintTone }}">{{ $hint }}</p>
    @endif
</div>
