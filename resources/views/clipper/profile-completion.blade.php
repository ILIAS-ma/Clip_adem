<x-app-layout>
    <x-slot name="header">
        <span class="chip-wait">Étape 2 sur 2</span>
        <h1 class="mt-3 font-display text-2xl font-bold text-ink-900">Complétez votre profil</h1>
        <p class="mt-1 text-ink-500">Dernière étape avant de pouvoir rejoindre une campagne.</p>
    </x-slot>

    <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">

        @if ($missing)
            {{-- Dit pourquoi on bloque, pas seulement qu'on bloque. --}}
            <div class="alert-warn mb-6">
                <p class="font-semibold">Pourquoi ces informations</p>
                <p class="mt-1 leading-relaxed">
                    Sans elles, vos gains resteraient bloqués : impossible de vous identifier
                    publiquement ni de vous verser quoi que ce soit.
                </p>
            </div>
        @endif

        <div class="card p-6 sm:p-8">
            <form method="POST" action="{{ route('profile.complete.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div>
                    <x-input-label for="pseudo" value="Pseudo public" />
                    <x-text-input id="pseudo" name="pseudo" type="text" class="mt-1.5"
                                  :value="old('pseudo', $user->pseudo)" required autofocus />
                    <p class="hint">
                        Le nom sous lequel vous apparaissez sur la plateforme. Votre nom réel reste privé.
                    </p>
                    <x-input-error class="mt-2" :messages="$errors->get('pseudo')" />
                </div>

                <div>
                    <x-input-label for="country" value="Pays de résidence" />
                    <select id="country" name="country" required class="field mt-1.5">
                        <option value="">Choisir…</option>
                        @foreach ([
                            'FR' => 'France', 'BE' => 'Belgique', 'CH' => 'Suisse', 'CA' => 'Canada',
                            'MA' => 'Maroc', 'DZ' => 'Algérie', 'TN' => 'Tunisie', 'SN' => 'Sénégal',
                            'CI' => "Côte d'Ivoire", 'LU' => 'Luxembourg',
                        ] as $code => $label)
                            <option value="{{ $code }}" @selected(old('country', $user->country) === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="hint">PayPal restreint certains versements selon le pays du bénéficiaire.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('country')" />
                </div>

                <div>
                    <x-input-label for="paypal_email" value="Adresse PayPal" />
                    <x-text-input id="paypal_email" name="paypal_email" type="email" class="mt-1.5"
                                  :value="old('paypal_email', $user->paypal_email)" required />
                    <p class="hint">
                        Destination de vos retraits. Elle peut différer de votre adresse de connexion.
                    </p>
                    <x-input-error class="mt-2" :messages="$errors->get('paypal_email')" />
                </div>

                <div class="border-t border-ink-100 pt-6">
                    <x-primary-button class="w-full sm:w-auto">Enregistrer et continuer</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
