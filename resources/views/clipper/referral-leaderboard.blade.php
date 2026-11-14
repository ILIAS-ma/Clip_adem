@php use App\Services\Referrals\ReferrerLeaderboard; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink-50">Classement des parrains</h1>
                <p class="mt-1 text-sm text-ink-300 sm:text-base">
                    Ceux qui amènent le plus de clippeurs qui participent vraiment.
                </p>
            </div>
            <a href="{{ route('referrals.index') }}" wire:navigate class="btn-ghost text-sm">← Mon parrainage</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-4 px-4 py-5 sm:space-y-6 sm:px-6 sm:py-8 lg:px-8">

        {{-- La récompense, quand l'équipe en annonce une. Le classement lui-même ne verse rien. --}}
        @if (filled($prize))
            <div class="rounded-2xl border border-brand-500/40 bg-brand-500/5 p-4 sm:p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-400">À gagner</p>
                <p class="mt-1 font-display text-lg font-bold text-ink-50">{{ $prize }}</p>
            </div>
        @endif

        {{-- La période : un mois qui repart de zéro laisse sa chance à tout le monde, le total garde la mémoire. --}}
        <div class="flex gap-2">
            @foreach ([ReferrerLeaderboard::MONTH => 'Ce mois-ci', ReferrerLeaderboard::ALL => 'Depuis le début'] as $key => $label)
                <a href="{{ route('referrals.leaderboard', ['periode' => $key]) }}" wire:navigate
                   @class(['chip', 'chip-ok' => $period === $key, 'chip-neutral' => $period !== $key])>{{ $label }}</a>
            @endforeach
        </div>

        {{-- Où l'on en est, dit en clair, y compris quand on n'est pas encore classé. --}}
        <div class="card p-5 sm:p-6">
            @if ($mine->rank)
                <p class="text-sm text-ink-300">Votre place</p>
                <p class="mt-1 font-display text-3xl font-extrabold tabular text-brand-400">{{ $mine->rank === 1 ? '1er' : $mine->rank.'e' }}</p>
                <p class="mt-1 text-sm text-ink-300">
                    {{ $mine->active }} filleul{{ $mine->active > 1 ? 's' : '' }} actif{{ $mine->active > 1 ? 's' : '' }}
                    sur {{ $mine->referred }} inscrit{{ $mine->referred > 1 ? 's' : '' }}.
                </p>
            @elseif ($mine->referred > 0)
                <p class="font-display text-lg font-bold text-ink-50">Pas encore classé</p>
                <p class="mt-1 text-sm leading-relaxed text-ink-300">
                    Vous avez {{ $mine->referred }} filleul{{ $mine->referred > 1 ? 's' : '' }} inscrit{{ $mine->referred > 1 ? 's' : '' }}, mais aucun n'est encore actif.
                    Un filleul compte quand il est <strong class="text-ink-100">vraiment actif</strong> :
                    {{ $rule['clips'] }} clips validés, {{ number_format($rule['views'], 0, ',', ' ') }} vues
                    et de l'activité sur {{ $rule['days'] }} jours différents.
                </p>
            @else
                <p class="font-display text-lg font-bold text-ink-50">Vous n'avez pas encore de filleul</p>
                <p class="mt-1 text-sm leading-relaxed text-ink-300">
                    Partagez votre lien : chaque ami qui devient actif vous fait monter
                    ({{ $rule['clips'] }} clips validés et {{ number_format($rule['views'], 0, ',', ' ') }} vues).
                </p>
            @endif

            <div x-data="{ copied: false }" class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button"
                        @click="navigator.clipboard.writeText(@js($link)); copied = true; setTimeout(() => copied = false, 2000)"
                        class="btn-brand px-4 py-2 text-sm">
                    <span x-show="! copied">Copier mon lien de parrainage</span>
                    <span x-show="copied" x-cloak>Lien copié</span>
                </button>
            </div>
        </div>

        <div class="card">
            <div class="border-b border-ink-700 px-5 py-4 sm:px-6">
                <h2 class="font-display text-lg font-bold text-ink-50">Le classement</h2>
                <p class="mt-0.5 text-sm text-ink-400">
                    {{ $period === ReferrerLeaderboard::MONTH ? 'Les filleuls inscrits depuis le début du mois.' : 'Tous les filleuls depuis le début.' }}
                    Seuls comptent les filleuls actifs : {{ $rule['clips'] }} clips validés,
                    {{ number_format($rule['views'], 0, ',', ' ') }} vues, sur {{ $rule['days'] }} jours différents.
                </p>
            </div>

            @if ($rows->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="font-display text-base font-bold text-ink-50">Personne n'est encore classé</p>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-ink-300">
                        {{ $period === ReferrerLeaderboard::MONTH ? 'Le mois commence : la première place est à prendre.' : 'La première place est à prendre.' }}
                    </p>
                </div>
            @else
                <ol class="divide-y divide-ink-800">
                    @foreach ($rows as $row)
                        <li @class(['flex items-center gap-3 px-5 py-3.5 sm:px-6', 'bg-brand-500/5' => $row->user_id === $viewerId])>
                            <span @class([
                                'flex h-8 w-8 flex-none items-center justify-center rounded-full text-sm font-bold tabular',
                                'bg-amber-400 text-ink-950' => $row->rank === 1,
                                'bg-ink-200 text-ink-950' => $row->rank === 2,
                                'bg-amber-700 text-white' => $row->rank === 3,
                                'bg-ink-800 text-ink-300' => $row->rank > 3,
                            ])>{{ $row->rank }}</span>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-semibold text-ink-50">
                                    {{ $row->name }}
                                    @if ($row->user_id === $viewerId)<span class="ms-1 text-xs font-medium text-brand-400">(vous)</span>@endif
                                </span>
                                <span class="block text-xs text-ink-400">{{ $row->referred }} inscrit{{ $row->referred > 1 ? 's' : '' }}</span>
                            </span>

                            <span class="flex-none text-right">
                                <span class="block font-display text-lg font-bold tabular text-ink-50">{{ $row->active }}</span>
                                <span class="block text-[11px] text-ink-400">actif{{ $row->active > 1 ? 's' : '' }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>

                @if ($ranked > $rows->count())
                    <p class="border-t border-ink-800 px-5 py-3 text-xs text-ink-400 sm:px-6">
                        Les {{ $rows->count() }} premiers sur {{ $ranked }} parrains classés.
                    </p>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
