@php use App\Enums\UserRole; @endphp

<x-guest-layout>
    <div class="mb-8">
        <h2 class="font-display text-3xl font-bold text-ink-50">Créer un compte</h2>
        <p class="mt-2 text-ink-300">Gratuit. Vous choisissez ce que vous faites sur la plateforme.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5" x-data="{ role: '{{ old('role', $role->value) }}' }">
        @csrf

        <fieldset>
            <legend class="label mb-2">Je suis…</legend>

            <div class="grid grid-cols-2 gap-3">
                @foreach ([
                    [UserRole::Clipper, 'Clippeur', 'Je publie des clips et je suis payé aux vues'],
                    [UserRole::Artist, 'Artiste', 'Je suis promu et je suis les statistiques'],
                ] as [$option, $title, $description])
                    <label class="cursor-pointer">
                        <input type="radio" name="role" value="{{ $option->value }}"
                               x-model="role" class="peer sr-only">
                        <span class="block h-full rounded-2xl border-2 border-ink-700 p-4 transition
                                     peer-checked:border-ink-700 peer-checked:bg-ink-800
                                     peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-focus-visible:ring-offset-2">
                            <span class="block font-display text-base font-bold text-ink-50">{{ $title }}</span>
                            <span class="mt-1 block text-xs leading-relaxed text-ink-300">{{ $description }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </fieldset>

        <div>
            <x-input-label for="name" value="Nom et prénom" />
            <x-text-input id="name" class="mt-1.5" type="text" name="name"
                          :value="old('name')" required autofocus autocomplete="name" />
            {{-- Le nom réel sert aux versements ; le pseudo ou le nom de scène
                 est demandé à l'étape suivante, pour ne pas avoir à s'exposer
                 publiquement afin d'être payé. --}}
            <p class="hint" x-show="role === 'clipper'">
                Utilisé pour vos versements. Votre pseudo public sera choisi à l'étape suivante.
            </p>
            <p class="hint" x-show="role === 'artist'" x-cloak>
                Votre nom réel. Votre nom de scène sera choisi à l'étape suivante.
            </p>
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

    <p class="mt-8 text-center text-sm text-ink-300">
        Déjà inscrit ?
        <a href="{{ route('login') }}" class="font-semibold text-ink-50 underline underline-offset-2">
            Se connecter
        </a>
    </p>
</x-guest-layout>
