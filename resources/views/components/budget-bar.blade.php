@props(['campaign', 'remaining'])

@php
    use App\Support\Money;

    $total = max(1, $campaign->budget_total_cents);
    $consumed = min(100, round(($total - $remaining) / $total * 100));

    // À 10 % de reliquat, un clippeur doit savoir qu'il risque de ne pas être
    // payé s'il publie maintenant. La couleur porte cette urgence.
    $tone = match (true) {
        $remaining <= 0 => 'bg-red-500/150',
        $consumed >= 90 => 'bg-brand-500',
        default => 'bg-brand-500',
    };
@endphp

<div {{ $attributes }}>
    <div class="flex items-baseline justify-between gap-2 text-sm">
        <span class="font-semibold tabular {{ $remaining <= 0 ? 'text-red-400' : 'text-ink-50' }}">
            {{ $remaining <= 0 ? 'Budget épuisé' : Money::euros($remaining).' restants' }}
        </span>
        <span class="tabular text-ink-400">sur {{ Money::euros($campaign->budget_total_cents) }}</span>
    </div>

    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-ink-700"
         role="progressbar" aria-valuenow="{{ $consumed }}" aria-valuemin="0" aria-valuemax="100"
         aria-label="Budget consommé">
        <div class="h-full rounded-full {{ $tone }} transition-all duration-500" style="width: {{ $consumed }}%"></div>
    </div>

    <p class="mt-1.5 text-xs text-ink-400">{{ $consumed }} % consommé</p>
</div>
