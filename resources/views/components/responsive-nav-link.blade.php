@props([
    'active' => false,
    // La déconnexion passe par un <form> et un onclick : la navigation
    // instantanée n'a rien à y faire, et intercepter ce clic empêcherait la
    // soumission. D'où l'échappatoire explicite.
    'navigate' => true,
])

@php
    $classes = $active
        ? 'block w-full ps-4 pe-4 py-2.5 border-s-4 border-brand-500 text-start text-base font-semibold text-ink-50 bg-ink-800 transition'
        : 'block w-full ps-4 pe-4 py-2.5 border-s-4 border-transparent text-start text-base font-medium text-ink-300 hover:bg-ink-800 hover:text-ink-50 transition';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if ($navigate) wire:navigate @endif>{{ $slot }}</a>
