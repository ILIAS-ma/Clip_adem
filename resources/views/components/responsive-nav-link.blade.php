@props([
    'active' => false,
    // La déconnexion passe par un <form> et un onclick : la navigation
    // instantanée n'a rien à y faire, et intercepter ce clic empêcherait la
    // soumission. D'où l'échappatoire explicite.
    'navigate' => true,
])

@php
    $classes = $active
        ? 'block w-full ps-4 pe-4 py-2.5 border-s-4 border-brand-500 text-start text-base font-semibold text-ink-950 bg-ink-200 transition'
        : 'block w-full ps-4 pe-4 py-2.5 border-s-4 border-transparent text-start text-base font-medium text-ink-700 hover:bg-ink-200 hover:text-ink-950 transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($navigate) wire:navigate @endif>{{ $slot }}</a>
