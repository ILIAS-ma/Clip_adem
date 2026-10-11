@php use App\Support\PhoneNumber; @endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-white">Vérifier mon numéro</h1>
        <p class="mt-1 text-ink-300">
            Un numéro confirmé protège votre compte et vos gains.
        </p>
    </x-slot>

    <div class="mx-auto max-w-xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        @if (session('status'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        @if ($user->hasVerifiedPhone())
            <div class="card p-6 sm:p-8">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="chip-ok">Vérifié</span>
                    <p class="font-semibold tabular text-white">{{ PhoneNumber::masque($user->phone) }}</p>
                </div>
                <p class="mt-3 text-sm leading-relaxed text-ink-300">
                    Ce numéro est confirmé. Pour en changer, saisissez le nouveau ci-dessous :
                    il devra être vérifié à son tour, et l'ancien cessera d'être associé à votre compte.
                </p>
            </div>
        @endif

        {{-- Deux étapes sur un même écran plutôt que deux pages : on garde le
             numéro saisi sous les yeux au moment de taper le code, ce qui
             permet de voir tout de suite qu'on s'est trompé d'un chiffre. --}}
        <div class="card p-6 sm:p-8">
            <h2 class="font-display text-lg font-bold text-white">
                {{ $user->hasVerifiedPhone() ? 'Changer de numéro' : 'Votre numéro' }}
            </h2>

            <form method="POST" action="{{ route('phone.send') }}" class="mt-5 space-y-4">
                @csrf

                <div class="grid gap-3 sm:grid-cols-[9rem_1fr]">
                    <div>
                        <label class="label" for="phone_country">Pays</label>
                        <select id="phone_country" name="phone_country" class="input mt-1.5">
                            @foreach ($pays as $code => $indicatif)
                                <option value="{{ $code }}"
                                    @selected(old('phone_country', $user->country ?? 'FR') === $code)>
                                    {{ $code }} · +{{ $indicatif }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label" for="phone">Numéro</label>
                        {{-- Pré-rempli avec le numéro donné à l'inscription : il
                             n'y a plus qu'à cliquer. Le retaper serait une
                             occasion de se tromper d'un chiffre, et le code
                             partirait alors vers un inconnu. --}}
                        <input id="phone" name="phone" type="tel" inputmode="tel"
                               autocomplete="tel" class="input mt-1.5"
                               placeholder="06 12 34 56 78"
                               value="{{ old('phone', $user->hasVerifiedPhone() ? '' : $user->phone) }}" required>
                    </div>
                </div>

                @error('phone')
                    <p class="text-sm text-rose-300">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn-brand" @disabled($delai > 0)>
                        <span wire:loading.remove>Recevoir un code</span>
                    </button>

                    @if ($delai > 0)
                        {{-- Le délai est dit, pas seulement appliqué : un bouton
                             grisé sans explication se lit comme une panne. --}}
                        <span class="text-sm text-ink-400">
                            Nouveau code possible dans {{ $delai }} seconde{{ $delai > 1 ? 's' : '' }}.
                        </span>
                    @endif
                </div>
            </form>
        </div>

        @if ($user->phone && ! $user->hasVerifiedPhone())
            <div class="card p-6 sm:p-8">
                <h2 class="font-display text-lg font-bold text-white">Le code reçu</h2>
                <p class="mt-1.5 text-sm text-ink-300">
                    Envoyé au {{ PhoneNumber::masque($user->phone) }}.
                    Il expire au bout de {{ config('clipping.phone.code_minutes') }} minutes.
                </p>

                <form method="POST" action="{{ route('phone.confirm') }}" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label class="label" for="code">Code à six chiffres</label>
                        {{-- `one-time-code` laisse le téléphone proposer le code
                             directement depuis le SMS, sans recopie. --}}
                        <input id="code" name="code" type="text" inputmode="numeric"
                               autocomplete="one-time-code" maxlength="6" required
                               class="input mt-1.5 text-center font-display text-2xl tracking-[0.5em] tabular"
                               placeholder="······">
                    </div>

                    @error('code')
                        <p class="text-sm text-rose-300">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="btn-brand w-full">Confirmer</button>
                </form>
            </div>
        @endif

        <div class="card p-6">
            <h2 class="font-display text-base font-bold text-white">Pourquoi ce numéro</h2>
            <p class="mt-2 text-sm leading-relaxed text-ink-300">
                Une adresse e-mail jetable s'obtient en dix secondes ; une ligne téléphonique non.
                C'est ce qui rend le bannissement d'un fraudeur réellement dissuasif, et ce qui
                protège vos gains d'un compte qui en créerait dix pour épuiser un budget.
            </p>
            <p class="mt-2 text-sm leading-relaxed text-ink-300">
                Il ne sert qu'à cela : ni démarchage, ni transmission à un tiers.
            </p>
        </div>
    </div>
</x-app-layout>
