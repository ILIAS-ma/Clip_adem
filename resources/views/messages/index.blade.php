<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-ink-50">Messages</h1>
        <p class="mt-1 text-sm text-ink-300">Votre fil avec l'équipe {{ config('app.name') }}.</p>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <livewire:messages />
    </div>
</x-app-layout>
