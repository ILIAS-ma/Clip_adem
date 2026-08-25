<div class="mt-3">
    <form wire:submit="submit" class="space-y-3">
        <div>
            <label for="clip-url" class="sr-only">Lien de la publication</label>
            <input id="clip-url" type="url" wire:model="url"
                   placeholder="https://www.tiktok.com/@votrepseudo/video/…"
                   class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
            <p class="mt-1 text-xs text-gray-500">
                Collez l'adresse complète de votre publication. Les liens raccourcis ne peuvent pas être vérifiés.
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('url')" />
        </div>

        <button type="submit"
                class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:opacity-50"
                wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="submit">Soumettre le clip</span>
            <span wire:loading wire:target="submit">Vérification…</span>
        </button>
    </form>
</div>
