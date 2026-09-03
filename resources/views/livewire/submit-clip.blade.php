<div class="mt-4">
    <form wire:submit="submit" class="space-y-3">
        <div>
            <label for="clip-url" class="sr-only">Lien de la publication</label>
            <input id="clip-url" type="url" wire:model="url"
                   placeholder="https://www.tiktok.com/@votrepseudo/video/…"
                   class="field text-sm">
            <p class="hint">
                Adresse complète de la publication. Les liens raccourcis ne peuvent pas être vérifiés.
            </p>
            <x-input-error class="mt-2" :messages="$errors->get('url')" />
        </div>

        <button type="submit" class="btn-primary" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="submit">Soumettre le clip</span>
            <span wire:loading wire:target="submit">Vérification…</span>
        </button>
    </form>
</div>
