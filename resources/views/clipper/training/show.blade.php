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

        @if ($file = $lesson->videoFileUrl())
            <div class="card overflow-hidden">
                <video class="aspect-video w-full bg-ink-950" controls preload="metadata" playsinline>
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
