<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink-50">Formation</h1>
                <p class="mt-1 text-ink-300">Tout ce que tu dois savoir pour être payé dès tes premiers clips.</p>
            </div>

            @if ($lessons->isNotEmpty())
                <p class="text-sm text-ink-300">
                    <span class="font-semibold tabular text-ink-50">{{ count(array_intersect($completed, $lessons->pluck('id')->all())) }} / {{ $lessons->count() }}</span>
                    leçons terminées
                </p>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-4 px-4 py-5 sm:space-y-6 sm:px-6 sm:py-8 lg:px-8">
        @if (session('status'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        @if ($lessons->isEmpty())
            <div class="card px-6 py-12 text-center text-sm text-ink-300">
                Les leçons arrivent bientôt.
            </div>
        @else
            @php
                $done = count(array_intersect($completed, $lessons->pluck('id')->all()));
                $percent = (int) round($done / $lessons->count() * 100);
            @endphp

            <div class="card p-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="h-2 overflow-hidden rounded-full bg-ink-700" role="progressbar"
                             aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progression de la formation">
                            <div class="h-full rounded-full bg-brand-500 transition-all" style="width: {{ $percent }}%"></div>
                        </div>
                        <p class="mt-2 text-sm text-ink-300">
                            @if ($next)
                                Prochaine leçon : <span class="font-medium text-ink-50">{{ $next->title }}</span>
                            @else
                                Formation terminée. Le succès « Formé » est débloqué.
                            @endif
                        </p>
                    </div>

                    @if ($next)
                        <a href="{{ route('training.show', $next) }}" wire:navigate class="btn-primary">
                            {{ $done === 0 ? 'Commencer' : 'Reprendre' }}
                        </a>
                    @endif
                </div>
            </div>

            <ol class="space-y-3">
                @foreach ($lessons as $index => $lesson)
                    @php $isDone = in_array($lesson->id, $completed, true); @endphp
                    <li>
                        <a href="{{ route('training.show', $lesson) }}" wire:navigate
                           class="card flex items-center gap-4 p-5 transition hover:border-ink-600">
                            <span @class([
                                'flex h-10 w-10 flex-none items-center justify-center rounded-full text-sm font-bold tabular',
                                'bg-brand-500 text-ink-950' => $isDone,
                                'bg-ink-700 text-ink-300' => ! $isDone,
                            ])>
                                @if ($isDone)
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    <span class="sr-only">Terminée</span>
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block font-display font-bold text-ink-50">{{ $lesson->title }}</span>
                                @if ($lesson->summary)
                                    <span class="mt-0.5 block text-sm leading-relaxed text-ink-300">{{ $lesson->summary }}</span>
                                @endif
                            </span>

                            <span class="hidden flex-none items-center gap-2 text-xs text-ink-400 sm:flex">
                                @if ($lesson->hasVideo())
                                    <span class="chip-neutral">Vidéo</span>
                                @endif
                                @if ($lesson->duration_minutes)
                                    <span>{{ $lesson->duration_minutes }} min</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</x-app-layout>
