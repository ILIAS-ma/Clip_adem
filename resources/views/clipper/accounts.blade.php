@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Mes comptes réseaux</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="rounded-md border-l-4 border-emerald-400 bg-emerald-50 p-4 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @error('social')
                <div class="rounded-md border-l-4 border-red-400 bg-red-50 p-4 text-sm text-red-800">
                    {{ $message }}
                </div>
            @enderror

            <div class="rounded-lg bg-white shadow">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Comptes liés</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        C'est par ces comptes que vos vues sont relevées. Un compte déconnecté cesse d'être compté.
                    </p>
                </div>

                @if ($accounts->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-gray-500">
                        Aucun compte lié pour le moment.
                    </p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($accounts as $account)
                            <li class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900">
                                        {{ $account->platform->label() }}
                                        @if ($account->handle)
                                            · &#64;{{ $account->handle }}
                                        @endif
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        {{ $account->clips_count }} clip{{ $account->clips_count > 1 ? 's' : '' }}
                                        @if ($account->followers_count !== null)
                                            · {{ Money::views($account->followers_count) }} abonnés
                                        @endif
                                        @if ($account->token_expires_at)
                                            · jeton valide jusqu'au {{ $account->token_expires_at->format('d/m/Y') }}
                                        @endif
                                    </p>

                                    @if ($account->needs_reconnect)
                                        {{-- La panne la plus silencieuse du système : sans ce
                                             message, le clippeur croit que ses vues montent. --}}
                                        <p class="mt-1 text-xs font-medium text-red-600">
                                            À reconnecter — vos vues ne sont plus comptées sur ce compte.
                                        </p>
                                    @elseif (! $account->is_active)
                                        <p class="mt-1 text-xs text-gray-500">Compte délié.</p>
                                    @endif
                                </div>

                                <div class="flex shrink-0 items-center gap-3">
                                    @if ($account->needs_reconnect || ! $account->is_active)
                                        <a href="{{ route('social.redirect', $account->platform->value) }}"
                                           class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                            Reconnecter
                                        </a>
                                    @else
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                            Actif
                                        </span>

                                        <form method="POST" action="{{ route('accounts.destroy', $account) }}"
                                              onsubmit="return confirm('Délier ce compte ? Vos clips déjà soumis restent visibles, mais leurs vues cesseront d\'être relevées.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-gray-500 underline hover:text-gray-700">
                                                Délier
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="rounded-lg bg-white p-6 shadow">
                <h3 class="text-base font-semibold text-gray-900">Lier un compte</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Vous ne pouvez rejoindre une campagne qu'avec un compte lié.
                </p>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    @foreach ($platforms as $platform)
                        <a href="{{ route('social.redirect', $platform->value) }}"
                           class="flex flex-col rounded-lg border border-gray-200 p-4 transition hover:border-emerald-400 hover:bg-emerald-50">
                            <span class="font-medium text-gray-900">{{ $platform->label() }}</span>

                            @if ($simulated[$platform->value])
                                {{-- Dit franchement que la connexion est simulée : laisser croire
                                     à une vraie liaison ferait perdre du temps au premier bug. --}}
                                <span class="mt-1 text-xs text-amber-600">
                                    Mode démonstration — connexion simulée, vues générées
                                </span>
                            @else
                                <span class="mt-1 text-xs text-gray-500">Connexion officielle</span>
                            @endif
                        </a>
                    @endforeach
                </div>

                @if (collect($simulated)->contains(true))
                    <p class="mt-4 rounded-md bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">
                        Les plateformes marquées « démonstration » n'ont pas encore leurs identifiants
                        d'application. La liaison, la synchronisation des vues et le calcul des gains
                        fonctionnent de bout en bout, mais sur des données simulées.
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
