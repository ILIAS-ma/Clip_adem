<section>
    <header>
        <h2 class="font-display text-lg font-bold text-ink-50">Photo de profil</h2>
        <p class="mt-1 text-sm text-ink-300">Visible dans le menu, le classement et les notifications.</p>
    </header>

    <div class="mt-6 flex items-center gap-6">
        <x-avatar-badge :user="$user" class="!h-16 !w-16 !text-lg" />

        <div class="flex-1">
            <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp"
                       class="block w-full max-w-xs text-sm text-ink-300 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-700 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-ink-100 hover:file:bg-ink-600">
                <x-primary-button>Envoyer</x-primary-button>

                @if (session('status') === 'avatar-updated')
                    <p x-data="{ show: true }" x-show="show" x-transition
                       x-init="setTimeout(() => show = false, 2500)"
                       class="text-sm font-medium text-brand-400">Enregistrée.</p>
                @elseif (session('status') === 'avatar-removed')
                    <p x-data="{ show: true }" x-show="show" x-transition
                       x-init="setTimeout(() => show = false, 2500)"
                       class="text-sm font-medium text-ink-300">Retirée.</p>
                @endif
            </form>

            <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
            <p class="hint">JPG, PNG ou WEBP. 2 Mo maximum.</p>
        </div>

        @if ($user->avatar_url)
            <form method="POST" action="{{ route('profile.avatar.destroy') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-medium text-ink-400 underline-offset-2 hover:text-red-400 hover:underline">
                    Retirer
                </button>
            </form>
        @endif
    </div>
</section>
