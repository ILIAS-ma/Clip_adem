@php use App\Support\Money; @endphp

<div>
    @if ($available < $minimum)
        <div class="rounded-xl bg-ink-50 p-4 text-sm text-ink-600">
            Il vous faut au moins <strong class="text-ink-900">{{ Money::euros($minimum) }}</strong>
            de solde pour demander un retrait. Vous en avez {{ Money::euros($available) }}.
        </div>
    @else
        <form wire:submit="request" class="space-y-3">
            <div>
                <label for="payout-amount" class="text-xs font-semibold uppercase tracking-wide text-ink-400">
                    Montant
                </label>
                <div class="relative mt-1.5">
                    <input id="payout-amount" type="number" step="0.01" min="0" wire:model="amount"
                           class="field pe-8 text-sm tabular">
                    <span class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3 text-sm text-ink-300">€</span>
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('amount')" />
            </div>

            <button type="submit" class="btn-brand w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="request">Demander le retrait</span>
                <span wire:loading wire:target="request">Envoi…</span>
            </button>

            <p class="hint">
                Les petits montants sont validés automatiquement. Au-delà, un administrateur vérifie
                la demande avant le virement.
            </p>
        </form>
    @endif
</div>
