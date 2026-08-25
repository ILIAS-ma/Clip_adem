<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Compléter votre profil</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow sm:p-8">

                @if ($missing)
                    <div class="mb-6 rounded-md border-l-4 border-amber-400 bg-amber-50 p-4">
                        <p class="text-sm text-amber-800">
                            {{-- Dit pourquoi on bloque, pas seulement qu'on bloque. --}}
                            Ces informations sont nécessaires pour rejoindre une campagne et recevoir vos paiements.
                            Sans elles, vos gains resteraient bloqués.
                        </p>
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.complete.update') }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <x-input-label for="pseudo" value="Pseudo public" />
                        <x-text-input id="pseudo" name="pseudo" type="text" class="mt-1 block w-full"
                                      :value="old('pseudo', $user->pseudo)" required autofocus />
                        <p class="mt-1 text-xs text-gray-500">
                            Le nom sous lequel vous apparaissez sur la plateforme. Votre nom réel reste privé.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('pseudo')" />
                    </div>

                    <div>
                        <x-input-label for="country" value="Pays de résidence" />
                        <select id="country" name="country" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="">Choisir…</option>
                            @foreach (['FR' => 'France', 'BE' => 'Belgique', 'CH' => 'Suisse', 'CA' => 'Canada', 'MA' => 'Maroc', 'DZ' => 'Algérie', 'TN' => 'Tunisie', 'SN' => 'Sénégal', 'CI' => "Côte d'Ivoire", 'LU' => 'Luxembourg'] as $code => $label)
                                <option value="{{ $code }}" @selected(old('country', $user->country) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            PayPal restreint certains versements selon le pays du bénéficiaire.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('country')" />
                    </div>

                    <div>
                        <x-input-label for="paypal_email" value="Adresse PayPal" />
                        <x-text-input id="paypal_email" name="paypal_email" type="email" class="mt-1 block w-full"
                                      :value="old('paypal_email', $user->paypal_email)" required />
                        <p class="mt-1 text-xs text-gray-500">
                            C'est sur cette adresse que vos retraits seront versés. Elle peut différer de votre adresse de connexion.
                        </p>
                        <x-input-error class="mt-2" :messages="$errors->get('paypal_email')" />
                    </div>

                    <div class="flex items-center gap-4">
                        <x-primary-button>Enregistrer</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
