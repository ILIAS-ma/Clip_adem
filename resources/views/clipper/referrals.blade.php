@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-2xl font-bold text-ink-50">Parrainage</h1>
        <p class="mt-1 text-ink-300">
            Invitez d'autres clippeurs. Vous touchez {{ rtrim(rtrim(number_format($ratePercent, 1, ',', ' '), '0'), ',') }} %
            de ce qu'ils gagnent — sans que cela leur coûte quoi que ce soit.
        </p>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        {{-- Le lien d'abord : c'est la seule chose que la plupart viennent
             chercher sur cette page. --}}
        <div class="card overflow-hidden">
            <div class="relative p-6 sm:p-8">
                <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-brand-500/10 blur-3xl"></div>

                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Votre lien</p>

                <div x-data="{ copied: false }" class="mt-3 flex flex-wrap items-center gap-3">
                    <code class="min-w-0 flex-1 truncate rounded-xl border border-ink-700 bg-ink-900 px-4 py-3 text-sm text-brand-300">
                        {{ $link }}
                    </code>

                    <button type="button"
                            @click="navigator.clipboard.writeText(@js($link)); copied = true; setTimeout(() => copied = false, 2000)"
                            class="btn-primary shrink-0">
                        <span x-show="! copied">Copier</span>
                        <span x-show="copied" x-cloak>Copié</span>
                    </button>
                </div>

                <p class="hint mt-3">
                    Votre code : <span class="font-semibold tabular text-ink-100">{{ $code }}</span>.
                    Il peut aussi se saisir à la main à l'inscription.
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat label="Filleuls" :value="(string) $filleuls->count()"
                    :hint="$filleuls->count() > 0 ? 'inscrits grâce à vous' : 'personne pour l’instant'" />

            <x-stat label="Gagné en parrainage" :value="Money::euros($earnedCents)"
                    tone="money" highlight hint="Ajouté à votre solde" />

            <x-stat label="Commission" :value="rtrim(rtrim(number_format($ratePercent, 1, ',', ' '), '0'), ',').' %'"
                    hint="De leurs gains" />
        </div>

        {{-- Dire d'où vient l'argent évite la question qui vient toujours :
             « est-ce que ça enlève quelque chose à mon filleul ? » --}}
        <div class="alert-ok">
            <p class="font-semibold">Votre filleul ne perd rien</p>
            <p class="mt-1 leading-relaxed">
                La commission est payée par iPlant Clip, pas prélevée sur ses gains ni sur le budget
                de la campagne. Il touche exactement ce que ses vues valent.
            </p>
        </div>

        @if ($filleuls->isEmpty())
            <div class="card px-6 py-14 text-center">
                <p class="font-display text-base font-bold text-ink-50">Aucun filleul pour l'instant</p>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-ink-300">
                    Partagez votre lien dans votre bio, en story ou en commentaire. Chaque personne
                    qui s'inscrit avec est rattachée à vous définitivement.
                </p>
            </div>
        @else
            <div class="card">
                <div class="border-b border-ink-700 px-6 py-4">
                    <h2 class="font-display text-lg font-bold text-ink-50">Mes filleuls</h2>
                </div>

                <ul class="divide-y divide-ink-700">
                    @foreach ($filleuls as $filleul)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                            <div class="min-w-0">
                                {{-- Le pseudo, jamais l'e-mail : un parrain n'a
                                     pas à récupérer les contacts de ses filleuls. --}}
                                <p class="truncate font-semibold text-ink-50">{{ $filleul->displayName() }}</p>
                                <p class="mt-0.5 text-sm text-ink-400">
                                    inscrit le {{ $filleul->referred_at?->format('d/m/Y') }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="font-display font-bold tabular text-brand-400">
                                    {{ Money::euros((int) ($perReferred[$filleul->id] ?? 0)) }}
                                </p>
                                <p class="text-xs text-ink-400">vous ont rapporté</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($commissions->isNotEmpty())
            <div class="card">
                <div class="border-b border-ink-700 px-6 py-4">
                    <h2 class="font-display text-lg font-bold text-ink-50">Dernières commissions</h2>
                </div>

                <ul class="divide-y divide-ink-700">
                    @foreach ($commissions as $commission)
                        <li class="flex items-center justify-between gap-3 px-6 py-3 text-sm">
                            <span class="text-ink-300">
                                {{ $commission->referred?->displayName() ?? 'Filleul supprimé' }}
                                <span class="text-ink-500">· {{ $commission->created_at->format('d/m/Y') }}</span>
                            </span>
                            <span class="font-semibold tabular {{ $commission->amount_cents < 0 ? 'text-red-400' : 'text-brand-400' }}">
                                {{ $commission->amount_cents < 0 ? '' : '+' }}{{ Money::euros($commission->amount_cents) }}
                            </span>
                        </li>
                    @endforeach
                </ul>

                <p class="border-t border-ink-700 px-6 py-3 text-xs text-ink-500">
                    Une ligne en rouge est une reprise : les vues d'un filleul ont été invalidées.
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
