@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-ink-900">Mes comptes réseaux</h1>
        <p class="mt-1 text-ink-500">C'est par eux que vos vues sont relevées et vos gains calculés.</p>
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
                <div class="border-b border-ink-100 px-6 py-4">
                    <h2 class="font-display text-lg font-bold text-ink-900">Comptes liés</h2>
                </div>

                <ul class="divide-y divide-ink-100">
                    @foreach ($accounts as $account)
                        <li class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                            <div class="min-w-0">
                                <p class="font-semibold text-ink-900">
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
                                    <p class="mt-2 text-sm font-semibold text-red-600">
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
                                    <a href="{{ route('social.redirect', $account->platform->value) }}" class="btn-ghost">
                                        Relier
                                    </a>
                                @else
                                    <span class="chip-ok">Actif</span>
                                    <form method="POST" action="{{ route('accounts.destroy', $account) }}"
                                          onsubmit="return confirm('Délier ce compte ? Vos clips restent visibles, mais leurs vues cesseront d\'être relevées.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-ink-400 underline-offset-2 hover:text-red-600 hover:underline">
                                            Délier
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card p-6 sm:p-8">
            <h2 class="font-display text-lg font-bold text-ink-900">
                {{ $accounts->isEmpty() ? 'Liez votre premier compte' : 'Lier un autre compte' }}
            </h2>
            <p class="mt-1.5 text-sm text-ink-500">
                Vous ne pouvez rejoindre une campagne qu'avec un compte lié.
            </p>

            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                @foreach ($platforms as $platform)
                    <a href="{{ route('social.redirect', $platform->value) }}"
                       class="group rounded-2xl border border-ink-200 p-5 transition hover:-translate-y-0.5 hover:border-ink-800 hover:shadow-card">
                        <span class="font-display text-base font-bold text-ink-900">{{ $platform->label() }}</span>

                        @if ($simulated[$platform->value])
                            {{-- Laisser croire à une vraie liaison ferait perdre du
                                 temps au premier comportement inattendu. --}}
                            <span class="chip-wait mt-2 block w-fit">Démonstration</span>
                        @else
                            <span class="chip-ok mt-2 block w-fit">Connexion officielle</span>
                        @endif
                    </a>
                @endforeach
            </div>

            @if (collect($simulated)->contains(true))
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
