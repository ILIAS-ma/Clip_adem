<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-ink-50">Succès</h1>
        <p class="mt-1 text-ink-300">
            {{ $achievements->where('unlocked', true)->count() }} / {{ $achievements->count() }} débloqués
        </p>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($achievements as $achievement)
                <div @class([
                    'card p-5',
                    'opacity-50' => ! $achievement->unlocked,
                ])>
                    <span @class([
                        'chip',
                        'bg-brand-500/15 text-brand-300' => $achievement->unlocked,
                        'bg-ink-700 text-ink-400' => ! $achievement->unlocked,
                    ])>{{ $achievement->unlocked ? 'Débloqué' : 'À débloquer' }}</span>

                    <h2 class="mt-3 font-display text-base font-bold text-ink-50">{{ $achievement->label }}</h2>
                    <p class="mt-1 text-sm leading-relaxed text-ink-300">{{ $achievement->description }}</p>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
