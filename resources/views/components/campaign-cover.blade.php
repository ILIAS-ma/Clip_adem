@props(['url', 'alt' => ''])

{{--
    Couverture de campagne, quel que soit son format.

    L'image est montrée EN ENTIER (object-contain), jamais rognée : une affiche
    carrée ou portrait étirée sur un bandeau paysage perdait le haut et le bas,
    et devenait floue à force d'être agrandie. Les côtés laissés vides sont
    remplis par la même image, floutée et assombrie, plutôt que par du noir.

    La hauteur se règle par la classe passée au composant (h-56, aspect-video…).
--}}
<div {{ $attributes->merge(['class' => 'relative isolate overflow-hidden bg-ink-900']) }}>
    <img src="{{ $url }}" alt="" aria-hidden="true" loading="lazy" decoding="async"
         class="absolute inset-0 -z-10 h-full w-full scale-110 object-cover opacity-60 blur-2xl">
    <div aria-hidden="true" class="absolute inset-0 -z-10 bg-ink-950/40"></div>
    <img src="{{ $url }}" alt="{{ $alt }}" loading="lazy" decoding="async"
         class="mx-auto h-full w-auto max-w-full object-contain">
</div>
