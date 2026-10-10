<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink-50">Moyen de paiement</h1>
                <p class="mt-1 text-ink-300">Où vos gains sont versés quand vous demandez un retrait.</p>
            </div>

            <a href="{{ route('earnings.index') }}" wire:navigate class="btn-ghost">← Mes revenus</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        @if (session('status'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        @if ($user->hasPayoutDestination())
            <div class="card flex flex-wrap items-baseline justify-between gap-3 p-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Destination actuelle</p>
                    <p class="mt-1 font-display text-lg font-bold tabular text-ink-50">
                        {{ $user->payoutDestinationLabel() }}
                    </p>
                </div>
                <span class="chip-ok">{{ $user->payoutMethod()->label() }}</span>
            </div>
        @else
            <div class="alert-warn">
                <p class="font-semibold">Aucune destination enregistrée</p>
                <p class="mt-1 leading-relaxed">
                    Vos gains continuent de s'accumuler, mais aucun retrait ne pourra partir tant
                    que ce formulaire n'est pas rempli.
                </p>
            </div>
        @endif

        <div class="card p-6 sm:p-8">
            <form method="POST" action="{{ route('payout-method.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <x-payout-method-fields :user="$user" />

                <div class="border-t border-ink-700 pt-6">
                    <x-primary-button class="w-full sm:w-auto">Enregistrer</x-primary-button>
                </div>
            </form>
        </div>

        <p class="text-xs leading-relaxed text-ink-500">
            Changer de destination n'affecte pas les retraits déjà demandés : chacun garde celle
            qui était enregistrée au moment de la demande.
        </p>
    </div>
</x-app-layout>
