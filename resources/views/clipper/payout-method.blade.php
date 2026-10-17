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

        @php $business = old('legal_status', $user->legalStatus()->value) === \App\Enums\LegalStatus::Business->value; @endphp

        <div class="card p-6 sm:p-8" id="statut-legal" x-data="{ business: @js($business) }">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-display text-lg font-bold text-ink-50">Statut légal</h2>
                    <p class="mt-1 text-sm leading-relaxed text-ink-300">
                        Gagner de l'argent régulièrement en ligne est une activité déclarable.
                        Sans SIRET vérifié, vos retraits sont plafonnés sur l'année civile.
                    </p>
                </div>

                @if ($user->isVerifiedBusiness())
                    <span class="chip-ok">SIRET vérifié</span>
                @elseif ($user->hasPendingSiret())
                    <span class="chip-wait">SIRET en cours de vérification</span>
                @elseif ($user->siret_rejection_reason)
                    <span class="chip-danger">SIRET refusé</span>
                @else
                    <span class="chip-neutral">{{ $user->legalStatus()->label() }}</span>
                @endif
            </div>

            @if ($user->siret_rejection_reason && ! $user->isVerifiedBusiness())
                <div class="alert-danger mt-5">
                    <p class="font-semibold">Votre SIRET n'a pas pu être vérifié</p>
                    <p class="mt-1">{{ $user->siret_rejection_reason }}</p>
                </div>
            @endif

            @if ($allowance)
                <div class="mt-6 rounded-xl border border-ink-700 bg-ink-900 p-4">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <span class="text-ink-300">Retiré cette année</span>
                        <span class="tabular text-ink-50">
                            <span class="font-semibold">{{ \App\Support\Money::euros($allowance->usedCents) }}</span>
                            <span class="text-ink-400">/ {{ \App\Support\Money::euros($allowance->maxCents) }}</span>
                        </span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-ink-700">
                        <div @class([
                            'h-full rounded-full',
                            'bg-brand-500' => $allowance->percent() < 80,
                            'bg-amber-500' => $allowance->percent() >= 80,
                        ]) style="width: {{ $allowance->percent() }}%"></div>
                    </div>
                    <p class="mt-2 text-xs text-ink-400">
                        {{ $allowance->usedPayouts }} / {{ $allowance->maxPayouts }} retraits utilisés · remis à zéro le 1er janvier.
                    </p>
                </div>
            @endif

            <form method="POST" action="{{ route('legal-status.update') }}" class="mt-6 space-y-5">
                @csrf
                @method('PATCH')

                <fieldset class="grid gap-3 sm:grid-cols-2">
                    <legend class="sr-only">Votre statut</legend>
                    @foreach (\App\Enums\LegalStatus::cases() as $status)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-ink-700 bg-ink-900 p-4 has-[:checked]:border-brand-500">
                            <input type="radio" name="legal_status" value="{{ $status->value }}"
                                   class="mt-0.5 border-ink-600 bg-ink-950 text-brand-500 focus:ring-brand-500"
                                   x-on:change="business = $event.target.value === '{{ \App\Enums\LegalStatus::Business->value }}'"
                                   @checked(old('legal_status', $user->legalStatus()->value) === $status->value)>
                            <span>
                                <span class="block text-sm font-semibold text-ink-50">{{ $status->label() }}</span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-ink-400">
                                    {{ $status === \App\Enums\LegalStatus::Individual
                                        ? 'Retraits plafonnés sur l’année.'
                                        : 'Plafond levé une fois le SIRET vérifié.' }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </fieldset>

                <div x-show="business" x-cloak>
                    <label for="siret" class="label">Numéro SIRET</label>
                    <input id="siret" name="siret" type="text" inputmode="numeric" autocomplete="off"
                           class="field mt-1.5 tabular" placeholder="123 456 789 00012"
                           value="{{ old('siret', $user->siret) }}">
                    <p class="hint">14 chiffres, sur votre avis de situation INSEE ou sur annuaire-entreprises.data.gouv.fr.</p>
                    @foreach ($errors->legalStatus->get('siret') as $message)
                        <p class="mt-1.5 text-sm text-rose-300">{{ $message }}</p>
                    @endforeach
                </div>

                <div class="border-t border-ink-700 pt-5">
                    <x-primary-button class="w-full sm:w-auto">Enregistrer mon statut</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
