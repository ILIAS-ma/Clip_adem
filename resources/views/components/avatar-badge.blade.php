@props(['user'])

@php
    // Seul un clippeur a un niveau : un créateur n'a pas d'XP, le cadre
    // resterait un signal qui ne veut rien dire pour lui.
    $frame = $user->isClipper()
        ? app(\App\Services\Clippers\ClipperProgressionService::class)->for($user)->level->avatarFrameImage()
        : null;
@endphp

{{--
    Le cadre déborde largement de l'image source (fer, bronze, argent, or,
    lumière) : la photo doit rester nettement plus petite que la boîte pour
    que l'anneau ait la place de s'afficher tout autour, pas juste en
    bordure. Le pourcentage vient de l'image fournie, pas d'un calcul.
--}}
<span {{ $attributes->merge(['class' => 'relative inline-flex h-7 w-7 shrink-0 text-xs']) }}>
    @if ($user->avatar_url)
        <img src="{{ $user->avatar_url }}" alt=""
             class="absolute inset-[18%] h-[64%] w-[64%] rounded-full object-cover">
    @else
        <span class="absolute inset-[18%] flex h-[64%] w-[64%] items-center justify-center rounded-full bg-brand-500 text-[1em] font-bold text-ink-950">
            {{ mb_strtoupper(mb_substr($user->displayName(), 0, 1)) }}
        </span>
    @endif

    @if ($frame)
        <img src="{{ asset($frame) }}" alt="" aria-hidden="true"
             class="pointer-events-none absolute inset-0 h-full w-full">
    @endif
</span>
