@props([
    'size' => 'md',      // sm (bouton) | md | lg
    'tone' => 'brand',   // brand (lime) | dark (sur fond lime) | muted
    'label' => 'Chargement…',
])

@php
    $dimension = match ($size) {
        'sm' => 'h-4 w-4',
        'lg' => 'h-10 w-10',
        default => 'h-5 w-5',
    };

    // Sur un bouton lime, l'anneau doit être sombre : du lime sur du lime ne
    // se voit pas, et le loader passe alors totalement inaperçu.
    $ring = match ($tone) {
        'dark' => 'loader-dark',
        'muted' => 'loader-muted',
        default => 'loader-brand',
    };
@endphp

{{--
    Le libellé n'est pas décoratif : sans lui, un lecteur d'écran n'annonce
    rien pendant l'attente, et la personne ne sait pas que quelque chose se
    passe. `aria-hidden` sur l'anneau évite qu'il soit lu comme du contenu.
--}}
<span {{ $attributes->merge(['class' => 'loader '.$dimension.' '.$ring]) }}
      role="status"
      aria-live="polite">
    <span class="sr-only">{{ $label }}</span>
</span>
