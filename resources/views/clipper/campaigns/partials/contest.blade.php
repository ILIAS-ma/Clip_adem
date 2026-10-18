@php
    use App\Support\Money;
    $allRewards = $campaign->allRewards();
@endphp

<section class="space-y-4" id="classement">
    {{-- L'en-tête de la campagne annonce déjà la récompense : ici, juste le
         titre de la section et la règle du jeu, en une ligne. --}}
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="font-display text-xl font-bold text-ink-50">Classements</h2>
        <p class="text-sm text-ink-300">
            Pas de paiement aux vues ·
            @if ($campaign->prizePoolCents() > 0)
                <span class="font-semibold text-ink-50">{{ Money::wholeEuros($campaign->prizePoolCents()) }} à gagner</span>{{ $campaign->hasItemRewards() ? ', plus des lots' : '' }}
            @elseif ($allRewards !== [])
                <span class="font-semibold text-ink-50">{{ count($allRewards) }} lot{{ count($allRewards) > 1 ? 's' : '' }} à gagner</span>
            @else
                pour la gloire
            @endif
        </p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($boards as $board)
            @include('clipper.campaigns.partials.contest-board', ['campaign' => $campaign] + $board)
        @endforeach
    </div>

    @if ($campaign->prizes_paid_at)
        <p class="text-xs text-ink-400">Récompenses attribuées le {{ $campaign->prizes_paid_at->format('d/m/Y') }}.</p>
    @endif
</section>
