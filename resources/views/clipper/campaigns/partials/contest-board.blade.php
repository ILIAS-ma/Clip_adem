@php
    use App\Enums\ContestMetric;
    use App\Support\Money;
    /** @var ContestMetric $metric */
    $rewards = $campaign->rewards($metric);
    $winners = $campaign->prizes_paid_at
        ? collect(app(\App\Services\Campaigns\ContestService::class)->winnersOf($campaign, $metric))
        : collect();
    $ordinal = fn (int $rank) => $rank === 1 ? '1er' : $rank.'e';
@endphp

<div class="card overflow-hidden">
    {{-- La règle, en une ligne, et seulement quand elle n'est pas évidente. --}}
    @if ($metric === ContestMetric::Posts && $campaign->postMinViews() > 0)
        <p class="border-b border-ink-800 px-4 py-2.5 text-xs text-ink-400">
            Un clip compte dès <span class="font-semibold text-ink-200">{{ Money::views($campaign->postMinViews()) }} vues</span>.
        </p>
    @endif

    {{-- Les prix, en une bande qu'on fait défiler. --}}
    @if ($rewards !== [])
        <ol class="flex gap-2 overflow-x-auto border-b border-ink-800 px-4 py-3 [scrollbar-width:none]">
            @foreach ($rewards as $index => $reward)
                <li class="flex-none rounded-xl bg-ink-950/60 px-3 py-1.5 text-center ring-1 ring-ink-800">
                    <span class="block text-[10px] font-semibold uppercase tracking-wide text-ink-400">{{ $index === 0 ? '1re' : ($index + 1).'e' }} place</span>
                    <span class="block text-sm font-bold {{ $reward->isMoney() ? 'tabular text-brand-400' : 'text-ink-50' }}">{{ $reward->display() }}</span>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($winners->isNotEmpty())
        {{-- Tableau clôturé : le podium annoncé, figé. --}}
        <div class="border-b border-ink-800 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Gagnants</p>
            <ol class="mt-2 space-y-1.5 text-sm">
                @foreach ($winners as $winner)
                    <li @class(['flex justify-between gap-3', 'font-semibold' => $winner['user_id'] === auth()->id()])>
                        <span class="truncate text-ink-50">{{ $winner['rank'] }}. {{ $winner['name'] }}</span>
                        <span class="flex-none text-ink-300">
                            {{ $winner['reward'] ?? $metric->formatScore($metric === ContestMetric::Posts ? ($winner['clips'] ?? 0) : $winner['views']) }}
                        </span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    @if ($mine)
        <div class="flex items-center gap-3 border-b border-ink-800 bg-brand-500/10 px-4 py-3">
            <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-brand-500 font-display text-base font-bold text-white">{{ $mine->rank }}</span>
            <div class="min-w-0 flex-1 text-sm">
                <p class="text-ink-100">Vous êtes <span class="font-bold text-ink-50">{{ $ordinal($mine->rank) }}</span> · <span class="font-semibold tabular">{{ $metric->formatScore($mine->score) }}</span></p>
                @if ($reward = $campaign->rewardFor($mine->rank, $metric))
                    <p class="text-brand-300">Place récompensée : {{ $reward->display() }}</p>
                @endif
            </div>
        </div>
    @endif

    @if ($standings->isEmpty())
        <p class="px-4 py-8 text-center text-sm text-ink-300">Personne n'est encore classé. La 1re place est libre.</p>
    @else
        <ol class="divide-y divide-ink-800">
            @foreach ($standings as $row)
                <li @class([
                    'flex items-center gap-3 px-4 py-2.5',
                    'bg-brand-500/10' => $row->user_id === auth()->id(),
                ])>
                    <span @class([
                        'flex h-7 w-7 flex-none items-center justify-center rounded-full text-xs font-bold tabular',
                        'bg-amber-400 text-ink-950' => $row->rank === 1,
                        'bg-ink-200 text-ink-950' => $row->rank === 2,
                        'bg-amber-700 text-white' => $row->rank === 3,
                        'text-ink-400' => $row->rank > 3,
                    ])>{{ $row->rank }}</span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-ink-50">{{ $row->name }}</span>
                    <span class="flex-none text-sm font-semibold tabular text-ink-100">{{ $metric->formatScore($row->score) }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</div>
