@props(['user'])

@php
    use App\Enums\PayoutMethod;
    use App\Support\Iban;

    $selected = old('payout_method', $user->payoutMethod()->value);
@endphp

{{--
    Un seul bloc réutilisé par la fin d'inscription et par la page « Moyen de
    paiement » : deux formulaires distincts finiraient par ne plus demander la
    même chose, et c'est par là que l'argent part au mauvais endroit.
--}}
<div x-data="{ method: @js($selected) }" class="space-y-5">

    <div>
        <x-input-label value="Comment souhaitez-vous être payé ?" />

        <div class="mt-2 grid gap-3 sm:grid-cols-2">
            @foreach (PayoutMethod::cases() as $method)
                <label class="cursor-pointer rounded-xl border p-4 transition"
                       :class="method === @js($method->value)
                           ? 'border-brand-500 bg-brand-500/10'
                           : 'border-ink-700 hover:border-ink-600'">
                    <span class="flex items-center gap-2">
                        <input type="radio" name="payout_method" value="{{ $method->value }}"
                               x-model="method"
                               class="h-4 w-4 border-ink-600 bg-ink-900 text-brand-500 focus:ring-brand-500" />
                        <span class="font-semibold text-ink-50">{{ $method->label() }}</span>
                    </span>
                    <span class="mt-1.5 block pl-6 text-xs leading-relaxed text-ink-400">
                        {{ $method->hint() }}
                    </span>
                </label>
            @endforeach
        </div>

        <x-input-error class="mt-2" :messages="$errors->get('payout_method')" />
    </div>

    {{-- PayPal --}}
    <div x-show="method === @js(PayoutMethod::PayPal->value)" x-cloak>
        <x-input-label for="paypal_email" value="Adresse PayPal" />
        <x-text-input id="paypal_email" name="paypal_email" type="email" class="mt-1.5"
                      :value="old('paypal_email', $user->paypal_email)" />
        <p class="hint">Elle peut différer de votre adresse de connexion.</p>
        <x-input-error class="mt-2" :messages="$errors->get('paypal_email')" />
    </div>

    {{-- Virement bancaire --}}
    <div x-show="method === @js(PayoutMethod::BankTransfer->value)" x-cloak class="space-y-5">
        <div>
            <x-input-label for="account_holder" value="Titulaire du compte" />
            <x-text-input id="account_holder" name="account_holder" type="text" class="mt-1.5"
                          :value="old('account_holder', $user->account_holder)" autocomplete="name" />
            <p class="hint">Exactement comme sur le RIB : un nom qui ne correspond pas fait rejeter le virement.</p>
            <x-input-error class="mt-2" :messages="$errors->get('account_holder')" />
        </div>

        <div>
            <x-input-label for="iban" value="IBAN" />
            <x-text-input id="iban" name="iban" type="text" class="mt-1.5 tabular uppercase"
                          placeholder="FR76 3000 4000 0100 0000 0000 123"
                          :value="old('iban', $user->iban ? Iban::format($user->iban) : '')" />
            <p class="hint">
                Espaces autorisés. Nous n'en conservons que les quatre derniers chiffres en clair ;
                le reste est chiffré.
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('iban')" />
        </div>

        <div>
            <x-input-label for="bic" value="BIC (facultatif)" />
            <x-text-input id="bic" name="bic" type="text" class="mt-1.5 uppercase"
                          placeholder="BNPAFRPP" :value="old('bic', $user->bic)" />
            <p class="hint">Inutile dans la zone SEPA. À renseigner si votre banque le réclame.</p>
            <x-input-error class="mt-2" :messages="$errors->get('bic')" />
        </div>
    </div>
</div>
