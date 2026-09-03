<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-ink-900">Campagnes</h1>
        <p class="mt-1 text-ink-500">Choisissez une campagne, publiez, soyez payé aux 1000 vues.</p>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @livewire('campaign-catalogue')
    </div>
</x-app-layout>
