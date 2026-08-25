@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Bonjour {{ $clipper->displayName() }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if (session('status') && ! str_contains(session('status'), '-'))
                <div class="rounded-md border-l-4 border-emerald-400 bg-emerald-50 p-4 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($needsReconnect > 0)
                {{-- La panne la plus silencieuse du système : le clippeur croit que
                     ses vues montent alors que la synchro est morte. --}}
                <div class="rounded-md border-l-4 border-red-400 bg-red-50 p-4">
                    <p class="text-sm font-medium text-red-800">
                        {{ $needsReconnect }} compte{{ $needsReconnect > 1 ? 's' : '' }} à reconnecter
                    </p>
                    <p class="mt-1 text-sm text-red-700">
                        Tant qu'un compte est déconnecté, les vues de ses clips ne sont plus comptées ni rémunérées.
                    </p>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm text-gray-500">Vues cumulées</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ Money::views($views) }}</p>
                    @if ($unpaidViews > 0)
                        <p class="mt-1 text-xs text-amber-600">
                            {{ Money::views($unpaidViews) }} non rémunérées
                        </p>
                    @endif
                </div>

                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm text-gray-500">Gains validés</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ Money::euros($earnedCents) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Inscrits au grand livre</p>
                </div>

                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm text-gray-500">Solde disponible</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700">{{ Money::euros($balanceCents) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Retrait à partir de {{ Money::euros(config('clipping.payouts.minimum_cents')) }}</p>
                </div>

                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm text-gray-500">Comptes liés</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ $accountsCount }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $openCampaigns }} campagne{{ $openCampaigns > 1 ? 's' : '' }} ouverte{{ $openCampaigns > 1 ? 's' : '' }}</p>
                </div>
            </div>

            @if ($accountsCount === 0)
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center">
                    <h3 class="text-base font-semibold text-gray-900">Aucun compte réseau lié</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">
                        La connexion TikTok, YouTube et Instagram arrive prochainement. En attendant, vous pouvez
                        parcourir les campagnes ouvertes et lire leur brief.
                    </p>
                    <a href="{{ route('campaigns.index') }}"
                       class="mt-4 inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                        Voir les campagnes
                    </a>
                </div>
            @endif

            <div class="rounded-lg bg-white shadow">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Mes clips</h3>
                </div>

                @if ($clips->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-gray-500">
                        Aucun clip soumis pour le moment.
                    </p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs uppercase tracking-wide text-gray-500">
                                    <th class="px-6 py-3 text-left font-medium">Campagne</th>
                                    <th class="px-6 py-3 text-left font-medium">Statut</th>
                                    <th class="px-6 py-3 text-right font-medium">Vues</th>
                                    <th class="px-6 py-3 text-right font-medium">Gagné</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($clips as $clip)
                                    <tr>
                                        <td class="px-6 py-3">
                                            <a href="{{ route('campaigns.show', $clip->campaign) }}"
                                               class="font-medium text-emerald-700 hover:underline">
                                                {{ $clip->campaign?->title }}
                                            </a>
                                            <div class="text-xs text-gray-500">{{ $clip->platform->label() }}</div>
                                        </td>
                                        <td class="px-6 py-3">
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                {{ $clip->status->label() }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-right tabular-nums">{{ Money::views($clip->views_total) }}</td>
                                        <td class="px-6 py-3 text-right font-medium tabular-nums">{{ Money::euros($clip->earned_cents) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
