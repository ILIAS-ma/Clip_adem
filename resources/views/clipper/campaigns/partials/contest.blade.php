{{--
    Les deux classements d'une campagne — vues et nombre de clips — l'un à la
    fois, derrière un sélecteur : côte à côte, ils doublaient la longueur de la
    page sur téléphone.
--}}
<section class="space-y-3" id="classement" x-data="{ board: '{{ $boards[0]['metric']->value ?? 'views' }}' }">
    @if (count($boards) > 1)
        <div class="flex gap-2">
            @foreach ($boards as $board)
                <button type="button" @click="board = '{{ $board['metric']->value }}'"
                        :class="board === '{{ $board['metric']->value }}' ? 'bg-brand-500 text-white' : 'bg-ink-900 text-ink-300 ring-1 ring-ink-800 hover:text-ink-50'"
                        class="rounded-full px-4 py-1.5 text-sm font-semibold transition">{{ $board['metric']->title() }}</button>
            @endforeach
        </div>
    @endif

    @foreach ($boards as $board)
        <div x-show="board === '{{ $board['metric']->value }}'" @unless ($loop->first) x-cloak @endunless>
            @include('clipper.campaigns.partials.contest-board', ['campaign' => $campaign] + $board)
        </div>
    @endforeach

    <p class="px-1 text-xs text-ink-400">
        Pas de paiement aux vues ·
        @if ($campaign->prizePoolCents() > 0)
            {{ \App\Support\Money::wholeEuros($campaign->prizePoolCents()) }} à gagner{{ $campaign->hasItemRewards() ? ', plus des lots' : '' }}
        @elseif ($campaign->allRewards() !== [])
            {{ count($campaign->allRewards()) }} lot{{ count($campaign->allRewards()) > 1 ? 's' : '' }} à gagner
        @else
            pour la gloire
        @endif
        @if ($campaign->prizes_paid_at)
            · récompenses attribuées le {{ $campaign->prizes_paid_at->format('d/m/Y') }}
        @endif
    </p>
</section>
