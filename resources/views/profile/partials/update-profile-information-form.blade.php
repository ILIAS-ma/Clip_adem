<section>
    <header>
        <h2 class="font-display text-lg font-bold text-ink-50">Informations du compte</h2>
        <p class="mt-1 text-sm text-ink-300">
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

        @if ($user->isClipper())
            {{-- Le moyen de paiement a sa propre page : il se change plus
                 souvent que le reste, et une erreur y coûte un virement. --}}
            <div class="rounded-xl border border-ink-700 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Moyen de paiement</p>
                <p class="mt-1 font-display font-bold tabular text-ink-50">
                    {{ $user->payoutDestinationLabel() ?? 'Aucune destination enregistrée' }}
                </p>
                <a href="{{ route('payout-method.edit') }}" wire:navigate
                   class="mt-2 inline-block text-sm font-semibold text-brand-400 underline underline-offset-2">
                    Modifier
                </a>
            </div>
        @endif

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
                    <p class="mt-2 text-sm font-medium text-brand-400">
                        Un nouveau lien vient d'être envoyé.
                    </p>
                @endif
            @endif
        </div>

        <div class="flex items-center gap-4 border-t border-ink-700 pt-5">
            <x-primary-button>Enregistrer</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition
                   x-init="setTimeout(() => show = false, 2500)"
                   class="text-sm font-medium text-brand-400">Enregistré.</p>
            @endif
        </div>
    </form>
</section>
