<section>
    <header>
        <h2 class="font-display text-lg font-bold text-ink-50">Mot de passe</h2>
        <p class="mt-1 text-sm text-ink-300">
            Choisissez un mot de passe long, que vous n'utilisez nulle part ailleurs.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" value="Mot de passe actuel" />
            <x-text-input id="update_password_current_password" name="current_password" type="password"
                          class="mt-1.5" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" value="Nouveau mot de passe" />
            <x-text-input id="update_password_password" name="password" type="password"
                          class="mt-1.5" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" value="Confirmer" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation"
                          type="password" class="mt-1.5" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4 border-t border-ink-700 pt-5">
            <x-primary-button>Changer le mot de passe</x-primary-button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition
                   x-init="setTimeout(() => show = false, 2500)"
                   class="text-sm font-medium text-brand-400">Modifié.</p>
            @endif
        </div>
    </form>
</section>
