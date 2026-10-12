{{--
    Le pied de page commun : espaces connectés et pages légales.

    Les mentions légales doivent être atteignables depuis toutes les pages ;
    les espaces connectés n'avaient aucun pied de page, donc aucun lien vers
    elles, ni vers les conditions acceptées à l'inscription.
--}}
<footer {{ $attributes->merge(['class' => 'border-t border-ink-800']) }}>
    <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-6 text-xs text-ink-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
            <x-brand-mark class="opacity-80" />
            <span>© {{ date('Y') }} {{ config('app.name') }} — Payé pour vos clips</span>
        </div>

        <nav class="flex flex-wrap gap-x-5 gap-y-2" aria-label="Liens légaux">
            @auth
                <a href="{{ route('messages.index') }}" class="hover:text-ink-200">Contacter l'équipe</a>
            @endauth
            <a href="{{ route('legal.notice') }}" class="hover:text-ink-200">Mentions légales</a>
            <a href="{{ route('legal.terms') }}" class="hover:text-ink-200">Conditions d'utilisation</a>
            <a href="{{ route('legal.privacy') }}" class="hover:text-ink-200">Confidentialité</a>
        </nav>
    </div>
</footer>
