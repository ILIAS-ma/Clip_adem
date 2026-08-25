@php use App\Support\Money; @endphp

<div>
    @if ($available < $minimum)
        <p class="rounded-md bg-gray-50 p-3 text-sm text-gray-600">
            Il vous faut au moins {{ Money::euros($minimum) }} de solde pour demander un retrait.
            Vous en avez {{ Money::euros($available) }}.
        </p>
    @else
        <form wire:submit="request" class="space-y-3">
            <div>
                <label for="payout-amount" class="block text-xs font-medium text-gray-700">Montant</label>
                <div class="relative mt-1">
                    <input id="payout-amount" type="number" step="0.01" min="0" wire:model="amount"
                           class="block w-full rounded-md border-gray-300 pe-8 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <span class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3 text-sm text-gray-400">€</span>
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('amount')" />
            </div>

            <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="request">Demander le retrait</span>
                <span wire:loading wire:target="request">Envoi…</span>
            </button>

            <p class="text-xs leading-relaxed text-gray-500">
                Les petits montants sont validés automatiquement. Au-delà, un administrateur vérifie
                la demande avant le virement.
            </p>
        </form>
    @endif
</div>
