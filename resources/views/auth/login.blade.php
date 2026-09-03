<x-guest-layout>
    <div class="mb-8">
        <h2 class="font-display text-3xl font-bold text-ink-900">Content de vous revoir</h2>
        <p class="mt-2 text-ink-500">Connectez-vous pour suivre vos clips et vos gains.</p>
    </div>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input id="email" class="mt-1.5" type="email" name="email"
                          :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-baseline justify-between">
                <x-input-label for="password" value="Mot de passe" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                       class="text-sm font-medium text-ink-400 underline-offset-2 hover:text-ink-800 hover:underline">
                        Oublié ?
                    </a>
                @endif
            </div>
            <x-text-input id="password" class="mt-1.5" type="password" name="password"
                          required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex items-center gap-2.5">
            <input id="remember_me" type="checkbox" name="remember"
                   class="rounded border-ink-200 text-ink-800 focus:ring-ink-800">
            <span class="text-sm text-ink-500">Rester connecté</span>
        </label>

        <x-primary-button class="w-full">Se connecter</x-primary-button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-500">
        Pas encore de compte ?
        <a href="{{ route('register') }}" class="font-semibold text-ink-900 underline underline-offset-2">
            Devenir clippeur
        </a>
    </p>
</x-guest-layout>
