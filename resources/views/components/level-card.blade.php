@props(['progression'])

@php
    use App\Support\Money;

    $level = $progression->level;
    $next = $progression->nextLevel();

    $tone = match ($level->value) {
        'confirmed' => 'bg-sky-100 text-sky-800',
        'expert' => 'bg-money-100 text-money-700',
        'elite' => 'bg-brand-100 text-brand-800',
        'legend' => 'bg-ink-900 text-brand-300',
        default => 'bg-ink-100 text-ink-600',
    };
@endphp

<div {{ $attributes->merge(['class' => 'card p-6']) }}>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-ink-400">Votre niveau</p>
            <p class="mt-1.5 flex items-center gap-3">
                <span class="font-display text-2xl font-bold text-ink-900">{{ $level->label() }}</span>
                <span class="chip {{ $tone }}">{{ Money::views($progression->careerXp) }} XP</span>
            </p>
        </div>

        @if ($level->hasPerks())
            <span class="{{ $progression->perksActive ? 'chip-ok' : 'chip-wait' }}">
                {{ $progression->perksActive ? 'Avantages actifs' : 'Avantages en pause' }}
            </span>
        @endif
    </div>

    @if ($next)
        <div class="mt-5">
            <div class="flex items-baseline justify-between text-sm">
                <span class="text-ink-500">Prochain niveau : {{ $next->label() }}</span>
                <span class="tabular text-ink-400">
                    {{ Money::views($progression->xpToNextLevel()) }} XP restants
                </span>
            </div>
            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-ink-100">
                <div class="h-full rounded-full bg-ink-800 transition-all duration-500"
                     style="width: {{ $progression->progressPercent() }}%"></div>
            </div>
        </div>
    @else
        <p class="mt-5 text-sm text-ink-500">Niveau maximum atteint.</p>
    @endif

    @if ($level->hasPerks())
        <ul class="mt-5 space-y-2 border-t border-ink-100 pt-4 text-sm">
            @if ($level->earlyAccessHours() > 0)
                <li class="flex items-baseline justify-between gap-3">
                    <span class="text-ink-500">Accès anticipé aux campagnes</span>
                    <span class="font-semibold tabular {{ $progression->perksActive ? 'text-money-600' : 'text-ink-300 line-through' }}">
                        {{ $level->earlyAccessHours() }} h
                    </span>
                </li>
            @endif

            <li class="flex items-baseline justify-between gap-3">
                <span class="text-ink-500">Plafond de gain par clip</span>
                <span class="font-semibold tabular {{ $progression->perksActive ? 'text-money-600' : 'text-ink-300 line-through' }}">
                    ×{{ rtrim(rtrim(number_format($level->clipCapMultiplier(), 2, ',', ''), '0'), ',') }}
                </span>
            </li>
        </ul>

        {{-- Le niveau reste acquis, les avantages se maintiennent : sans cette
             explication, une mise en pause passerait pour une sanction. --}}
        @unless ($progression->perksActive)
            <div class="alert-warn mt-4">
                <p class="font-semibold">Avantages en pause</p>
                <p class="mt-1 leading-relaxed">
                    Il vous manque {{ Money::views($progression->viewsToReactivate()) }} vues rémunérées
                    sur les {{ config('clipping.progression.activity_window_days') }} derniers jours
                    pour les réactiver. Votre niveau, lui, reste acquis.
                </p>
            </div>
        @else
            <p class="hint mt-3">
                {{ Money::views($progression->recentViews) }} vues rémunérées sur
                {{ config('clipping.progression.activity_window_days') }} jours — seuil à maintenir :
                {{ Money::views($level->activityFloor()) }}.
            </p>
        @endunless
    @else
        <p class="hint mt-5 border-t border-ink-100 pt-4">
            Atteignez le niveau {{ $next?->label() }} pour débloquer un plafond de gain relevé,
            puis l'accès anticipé aux nouvelles campagnes.
        </p>
    @endif
</div>
