@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Mes revenus</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="rounded-md border-l-4 border-emerald-400 bg-emerald-50 p-4 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm text-gray-500">Gains validés</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ Money::euros($earnedCents) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Définitivement acquis</p>
                </div>

                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm text-gray-500">Estimé en attente</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700">{{ Money::euros($pendingCents) }}</p>
                    {{-- L'estimation vient du moteur de budget : reliquat et plafonds
                         déjà appliqués, donc jamais une promesse intenable. --}}
                    <p class="mt-1 text-xs text-gray-500">Vues pas encore créditées</p>
                </div>

                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm text-gray-500">Déjà demandé ou versé</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ Money::euros($lockedCents) }}</p>
                </div>

                <div class="rounded-lg bg-white p-5 shadow ring-2 ring-emerald-500">
                    <p class="text-sm text-gray-500">Solde retirable</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700">{{ Money::euros($balanceCents) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Minimum {{ Money::euros($minimumCents) }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">

                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-base font-semibold text-gray-900">Demander un retrait</h3>
                    <p class="mt-1 text-sm text-gray-500">Versé sur {{ $clipper->paypal_email }}</p>

                    <div class="mt-4">
                        @livewire('request-payout')
                    </div>
                </div>

                <div class="rounded-lg bg-white shadow lg:col-span-2">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Mes retraits</h3>
                    </div>

                    @if ($payouts->isEmpty())
                        <p class="px-6 py-8 text-center text-sm text-gray-500">Aucun retrait demandé.</p>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($payouts as $payout)
                                <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                                    <div>
                                        <p class="font-medium tabular-nums text-gray-900">{{ Money::euros($payout->amount_cents) }}</p>
                                        <p class="text-xs text-gray-500">
                                            Demandé le {{ $payout->requested_at?->format('d/m/Y') }}
                                            @if ($payout->processed_at)
                                                · versé le {{ $payout->processed_at->format('d/m/Y') }}
                                            @endif
                                        </p>
                                        @if ($payout->failure_reason)
                                            <p class="mt-1 text-xs text-red-600">{{ $payout->failure_reason }}</p>
                                        @endif
                                    </div>

                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-emerald-100 text-emerald-800' => $payout->status->value === 'paid',
                                        'bg-amber-100 text-amber-800' => in_array($payout->status->value, ['requested', 'approved', 'processing']),
                                        'bg-red-100 text-red-800' => $payout->status->value === 'failed',
                                        'bg-gray-100 text-gray-700' => $payout->status->value === 'cancelled',
                                    ])>{{ $payout->status->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="rounded-lg bg-white shadow">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Historique des crédits</h3>
                    <p class="mt-1 text-sm text-gray-500">Chaque ligne correspond à des vues rémunérées.</p>
                </div>

                @if ($transactions->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-gray-500">Aucun crédit pour le moment.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs uppercase tracking-wide text-gray-500">
                                    <th class="px-6 py-3 text-left font-medium">Date</th>
                                    <th class="px-6 py-3 text-left font-medium">Campagne</th>
                                    <th class="px-6 py-3 text-right font-medium">Vues</th>
                                    <th class="px-6 py-3 text-right font-medium">Montant</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($transactions as $transaction)
                                    <tr>
                                        <td class="px-6 py-3 text-gray-500">{{ $transaction->created_at->format('d/m/Y H:i') }}</td>
                                        <td class="px-6 py-3">{{ $transaction->campaign?->title }}</td>
                                        <td class="px-6 py-3 text-right tabular-nums">{{ Money::views($transaction->views_delta) }}</td>
                                        <td @class([
                                            'px-6 py-3 text-right font-medium tabular-nums',
                                            'text-red-600' => $transaction->amount_cents < 0,
                                            'text-gray-900' => $transaction->amount_cents >= 0,
                                        ])>{{ Money::euros($transaction->amount_cents) }}</td>
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
