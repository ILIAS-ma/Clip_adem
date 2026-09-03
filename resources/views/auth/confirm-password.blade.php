<x-guest-layout>
    <div class="mb-8">
        <h2 class="font-display text-3xl font-bold text-ink-900">Confirmez votre identité</h2>
        <p class="mt-2 text-ink-500">Cette action est sensible : ressaisissez votre mot de passe.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="password" value="Mot de passe" />
            <x-text-input id="password" class="mt-1.5" type="password" name="password"
                          required autocomplete="current-password" autofocus />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">Confirmer</x-primary-button>
    </form>
</x-guest-layout>
