<x-app-layout>
    <div class="mx-auto flex max-w-xl flex-col items-center px-4 py-20 text-center sm:px-6">
        <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-500/15 text-brand-400 ring-1 ring-brand-500/30">
            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </span>

        <h1 class="mt-6 font-display text-2xl font-bold text-ink-50">Echo Studio est en pause</h1>
        <p class="mt-3 leading-relaxed text-ink-300">
            On le prépare pour qu'il marche encore mieux : bientôt, chacun pourra y brancher son propre compte.
            En attendant, montez vos clips avec vos outils habituels et soumettez-les sur la campagne comme d'habitude.
        </p>

        <div class="mt-8 flex flex-col gap-2 sm:flex-row">
            <a href="{{ route('campaigns.index') }}" wire:navigate class="btn-primary">Voir les campagnes</a>
            <a href="{{ route('messages.index') }}" wire:navigate class="btn-ghost">Une question ? Écrivez-nous</a>
        </div>
    </div>
</x-app-layout>
