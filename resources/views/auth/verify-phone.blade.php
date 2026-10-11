@php
    use App\Support\PhoneNumber;

    $verifie = $user->hasVerifiedPhone();
    $attenteCode = $user->phone && ! $verifie;
    $codeSimule = session('codeSimule');
@endphp

<x-app-layout>
    <x-slot name="header">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Sécurité du compte</p>
        <h1 class="mt-1 font-display text-2xl font-bold text-ink-50">
            {{ $verifie ? 'Votre numéro' : 'Vérifier mon numéro' }}
        </h1>
        <p class="mt-1 text-ink-300">
            Un numéro confirmé protège votre compte et vos gains.
        </p>
    </x-slot>

    <div class="mx-auto max-w-lg space-y-5 px-4 py-8 sm:px-6">

        {{-- Deux étapes, et on voit toujours où l'on en est : un écran qui
             change sans prévenir laisse croire qu'on a raté quelque chose. --}}
        <ol class="flex items-center gap-3 text-sm" aria-label="Progression">
            @foreach ([1 => 'Votre numéro', 2 => 'Le code reçu'] as $rang => $libelle)
                @php $fait = $rang === 1 ? (bool) $user->phone : $verifie; @endphp

                <li class="flex items-center gap-2">
                    <span @class([
                        'flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold',
                        'bg-white text-ink-950' => $fait,
                        'bg-ink-700 text-ink-300' => ! $fait,
                    ])>{{ $fait ? '✓' : $rang }}</span>
                    <span @class(['font-semibold text-ink-100' => ! $fait, 'text-ink-400' => $fait])>
                        {{ $libelle }}
                    </span>
                </li>

                @if ($rang === 1)
                    <li aria-hidden="true" class="h-px flex-1 bg-ink-700"></li>
                @endif
            @endforeach
        </ol>

        @if (session('status') && session('status') !== 'phone-unverified')
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        {{--
            Le code affiché, faute de prestataire.

            Il ne peut apparaître que si rien n'est parti : le service ne rend
            le code que lorsque le pilote est simulé. Le dire franchement vaut
            mieux que « un SMS vous a été envoyé », qui laisse guetter un
            message inexistant.
        --}}
        @if ($codeSimule)
            <div class="card border-amber-500/40 bg-amber-500/[0.06] p-5">
                <p class="text-sm font-semibold text-amber-200">Aucun SMS n'est parti</p>
                <p class="mt-1 text-sm leading-relaxed text-ink-300">
                    La plateforme n'a pas encore de prestataire SMS. Voici votre code,
                    pour que la vérification reste possible :
                </p>
                <p class="mt-3 font-display text-3xl font-bold tracking-[0.3em] tabular text-ink-50">
                    {{ $codeSimule }}
                </p>
            </div>
        @elseif ($simule && ! $verifie)
            <div class="alert-warn">
                <p class="font-semibold">Les SMS ne partent pas encore</p>
                <p class="mt-1 leading-relaxed">
                    Le code s'affichera à l'écran au lieu d'être envoyé : la plateforme
                    n'a pas encore de prestataire. Rien ne sera expédié à ce numéro.
                </p>
            </div>
        @endif

        @if ($verifie)
            <div class="card p-6">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="chip-ok">Vérifié</span>
                    <p class="font-display text-lg font-bold tabular text-ink-50">
                        {{ PhoneNumber::masque($user->phone) }}
                    </p>
                </div>
                <p class="mt-3 text-sm leading-relaxed text-ink-300">
                    Pour en changer, saisissez le nouveau ci-dessous : il devra être vérifié
                    à son tour, et l'ancien cessera d'être associé à votre compte.
                </p>
            </div>
        @endif

        {{-- Le code d'abord quand on l'attend : c'est l'action en cours. Le
             champ du numéro reste accessible dessous, pour corriger un
             chiffre sans repartir de zéro. --}}
        @if ($attenteCode)
            <div class="card p-6 sm:p-7">
                <h2 class="font-display text-lg font-bold text-ink-50">Le code reçu</h2>
                <p class="mt-1 text-sm text-ink-400">
                    Envoyé au <span class="font-semibold tabular text-ink-200">{{ PhoneNumber::masque($user->phone) }}</span>.
                    Il expire au bout de {{ config('clipping.phone.code_minutes') }} minutes.
                </p>

                {{--
                    Six champs réels, un par chiffre, assemblés côté serveur.

                    La version précédente recopiait les cases dans un champ
                    caché par Alpine et désactivait le bouton tant qu'il
                    n'était pas rempli : Alpine ne démarrant pas sur cet
                    écran, le bouton restait inerte. La base montrait zéro
                    tentative pour un code demandé — rien n'avait jamais été
                    soumis, et le message « code incorrect » n'y était pour
                    rien.

                    Désormais le formulaire part sans JavaScript. Alpine
                    n'ajoute que le confort : avancer d'une case, reculer sur
                    une case vide, accepter un collage de six chiffres.
                --}}
                <form method="POST" action="{{ route('phone.confirm') }}" class="mt-6"
                      x-data="{
                          avancer(e) {
                              const cases = [...$el.querySelectorAll('.otp-case')];
                              const i = cases.indexOf(e.target);
                              const v = e.target.value.replace(/\D/g, '');

                              if (v.length > 1) {
                                  v.split('').slice(0, cases.length - i)
                                      .forEach((c, k) => cases[i + k].value = c);
                                  cases[Math.min(cases.length - 1, i + v.length)].focus();
                                  return;
                              }

                              e.target.value = v;
                              if (v) cases[i + 1]?.focus();
                          },
                          reculer(e) {
                              if (e.key !== 'Backspace' || e.target.value) return;

                              const cases = [...$el.querySelectorAll('.otp-case')];
                              cases[cases.indexOf(e.target) - 1]?.focus();
                          },
                      }"
                      x-on:input="avancer($event)"
                      x-on:keydown="reculer($event)">
                    @csrf

                    <div class="flex justify-between gap-2" role="group" aria-label="Code à six chiffres">
                        @for ($i = 0; $i < 6; $i++)
                            <input type="text" name="code[]" inputmode="numeric" maxlength="6"
                                   class="otp-case" placeholder=" "
                                   @if ($i === 0) autocomplete="one-time-code" autofocus @endif
                                   aria-label="Chiffre {{ $i + 1 }}">
                        @endfor
                    </div>

                    @error('code')
                        <p class="mt-3 text-sm text-rose-300">{{ $message }}</p>
                    @enderror

                    <button type="submit" class="btn-brand mt-6 w-full">Confirmer</button>
                </form>
            </div>
        @endif

        <div class="card p-6 sm:p-7">
            <h2 class="font-display text-base font-bold text-ink-50">
                {{ $verifie ? 'Changer de numéro' : ($attenteCode ? 'Mauvais numéro ?' : 'Votre numéro') }}
            </h2>

            <form method="POST" action="{{ route('phone.send') }}" class="mt-4 space-y-4"
                  x-data="{ reste: {{ $delai }} }"
                  x-init="reste > 0 && setInterval(() => reste > 0 && reste--, 1000)">
                @csrf

                <div class="grid gap-3 sm:grid-cols-[8.5rem_1fr]">
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
                               value="{{ old('phone', $verifie ? '' : $user->phone) }}" required>
                    </div>
                </div>

                @error('phone')
                    <p class="text-sm text-rose-300">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="btn-ghost" x-bind:disabled="reste > 0">
                        <span x-show="reste === 0">
                            {{ $attenteCode ? 'Renvoyer un code' : 'Recevoir un code' }}
                        </span>
                        {{-- Le délai est décompté, pas seulement appliqué : un
                             bouton grisé sans explication se lit comme une
                             panne, et un nombre figé comme un écran gelé. --}}
                        <span x-show="reste > 0" x-cloak>
                            Nouveau code dans <span x-text="reste" class="tabular"></span> s
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <div class="card p-6">
            <h2 class="font-display text-base font-bold text-ink-50">Pourquoi ce numéro</h2>
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
