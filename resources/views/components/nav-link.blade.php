@props(['active'])

@php
    $classes = ($active ?? false)
        ? 'relative inline-flex items-center px-1 pt-1 text-sm font-semibold text-ink-50 after:absolute after:inset-x-0 after:-bottom-px after:h-0.5 after:rounded-full after:bg-brand-500'
        : 'inline-flex items-center px-1 pt-1 text-sm font-medium text-ink-400 transition hover:text-ink-50';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
