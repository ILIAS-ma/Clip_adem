<x-app-layout>
    <x-slot name="header">
        <span class="chip-wait">Étape 2 sur 2</span>
        <h1 class="mt-3 font-display text-2xl font-bold text-ink-900">Créez votre fiche artiste</h1>
        <p class="mt-1 text-ink-500">C'est à elle que seront rattachées vos campagnes et vos statistiques.</p>
    </x-slot>

    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="card p-6 sm:p-8">
            <form method="POST" action="{{ route('artist.profile.store') }}" class="space-y-6">
                @csrf

                @include('artist._form', ['artist' => null])

                <div class="border-t border-ink-100 pt-6">
                    <x-primary-button class="w-full sm:w-auto">Créer ma fiche</x-primary-button>
                    <p class="hint">
                        Un administrateur validera votre fiche avant de lancer des campagnes à votre nom.
                    </p>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
