<section>
    <header>
        <h2 class="font-display text-lg font-bold text-ink-900">Informations du compte</h2>
        <p class="mt-1 text-sm text-ink-500">
            Votre nom réel sert aux versements ; votre pseudo est ce que voient les autres.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" value="Nom et prénom" />
            <x-text-input id="name" name="name" type="text" class="mt-1.5"
                          :value="old('name', $user->name)" required autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="pseudo" value="Pseudo public" />
            <x-text-input id="pseudo" name="pseudo" type="text" class="mt-1.5"
                          :value="old('pseudo', $user->pseudo)" />
            <x-input-error class="mt-2" :messages="$errors->get('pseudo')" />
        </div>

        <div>
            <x-input-label for="paypal_email" value="Adresse PayPal" />
            <x-text-input id="paypal_email" name="paypal_email" type="email" class="mt-1.5"
                          :value="old('paypal_email', $user->paypal_email)" />
            <p class="hint">Destination de vos retraits.</p>
            <x-input-error class="mt-2" :messages="$errors->get('paypal_email')" />
        </div>

        <div>
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input id="email" name="email" type="email" class="mt-1.5"
                          :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="alert-warn mt-3">
                    <p>Cette adresse n'est pas encore confirmée.</p>
                    <button form="send-verification" class="mt-1 font-semibold underline underline-offset-2">
                        Renvoyer le lien de confirmation
                    </button>
                </div>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-sm font-medium text-money-600">
                        Un nouveau lien vient d'être envoyé.
                    </p>
                @endif
            @endif
        </div>

        <div class="flex items-center gap-4 border-t border-ink-100 pt-5">
            <x-primary-button>Enregistrer</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition
                   x-init="setTimeout(() => show = false, 2500)"
                   class="text-sm font-medium text-money-600">Enregistré.</p>
            @endif
        </div>
    </form>
</section>
