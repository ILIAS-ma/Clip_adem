@props(['user'])

@php
    // Seul un clippeur a un niveau : un créateur n'a pas d'XP, l'anneau
    // resterait un signal qui ne veut rien dire pour lui.
    $ringClasses = $user->isClipper()
        ? app(\App\Services\Clippers\ClipperProgressionService::class)->for($user)->level->avatarRingClasses()
        : '';
@endphp

@if ($user->avatar_url)
    <img src="{{ $user->avatar_url }}" alt=""
         {{ $attributes->merge(['class' => "h-7 w-7 shrink-0 rounded-full object-cover ring-offset-2 ring-offset-ink-900 $ringClasses"]) }}>
@else
    <span {{ $attributes->merge(['class' => "flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-500 text-xs font-bold text-ink-950 ring-offset-2 ring-offset-ink-900 $ringClasses"]) }}>
        {{ mb_strtoupper(mb_substr($user->displayName(), 0, 1)) }}
    </span>
@endif
