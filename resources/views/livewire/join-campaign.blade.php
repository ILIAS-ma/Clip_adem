<div>
    @if ($this->accounts->isEmpty())
        <div class="rounded-xl bg-ink-800 p-4 text-sm text-ink-200">
            {{-- Distinguer « aucun compte » de « aucun compte compatible » évite
                 de laisser le clippeur chercher ce qu'il a mal fait. --}}
            @if ($campaign->participations()->where('user_id', auth()->id())->exists())
                {{-- Le cas le plus courant : le seul compte est déjà engagé. Ce
                     n'est pas une erreur, il n'y a simplement rien à ajouter. --}}
                <p>Tous vos comptes compatibles participent déjà à cette campagne.
                   Pour en engager un autre, <a href="{{ route('accounts.index') }}" class="text-brand-400 underline underline-offset-2">liez un nouveau compte</a>.</p>
            @elseif (auth()->user()->socialAccounts()->exists())
                <p>Aucun de vos comptes n'est sur une plateforme acceptée par cette campagne.
                   <a href="{{ route('accounts.index') }}" class="text-brand-400 underline underline-offset-2">Liez un compte compatible</a>.</p>
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
                <x-select id="socialAccountId" wire:model="socialAccountId" class="text-sm">
                    @foreach ($this->accounts as $account)
                        <option value="{{ $account->id }}">
                            {{ $account->platform->label() }}{{ $account->handle ? ' · @'.$account->handle : '' }}
                        </option>
                    @endforeach
                </x-select>
                <x-input-error class="mt-2" :messages="$errors->get('socialAccountId')" />
            </div>

            <button type="submit" class="btn-brand w-full" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="join">Rejoindre la campagne</span>
                <span wire:loading wire:target="join" class="inline-flex items-center gap-2">
                    <x-loader size="sm" tone="dark" label="Envoi en cours" />
                    Envoi…
                </span>
            </button>
        </form>
    @endif
</div>
