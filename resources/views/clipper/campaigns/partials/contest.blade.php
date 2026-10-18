@php
    use App\Support\Money;
    $prizes = $campaign->prizes();
@endphp

<div class="card overflow-hidden" id="classement">
    <div class="border-b border-ink-700 p-6">
        <span class="chip bg-brand-500/15 text-brand-300">Classement</span>
        <p class="mt-3 font-display text-2xl font-bold tabular text-ink-50">
            {{ Money::wholeEuros($campaign->prizePoolCents()) }} <span class="text-sm font-semibold text-ink-400">à gagner</span>
        </p>
        <p class="mt-1 text-sm leading-relaxed text-ink-300">
            Pas de paiement aux vues : les clippeurs sont classés sur les vues de leurs clips validés.
            @if ($campaign->ends_at)
                {{ $campaign->contestIsOver() ? 'Classement arrêté le' : 'Fin du classement le' }}
                <span class="font-semibold text-ink-100">{{ $campaign->ends_at->format('d/m/Y à H:i') }}</span>.
            @endif
        </p>

        <ol class="mt-4 space-y-1.5 text-sm">
            @foreach ($prizes as $index => $cents)
                <li class="flex justify-between gap-2">
                    <span class="text-ink-300">{{ $index === 0 ? '1re' : ($index + 1).'e' }} place</span>
                    <span class="font-semibold tabular text-brand-400">{{ Money::euros($cents) }}</span>
                </li>
            @endforeach
        </ol>
    </div>

    @if ($myStanding)
        <div class="border-b border-ink-700 bg-brand-500/10 px-6 py-3 text-sm">
            Vous êtes <span class="font-bold text-ink-50">{{ $myStanding->rank === 1 ? '1er' : $myStanding->rank.'e' }}</span>
            avec <span class="font-semibold tabular text-ink-50">{{ Money::views($myStanding->views) }}</span> vues.
            @if (isset($prizes[$myStanding->rank - 1]))
                <span class="text-brand-400">Place payée {{ Money::euros($prizes[$myStanding->rank - 1]) }}.</span>
            @endif
        </div>
    @endif

    @if ($standings->isEmpty())
        <p class="px-6 py-8 text-center text-sm text-ink-300">Personne n'a encore de vues validées. La 1re place est libre.</p>
    @else
        <ol class="divide-y divide-ink-700">
            @foreach ($standings as $row)
                <li @class([
                    'flex items-center gap-3 px-6 py-3',
                    'bg-brand-500/10' => $row->user_id === auth()->id(),
                ])>
                    <span class="w-6 flex-none text-sm font-semibold tabular text-ink-400">{{ $row->rank }}</span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink-50">{{ $row->name }}</span>
                    <span class="flex-none text-sm font-semibold tabular text-ink-100">{{ Money::compactViews($row->views) }}</span>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($campaign->prizes_paid_at)
        <p class="border-t border-ink-700 px-6 py-3 text-xs text-ink-400">Prix versés le {{ $campaign->prizes_paid_at->format('d/m/Y') }}.</p>
    @endif
</div>
