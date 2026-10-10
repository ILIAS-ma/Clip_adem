@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-ink-50">Mes comptes réseaux</h1>
        <p class="mt-1 text-ink-300">C'est par eux que vos vues sont relevées et vos gains calculés.</p>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        @if (session('status'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        @error('social')
            <div class="alert-danger">{{ $message }}</div>
        @enderror

        @if ($accounts->isNotEmpty())
            <div class="card">
                <div class="border-b border-ink-700 px-6 py-4">
                    <h2 class="font-display text-lg font-bold text-ink-50">Comptes liés</h2>
                </div>

                <ul class="divide-y divide-ink-700">
                    @foreach ($accounts as $account)
                        <li class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                            <div class="min-w-0">
                                <p class="font-semibold text-ink-50">
                                    {{ $account->platform->label() }}
                                    @if ($account->handle)
                                        <span class="text-ink-400">· &#64;{{ $account->handle }}</span>
                                    @endif
                                </p>

                                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-400">
                                    <span class="tabular">{{ $account->clips_count }} clip{{ $account->clips_count > 1 ? 's' : '' }}</span>
                                    @if ($account->followers_count !== null)
                                        <span aria-hidden="true">·</span>
                                        <span class="tabular">{{ Money::views($account->followers_count) }} abonnés</span>
                                    @endif
                                    @if ($account->token_expires_at && $account->is_active && ! $account->needs_reconnect)
                                        <span aria-hidden="true">·</span>
                                        <span>jeton valide jusqu'au {{ $account->token_expires_at->format('d/m/Y') }}</span>
                                    @endif
                                </p>

                                @if ($account->needs_reconnect)
                                    <p class="mt-2 text-sm font-semibold text-red-400">
                                        À reconnecter — vos vues ne sont plus comptées sur ce compte.
                                    </p>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-3">
                                @if ($account->needs_reconnect)
                                    <span class="chip-danger">Déconnecté</span>
                                    <a href="{{ route('social.redirect', $account->platform->value) }}" class="btn-brand">
                                        Reconnecter
                                    </a>
                                @elseif (! $account->is_active)
                                    <span class="chip-neutral">Délié</span>
                                    <a href="{{ route('social.redirect', $account->platform->value) }}" class="btn-brand">
                                        Relier
                                    </a>
                                @else
                                    <span class="chip-ok">Actif</span>

                                    {{--
                                        Confirmation en deux temps plutôt qu'un
                                        `confirm()` du navigateur : la boîte native
                                        est brutale, illisible sur mobile, et son
                                        texte ne peut pas rappeler la conséquence
                                        exacte. Ici la question s'ouvre à la place
                                        du bouton, et « Annuler » est le choix le
                                        plus facile à atteindre.
                                    --}}
                                    <div x-data="{ confirming: false }" class="flex items-center gap-2">
                                        <button type="button"
                                                x-show="! confirming"
                                                x-on:click="confirming = true"
                                                x-transition.opacity.duration.150ms
                                                class="btn-danger">
                                            Délier
                                        </button>

                                        <div x-show="confirming"
                                             x-cloak
                                             x-transition.opacity.duration.150ms
                                             class="flex items-center gap-2">
                                            <span class="text-sm text-ink-300">
                                                Les vues cesseront d’être relevées.
                                            </span>

                                            <button type="button"
                                                    x-on:click="confirming = false"
                                                    class="btn-ghost">
                                                Annuler
                                            </button>

                                            <form method="POST" action="{{ route('accounts.destroy', $account) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-danger">
                                                    Confirmer
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card p-6 sm:p-8">
            <h2 class="font-display text-lg font-bold text-ink-50">
                {{ $accounts->isEmpty() ? 'Liez votre premier compte' : 'Lier un autre compte' }}
            </h2>
            <p class="mt-1.5 text-sm text-ink-300">
                Vous ne pouvez rejoindre une campagne qu'avec un compte lié.
            </p>

            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                @foreach ($platforms as $index => $platform)
                    <a href="{{ route('social.redirect', $platform->value) }}"
                       data-reveal style="--reveal-delay: {{ $index * 90 }}ms"
                       class="group rounded-2xl border border-ink-700 p-5 transition hover:-translate-y-0.5 hover:border-brand-500 hover:shadow-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-400">
                        <span class="font-display text-base font-bold text-ink-50">{{ $platform->label() }}</span>

                        {{-- La pastille reste utile quand on force l'affichage
                             des plateformes simulées pour éprouver le parcours
                             sans clés : laisser croire à une vraie liaison
                             ferait perdre du temps au premier comportement
                             inattendu. --}}
                        @if ($simulated[$platform->value])
                            <span class="chip-wait mt-2 block w-fit">Démonstration</span>
                        @else
                            <span class="chip-ok mt-2 block w-fit">Connexion officielle</span>
                        @endif
                    </a>
                @endforeach
            </div>

            @if (collect($platforms)->contains(fn ($p) => $simulated[$p->value]))
                <div class="alert-warn mt-6">
                    <p class="font-semibold">Mode démonstration</p>
                    <p class="mt-1 leading-relaxed">
                        Les plateformes marquées ainsi n'ont pas encore leurs identifiants d'application.
                        La liaison, le relevé des vues et le calcul des gains fonctionnent de bout en bout,
                        mais sur des données simulées.
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
