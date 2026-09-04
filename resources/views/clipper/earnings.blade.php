@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-ink-50">Mes revenus</h1>
        <p class="mt-1 text-ink-300">
            @if ($clipper->hasPayoutDestination())
                Versements par {{ $clipper->payoutMethod()->label() }}
                sur <span class="tabular text-ink-100">{{ $clipper->payoutDestinationLabel() }}</span>
            @else
                Aucune destination de paiement enregistrée
            @endif
            ·
            <a href="{{ route('payout-method.edit') }}" wire:navigate
               class="font-semibold text-brand-400 underline underline-offset-2">Modifier</a>
        </p>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        @if (session('status'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat label="Gains validés" :value="Money::euros($earnedCents)"
                    tone="money" hint="Définitivement acquis" />

            {{-- L'estimation vient du moteur de budget : reliquat et plafonds
                 déjà appliqués, donc jamais une promesse intenable. --}}
            <x-stat label="Estimé en attente" :value="Money::euros($pendingCents)"
                    tone="brand" hint="Vues pas encore créditées" />

            <x-stat label="Demandé ou versé" :value="Money::euros($lockedCents)" />

            <x-stat label="Solde retirable" :value="Money::euros($balanceCents)"
                    tone="money" highlight
                    :hint="'Minimum '.Money::euros($minimumCents)" />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">

            <div class="card p-6">
                <h2 class="font-display text-lg font-bold text-ink-50">Demander un retrait</h2>

                @if ($clipper->hasPayoutDestination())
                    <div class="mt-4">
                        @livewire('request-payout')
                    </div>
                @else
                    {{-- Bloquer sans dire quoi faire, c'est un ticket au support. --}}
                    <div class="alert-warn mt-4">
                        <p class="font-semibold">Destination manquante</p>
                        <p class="mt-1 leading-relaxed">
                            Choisissez PayPal ou un virement bancaire avant de demander un retrait.
                        </p>
                        <a href="{{ route('payout-method.edit') }}" wire:navigate
                           class="mt-2 inline-block font-semibold underline underline-offset-2">
                            Renseigner mon moyen de paiement
                        </a>
                    </div>
                @endif
            </div>

            <div class="card lg:col-span-2">
                <div class="border-b border-ink-700 px-6 py-4">
                    <h2 class="font-display text-lg font-bold text-ink-50">Mes retraits</h2>
                </div>

                @if ($payouts->isEmpty())
                    <p class="px-6 py-10 text-center text-sm text-ink-300">Aucun retrait demandé.</p>
                @else
                    <ul class="divide-y divide-ink-700">
                        @foreach ($payouts as $payout)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                                <div>
                                    <p class="font-display font-bold tabular text-ink-50">
                                        {{ Money::euros($payout->amount_cents) }}
                                    </p>
                                    <p class="mt-0.5 text-sm text-ink-400">
                                        Demandé le {{ $payout->requested_at?->format('d/m/Y') }}
                                        @if ($payout->processed_at)
                                            · versé le {{ $payout->processed_at->format('d/m/Y') }}
                                        @endif
                                    </p>
                                    @if ($payout->failure_reason)
                                        <p class="mt-1 text-sm text-red-400">{{ $payout->failure_reason }}</p>
                                    @endif
                                </div>

                                <span @class([
                                    'chip-ok' => $payout->status->value === 'paid',
                                    'chip-wait' => in_array($payout->status->value, ['requested', 'approved', 'processing']),
                                    'chip-danger' => $payout->status->value === 'failed',
                                    'chip-neutral' => $payout->status->value === 'cancelled',
                                ])>{{ $payout->status->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="border-b border-ink-700 px-6 py-4">
                <h2 class="font-display text-lg font-bold text-ink-50">Historique des crédits</h2>
                <p class="mt-0.5 text-sm text-ink-400">Chaque ligne correspond à des vues rémunérées.</p>
            </div>

            @if ($transactions->isEmpty())
                <p class="px-6 py-10 text-center text-sm text-ink-300">Aucun crédit pour le moment.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs uppercase tracking-wide text-ink-400">
                                <th class="px-6 py-3 text-left font-semibold">Date</th>
                                <th class="px-6 py-3 text-left font-semibold">Campagne</th>
                                <th class="px-6 py-3 text-right font-semibold">Vues</th>
                                <th class="px-6 py-3 text-right font-semibold">Montant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-700">
                            @foreach ($transactions as $transaction)
                                <tr>
                                    <td class="px-6 py-3 text-ink-400">{{ $transaction->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-6 py-3 text-ink-100">{{ $transaction->campaign?->title }}</td>
                                    <td class="px-6 py-3 text-right tabular text-ink-300">{{ Money::views($transaction->views_delta) }}</td>
                                    <td @class([
                                        'px-6 py-3 text-right font-semibold tabular',
                                        'text-red-400' => $transaction->amount_cents < 0,
                                        'text-ink-50' => $transaction->amount_cents >= 0,
                                    ])>{{ Money::euros($transaction->amount_cents) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
