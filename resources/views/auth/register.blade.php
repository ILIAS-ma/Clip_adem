<x-guest-layout>
    <div class="mb-8">
        <h2 class="font-display text-3xl font-bold text-ink-900">Créer un compte</h2>
        <p class="mt-2 text-ink-500">Gratuit. Vous choisissez vos campagnes, vous gardez vos comptes.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="name" value="Nom et prénom" />
            <x-text-input id="name" class="mt-1.5" type="text" name="name"
                          :value="old('name')" required autofocus autocomplete="name" />
            {{-- Le nom réel sert aux versements ; le pseudo public est demandé
                 juste après, pour éviter d'avoir à s'exposer pour être payé. --}}
            <p class="hint">Utilisé pour vos versements. Votre pseudo public sera choisi à l'étape suivante.</p>
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input id="email" class="mt-1.5" type="email" name="email"
                          :value="old('email')" required autocomplete="username" />
            <p class="hint">Un lien de confirmation y sera envoyé.</p>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Mot de passe" />
            <x-text-input id="password" class="mt-1.5" type="password" name="password"
                          required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirmer le mot de passe" />
            <x-text-input id="password_confirmation" class="mt-1.5" type="password"
                          name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">Créer mon compte</x-primary-button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-500">
        Déjà inscrit ?
        <a href="{{ route('login') }}" class="font-semibold text-ink-900 underline underline-offset-2">
            Se connecter
        </a>
    </p>
</x-guest-layout>
