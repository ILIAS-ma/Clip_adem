@php use App\Enums\UserRole; @endphp

<x-guest-layout>
    <div class="mb-8">
        <h2 class="font-display text-3xl font-bold text-ink-50">Créer un compte</h2>
        <p class="mt-2 text-ink-200">Gratuit. Vous choisissez ce que vous faites sur la plateforme.</p>
    </div>

    <x-google-button :role="$role->value" label="S’inscrire avec Google" />

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        @if ($parrain = request('parrain'))
            <input type="hidden" name="parrain" value="{{ $parrain }}" />
            <p class="rounded-xl border border-brand-500/40 bg-brand-500/10 px-4 py-3 text-sm text-brand-200">
                Vous avez été invité avec le code <span class="font-semibold tabular">{{ $parrain }}</span>.
            </p>
        @endif

        {{-- Piège à robots : un champ qu'un humain ne voit ni ne remplit
             jamais, mais qu'un bot de remplissage automatique complète
             presque toujours. Visuellement invisible plutôt que
             `display:none`, que certains bots savent déjà ignorer, et hors
             du parcours au clavier pour ne gêner personne. --}}
        <div class="absolute left-[-9999px]" aria-hidden="true">
            <label for="website">Laissez ce champ vide</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        {{-- On s'inscrit clippeur : les comptes créateur sont ouverts par l'équipe, pas depuis ce formulaire. --}}
        <input type="hidden" name="role" value="{{ UserRole::Clipper->value }}" />
        <x-input-error :messages="$errors->get('role')" class="mt-2" />

        <div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-input-label for="first_name" value="Prénom" />
                    <x-text-input id="first_name" class="mt-1.5" type="text" name="first_name"
                                  :value="old('first_name')" required autofocus autocomplete="given-name" />
                    <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="last_name" value="Nom" />
                    <x-text-input id="last_name" class="mt-1.5" type="text" name="last_name"
                                  :value="old('last_name')" required autocomplete="family-name" />
                    <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                </div>
            </div>
            {{-- Le nom réel sert aux versements ; le pseudo ou le nom de scène
                 est demandé à l'étape suivante, pour ne pas avoir à s'exposer
                 publiquement afin d'être payé. --}}
            <p class="hint">
                Utilisé pour vos versements. Votre pseudo public sera choisi à l'étape suivante.
            </p>
        </div>

        <div>
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input id="email" class="mt-1.5" type="email" name="email"
                          :value="old('email')" required autocomplete="username" />
            <p class="hint">Un lien de confirmation y sera envoyé.</p>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        {{--
            Le numéro est facultatif : l'adresse e-mail suffit pour ouvrir un compte.

            Saisi, il est quand même contrôlé (lisible, pas déjà pris par un autre compte).
            Un second compte peut donc se créer sans numéro, ce que l'unicité du numéro
            interdisait quand il était obligatoire.
        --}}
        <div>
            <x-input-label for="phone" value="Numéro de téléphone (facultatif)" />

            <div class="mt-1.5 grid gap-3 sm:grid-cols-[8.5rem_1fr]">
                <select id="phone_country" name="phone_country" class="field">
                    @foreach (App\Support\PhoneNumber::INDICATIFS as $code => $indicatif)
                        <option value="{{ $code }}" @selected(old('phone_country', 'FR') === $code)>
                            {{ $code }} · +{{ $indicatif }}
                        </option>
                    @endforeach
                </select>

                <x-text-input id="phone" type="tel" name="phone" inputmode="tel"
                              :value="old('phone')" autocomplete="tel"
                              placeholder="06 12 34 56 78" />
            </div>

            <p class="hint">
                Tu peux laisser ce champ vide. Il ne sert qu'à te joindre : ni démarchage, ni transmission à un tiers.
            </p>
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            <x-input-error :messages="$errors->get('phone_country')" class="mt-2" />
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

        {{-- Défi anti-robot Cloudflare Turnstile : invisible pour la grande
             majorité des visiteurs, il ne demande une action que si le
             trafic paraît suspect. --}}
        <div>
            <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="dark"></div>
            <x-input-error :messages="$errors->get('cf-turnstile-response')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">Créer mon compte</x-primary-button>
    </form>

    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce

    <p class="mt-8 text-center text-sm text-ink-200">
        Déjà inscrit ?
        <a href="{{ route('login') }}" class="font-semibold text-ink-50 underline underline-offset-2">
            Se connecter
        </a>
    </p>
</x-guest-layout>
