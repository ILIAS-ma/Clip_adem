<section x-data="{ open: false }">
    <header>
        <h2 class="font-display text-lg font-bold text-ink-900">Supprimer mon compte</h2>
        <p class="mt-1 text-sm leading-relaxed text-ink-500">
            Votre compte disparaît de la plateforme et vous ne pouvez plus vous connecter.
            {{-- Soft delete : clips, versements et grand livre référencent ce
                 compte, la comptabilité doit rester justifiable. --}}
            Vos clips et versements passés sont conservés pour la comptabilité.
            Un solde non retiré serait perdu : demandez-le avant.
        </p>
    </header>

    <button type="button" @click="open = true" class="btn mt-5 bg-red-600 text-white hover:bg-red-700">
        Supprimer mon compte
    </button>

    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-ink-900/50 p-4"
         @keydown.escape.window="open = false">
        <div class="card w-full max-w-md p-6" @click.outside="open = false">
            <h3 class="font-display text-lg font-bold text-ink-900">Confirmer la suppression</h3>
            <p class="mt-2 text-sm text-ink-500">Saisissez votre mot de passe pour confirmer.</p>

            <form method="post" action="{{ route('profile.destroy') }}" class="mt-5 space-y-4">
                @csrf
                @method('delete')

                <div>
                    <x-input-label for="password" value="Mot de passe" class="sr-only" />
                    <x-text-input id="password" name="password" type="password" placeholder="Mot de passe" />
                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" @click="open = false" class="btn-ghost">Annuler</button>
                    <button type="submit" class="btn bg-red-600 text-white hover:bg-red-700">
                        Supprimer définitivement
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
