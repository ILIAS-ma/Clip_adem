<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-400">Leçon {{ $number }} / {{ $lessons->count() }}</p>
                <h1 class="mt-1 font-display text-2xl font-bold text-ink-50">{{ $lesson->title }}</h1>
                @if ($lesson->summary)
                    <p class="mt-1 text-ink-300">{{ $lesson->summary }}</p>
                @endif
            </div>

            <a href="{{ route('training.index') }}" wire:navigate class="btn-ghost">← Toutes les leçons</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-4 px-4 py-5 sm:space-y-6 sm:px-6 sm:py-8 lg:px-8">
        @if (session('status'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        @if ($player = $lesson->playerUrl())
            {{--
                Un geste : on touche, la vidéo s'ouvre en plein écran, on regarde.

                L'iframe n'existe qu'ouverte : la fermer coupe le son d'un coup, et
                son chargement part du toucher, ce qui laisse le navigateur lancer
                la voix off. `sandbox` sans allow-same-origin : la page tourne
                isolée du site, elle ne peut rien lire de la session.
            --}}
            <div x-data="{ open: false }" @keydown.escape.window="open = false"
                 x-effect="document.body.classList.toggle('overflow-hidden', open)">
                <button type="button" @click="open = true"
                        class="group relative flex aspect-video w-full items-center justify-center overflow-hidden rounded-3xl border border-ink-700 bg-ink-900 text-left transition hover:border-brand-500/60 sm:aspect-[21/9]">
                    <span aria-hidden="true" class="absolute -left-16 -top-20 h-72 w-72 rounded-full bg-brand-500/25 blur-3xl"></span>
                    <span class="relative flex flex-col items-center gap-3 px-6 text-center">
                        <span class="flex h-20 w-20 items-center justify-center rounded-full bg-brand-500 text-white shadow-glow transition group-hover:scale-105">
                            <svg class="ms-1 h-9 w-9" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 001.5.86l11-6.5a1 1 0 000-1.72l-11-6.5A1 1 0 008 5.5z" /></svg>
                        </span>
                        <span class="font-display text-xl font-bold text-ink-50">Regarder la vidéo</span>
                        <span class="text-sm text-ink-300">54 secondes · avec le son</span>
                    </span>
                </button>

                {{-- Téléporté dans <body> : un parent animé ou flouté piégerait la position fixe
                     sous la barre du haut, comme le tiroir du menu. --}}
                <template x-teleport="body">
                    <div x-show="open" x-cloak class="fixed inset-0 z-[70] bg-black" role="dialog" aria-modal="true" aria-label="{{ $lesson->title }}">
                        <template x-if="open">
                            <iframe class="h-full w-full" src="{{ $player }}" title="{{ $lesson->title }}"
                                    sandbox="allow-scripts" allow="autoplay; fullscreen"></iframe>
                        </template>
                        <button type="button" @click="open = false" aria-label="Fermer"
                                class="absolute right-3 flex h-11 w-11 items-center justify-center rounded-full bg-ink-950/80 text-ink-50 ring-1 ring-white/20 backdrop-blur transition hover:bg-ink-800"
                                style="top: max(0.75rem, env(safe-area-inset-top));">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                        </button>
                    </div>
                </template>
            </div>
        @elseif ($file = $lesson->videoFileUrl())
            <div class="card overflow-hidden">
                <video class="aspect-video w-full bg-ink-950" controls controlsList="nodownload" disablePictureInPicture preload="metadata" playsinline oncontextmenu="return false">
                    <source src="{{ $file }}" type="{{ str_ends_with($file, '.webm') ? 'video/webm' : 'video/mp4' }}">
                    Ton navigateur ne lit pas cette vidéo.
                </video>
            </div>
        @elseif ($embed = $lesson->embedUrl())
            <div class="card overflow-hidden">
                <iframe class="aspect-video w-full" src="{{ $embed }}" title="{{ $lesson->title }}"
                        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                        allowfullscreen loading="lazy"></iframe>
            </div>
        @endif

        @if (filled($lesson->body))
            <article class="card lesson p-6 sm:p-8">
                {{ $lesson->bodyHtml() }}
            </article>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            @if ($previous)
                <a href="{{ route('training.show', $previous) }}" wire:navigate class="btn-ghost">← {{ $previous->title }}</a>
            @else
                <span></span>
            @endif

            @if (in_array($lesson->id, $completed, true))
                <div class="flex items-center gap-3">
                    <span class="chip-ok">Leçon terminée</span>
                    @if ($following)
                        <a href="{{ route('training.show', $following) }}" wire:navigate class="btn-primary">{{ $following->title }} →</a>
                    @endif
                </div>
            @else
                <form method="POST" action="{{ route('training.complete', $lesson) }}">
                    @csrf
                    <button type="submit" class="btn-primary">
                        {{ $following ? 'J’ai compris, leçon suivante →' : 'Terminer la formation' }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
