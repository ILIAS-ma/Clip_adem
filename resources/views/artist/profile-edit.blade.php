<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-ink-50">Ma fiche artiste</h1>
        <p class="mt-1 text-ink-300">Ce que voient les clippeurs sur vos campagnes.</p>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        @if (session('status'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        @unless ($artist->is_active)
            <div class="alert-warn">
                <p class="font-semibold">Fiche en attente de validation</p>
                <p class="mt-1">Un administrateur doit la valider avant que des campagnes puissent être lancées.</p>
            </div>
        @endunless

        <div class="card p-6 sm:p-8">
            <form method="POST" action="{{ route('artist.profile.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                @include('artist._form', ['artist' => $artist])

                <div class="border-t border-ink-700 pt-6">
                    <x-primary-button>Enregistrer</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
