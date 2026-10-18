@php
    use App\Support\Money;
    $allRewards = $campaign->allRewards();
@endphp

<div class="space-y-4" id="classement">
    {{-- En-tête commun aux deux tableaux : ce qu'il y a à gagner, et quand ça s'arrête. --}}
    <div class="card flex flex-wrap items-end justify-between gap-4 p-6">
        <div>
            <span class="chip bg-brand-500/15 text-brand-300">Classement</span>
            <p class="mt-3 font-display text-2xl font-bold tabular text-ink-50">
                @if ($campaign->prizePoolCents() > 0)
                    {{ Money::wholeEuros($campaign->prizePoolCents()) }} <span class="text-sm font-semibold text-ink-400">à gagner{{ $campaign->hasItemRewards() ? ', plus des lots' : '' }}</span>
                @elseif ($allRewards !== [])
                    {{ count($allRewards) }} lot{{ count($allRewards) > 1 ? 's' : '' }} <span class="text-sm font-semibold text-ink-400">à gagner</span>
                @else
                    Pour la gloire
                @endif
            </p>
            <p class="mt-1 text-sm leading-relaxed text-ink-300">
                Pas de paiement aux vues : deux classements en même temps, sur les vues et sur le nombre de clips validés.
            </p>
        </div>

        @if ($campaign->ends_at)
            <p class="text-sm text-ink-300">
                {{ $campaign->contestIsOver() ? 'Arrêté le' : 'Fin le' }}
                <span class="font-semibold text-ink-50">{{ $campaign->ends_at->format('d/m/Y à H:i') }}</span>
            </p>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($boards as $board)
            @include('clipper.campaigns.partials.contest-board', ['campaign' => $campaign] + $board)
        @endforeach
    </div>

    @if ($campaign->prizes_paid_at)
        <p class="text-xs text-ink-400">Récompenses attribuées le {{ $campaign->prizes_paid_at->format('d/m/Y') }}.</p>
    @endif
</div>
