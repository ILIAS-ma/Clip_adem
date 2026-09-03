<div>
    @if ($this->accounts->isEmpty())
        <div class="rounded-xl bg-ink-800 p-4 text-sm text-ink-200">
            {{-- Distinguer « aucun compte » de « aucun compte compatible » évite
                 de laisser le clippeur chercher ce qu'il a mal fait. --}}
            @if (auth()->user()->socialAccounts()->exists())
                <p>Aucun de vos comptes n'est compatible avec les plateformes de cette campagne,
                   ou vous les avez déjà tous engagés.</p>
            @else
                <p class="font-semibold text-ink-50">Liez d'abord un compte</p>
                <p class="mt-1">Vos vues doivent pouvoir être relevées pour être payées.</p>
                <a href="{{ route('accounts.index') }}" class="btn-brand mt-3 w-full">Lier un compte</a>
            @endif
        </div>
    @else
        <form wire:submit="join" class="space-y-3">
            <div>
                <label for="socialAccountId" class="text-xs font-semibold uppercase tracking-wide text-ink-400">
                    Compte utilisé pour publier
                </label>
                <select id="socialAccountId" wire:model="socialAccountId" class="field mt-1.5 text-sm">
                    @foreach ($this->accounts as $account)
                        <option value="{{ $account->id }}">
                            {{ $account->platform->label() }}{{ $account->handle ? ' · @'.$account->handle : '' }}
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('socialAccountId')" />
            </div>

            <button type="submit" class="btn-brand w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="join">Rejoindre la campagne</span>
                <span wire:loading wire:target="join">Envoi…</span>
            </button>
        </form>
    @endif
</div>
