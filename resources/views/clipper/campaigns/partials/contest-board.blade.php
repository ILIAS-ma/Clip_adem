@php
    use App\Support\Money;
    /** @var \App\Enums\ContestMetric $metric */
    $rewards = $campaign->rewards($metric);
    $winners = $campaign->prizes_paid_at
        ? collect(app(\App\Services\Campaigns\ContestService::class)->winnersOf($campaign, $metric))
        : collect();
@endphp

<div class="card overflow-hidden">
    <div class="border-b border-ink-700 p-5">
        <h3 class="font-display text-lg font-bold text-ink-50">{{ $metric->title() }}</h3>
        <p class="mt-0.5 text-sm text-ink-400">Classés sur {{ $metric->rankedOn() }}.</p>

        @if ($rewards !== [])
            <ol class="mt-3 space-y-1 text-sm">
                @foreach ($rewards as $index => $reward)
                    <li class="flex justify-between gap-2">
                        <span class="text-ink-300">{{ $index === 0 ? '1re' : ($index + 1).'e' }} place</span>
                        <span class="text-right font-semibold {{ $reward->isMoney() ? 'tabular text-brand-400' : 'text-ink-50' }}">{{ $reward->display() }}</span>
                    </li>
                @endforeach
            </ol>
        @else
            <p class="mt-3 text-sm text-ink-400">Pour la gloire.</p>
        @endif
    </div>

    @if ($winners->isNotEmpty())
        {{-- Tableau clôturé : le podium annoncé, figé. --}}
        <div class="border-b border-ink-700 px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Gagnants</p>
            <ol class="mt-2 space-y-1.5 text-sm">
                @foreach ($winners as $winner)
                    <li @class(['flex justify-between gap-3', 'font-semibold' => $winner['user_id'] === auth()->id()])>
                        <span class="truncate text-ink-50">{{ $winner['rank'] }}. {{ $winner['name'] }}</span>
                        <span class="flex-none text-ink-300">
                            {{ $winner['reward'] ?? $metric->formatScore($metric === \App\Enums\ContestMetric::Posts ? ($winner['clips'] ?? 0) : $winner['views']) }}
                        </span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    @if ($mine)
        <div class="border-b border-ink-700 bg-brand-500/10 px-5 py-3 text-sm">
            Vous êtes <span class="font-bold text-ink-50">{{ $mine->rank === 1 ? '1er' : $mine->rank.'e' }}</span>
            avec <span class="font-semibold tabular text-ink-50">{{ $metric->formatScore($mine->score) }}</span>.
            @if ($reward = $campaign->rewardFor($mine->rank, $metric))
                <span class="text-brand-400">Place récompensée : {{ $reward->display() }}.</span>
            @endif
        </div>
    @endif

    @if ($standings->isEmpty())
        <p class="px-5 py-8 text-center text-sm text-ink-300">Personne n'est encore classé. La 1re place est libre.</p>
    @else
        <ol class="divide-y divide-ink-700">
            @foreach ($standings as $row)
                <li @class([
                    'flex items-center gap-3 px-5 py-3',
                    'bg-brand-500/10' => $row->user_id === auth()->id(),
                ])>
                    <span class="w-6 flex-none text-sm font-semibold tabular text-ink-400">{{ $row->rank }}</span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink-50">{{ $row->name }}</span>
                    <span class="flex-none text-sm font-semibold tabular text-ink-100">{{ $metric->formatScore($row->score) }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</div>
