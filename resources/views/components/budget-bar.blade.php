@props(['campaign', 'remaining'])

@php
    use App\Support\Money;

    $total = max(1, $campaign->budget_total_cents);
    $consumed = min(100, round(($total - $remaining) / $total * 100));

    // La couleur porte l'urgence : à 10 % de reliquat, un clippeur doit savoir
    // qu'il ne sera peut-être pas payé s'il publie maintenant.
    $tone = match (true) {
        $remaining <= 0 => 'bg-red-500',
        $consumed >= 90 => 'bg-amber-500',
        default => 'bg-emerald-500',
    };
@endphp

<div {{ $attributes }}>
    <div class="flex items-baseline justify-between text-sm">
        <span class="font-medium text-gray-900">
            {{ $remaining <= 0 ? 'Budget épuisé' : Money::euros($remaining) . ' restants' }}
        </span>
        <span class="text-gray-500 tabular-nums">sur {{ Money::euros($campaign->budget_total_cents) }}</span>
    </div>

    <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-200"
         role="progressbar"
         aria-valuenow="{{ $consumed }}"
         aria-valuemin="0"
         aria-valuemax="100"
         aria-label="Budget consommé">
        <div class="h-full rounded-full {{ $tone }} transition-all" style="width: {{ $consumed }}%"></div>
    </div>

    <p class="mt-1 text-xs text-gray-500">{{ $consumed }} % du budget consommé</p>
</div>
