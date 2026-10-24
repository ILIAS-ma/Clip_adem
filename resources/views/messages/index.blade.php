<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-ink-50">{{ auth()->user()->isCreator() ? 'Support' : 'Messages' }}</h1>
        <p class="mt-1 text-sm text-ink-300">
            Votre fil avec l'équipe {{ config('app.name') }}. Les messages sont effacés trois mois après leur envoi.
        </p>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 py-5 sm:px-6 sm:py-8 lg:px-8">
        <livewire:messages />
    </div>
</x-app-layout>
