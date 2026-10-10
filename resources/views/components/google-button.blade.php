@props(['role' => null, 'label' => 'Continuer avec Google'])

@if (filled(config('services.google.client_id')))
    <div class="space-y-4">
        <a href="{{ route('google.redirect', array_filter(['profil' => $role])) }}"
           class="flex w-full items-center justify-center gap-3 rounded-xl border border-ink-700 bg-ink-800
                  px-4 py-3 font-semibold text-ink-50 transition
                  hover:border-ink-600 hover:bg-ink-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
            {{-- Logo officiel en SVG inline : un appel réseau vers Google pour
                 une image de 20 px ralentirait la page de connexion. --}}
            <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.2-.1-2.3-.4-3.5z"/>
                <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.6 39.6 16.2 44 24 44z"/>
                <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C36.9 40.2 44 35 44 24c0-1.2-.1-2.3-.4-3.5z"/>
            </svg>
            {{ $label }}
        </a>

        <div class="flex items-center gap-3">
            <span class="h-px flex-1 bg-ink-700"></span>
            <span class="text-xs uppercase tracking-wide text-ink-500">ou</span>
            <span class="h-px flex-1 bg-ink-700"></span>
        </div>
    </div>
@endif
