<x-guest-layout>
    <div class="mb-8">
        <h2 class="font-display text-3xl font-bold text-ink-50">Mot de passe oublié</h2>
        <p class="mt-2 text-ink-300">
            Indiquez votre adresse : nous vous enverrons un lien pour en choisir un nouveau.
        </p>
    </div>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input id="email" class="mt-1.5" type="email" name="email"
                          :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">Envoyer le lien</x-primary-button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-300">
        <a href="{{ route('login') }}" class="font-semibold text-ink-50 underline underline-offset-2">
            Retour à la connexion
        </a>
    </p>
</x-guest-layout>
