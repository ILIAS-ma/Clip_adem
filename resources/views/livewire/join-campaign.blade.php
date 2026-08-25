<div>
    @if ($this->accounts->isEmpty())
        <p class="rounded-md bg-gray-50 p-3 text-sm text-gray-600">
            {{-- Distinguer « aucun compte » de « aucun compte compatible » évite
                 de laisser le clippeur chercher ce qu'il a mal fait. --}}
            @if (auth()->user()->socialAccounts()->exists())
                Aucun de vos comptes n'est compatible avec les plateformes ouvertes sur cette campagne,
                ou vous les avez déjà tous engagés.
            @else
                Liez un compte TikTok, YouTube ou Instagram pour rejoindre cette campagne.
            @endif
        </p>
    @else
        <form wire:submit="join" class="space-y-3">
            <div>
                <label for="socialAccountId" class="block text-xs font-medium text-gray-700">
                    Compte utilisé pour publier
                </label>
                <select id="socialAccountId" wire:model="socialAccountId"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    @foreach ($this->accounts as $account)
                        <option value="{{ $account->id }}">
                            {{ $account->platform->label() }}{{ $account->handle ? ' · @'.$account->handle : '' }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('socialAccountId')" />
            </div>

            <button type="submit"
                    class="inline-flex w-full items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50"
                    wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="join">Rejoindre la campagne</span>
                <span wire:loading wire:target="join">Envoi…</span>
            </button>
        </form>
    @endif
</div>
