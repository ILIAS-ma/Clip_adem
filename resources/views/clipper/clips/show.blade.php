@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">
                    {{ $clip->campaign?->creator?->name }}
                </p>
                <h1 class="mt-1 font-display text-2xl font-bold text-ink-50">{{ $clip->campaign?->title }}</h1>
                <p class="mt-1 text-sm text-ink-400">{{ $clip->platform->label() }} · {{ $clip->external_post_id }}</p>
            </div>

            <div class="flex gap-3">
                <a href="{{ $clip->url }}" target="_blank" rel="noopener" class="btn-ghost">Voir la publication</a>
                <a href="{{ route('clips.index') }}" class="btn-ghost">← Mes clips</a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat label="Vues" :value="Money::views($clip->views_total)"
                    :hint="Money::views($clip->paid_views).' rémunérées'" />

            <x-stat label="Gains validés" :value="Money::euros($clip->earned_cents)"
                    tone="money" hint="Inscrits au grand livre" />

            <x-stat label="En attente de crédit" :value="Money::euros($quote->payableCents)"
                    :tone="$quote->payableCents > 0 ? 'money' : 'neutral'"
                    :hint="$quote->deltaViews > 0 ? Money::views($quote->deltaViews).' vues nouvelles' : 'Tout est crédité'" />
        </div>

        @if ($clip->unpaidViews() > 0 && $quote->payableCents === 0)
            {{-- Le cas qui fait le plus douter d'un bug : les vues montent, les
                 gains non. Il faut nommer la raison, pas laisser deviner. --}}
            <div class="alert-warn">
                <p class="font-semibold">
                    {{ Money::views($clip->unpaidViews()) }} vues comptées mais non rémunérées
                </p>
                <p class="mt-1 leading-relaxed">
                    {{ match ($quote->outcome->value) {
                        'no_budget_left' => "Le budget de la campagne est épuisé, ou ce clip a atteint son plafond. Les vues continuent d'être comptées mais ne sont plus payées.",
                        'campaign_closed' => 'La campagne est terminée ou en pause : les vues ne sont plus rémunérées.',
                        'clip_not_payable' => "Ce clip n'est pas encore validé par la modération.",
                        'below_threshold' => "Ce clip n'a pas atteint le seuil de vues minimum de la campagne.",
                        default => $quote->outcome->label(),
                    } }}
                </p>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">

            <div class="card p-6">
                <h2 class="font-display text-lg font-bold text-ink-50">Conformité au brief</h2>

                @if (! $clip->compliance || ! ($clip->compliance['checks'] ?? []))
                    <p class="mt-3 text-sm text-ink-300">
                        La vérification aura lieu au premier relevé des vues.
                    </p>
                @else
                    <ul class="mt-5 space-y-3.5">
                        @foreach ($clip->compliance['checks'] as $check)
                            <li class="flex gap-3">
                                <span @class([
                                    'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                    'bg-brand-500/20 text-brand-300' => $check['passed'],
                                    'bg-red-500/20 text-red-300' => ! $check['passed'],
                                ])>{{ $check['passed'] ? '✓' : '✕' }}</span>
                                <div>
                                    <p class="text-sm font-medium text-ink-100">{{ $check['label'] }}</p>
                                    @if ($check['detail'])
                                        <p class="mt-0.5 text-sm text-red-400">{{ $check['detail'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <p class="hint mt-5 border-t border-ink-700 pt-4">
                        Ces contrôles sont automatiques. La validation finale reste faite par un modérateur.
                    </p>
                @endif

                @if ($clip->rejection_reason)
                    <div class="alert-danger mt-5">
                        <p class="font-semibold">Motif de la modération</p>
                        <p class="mt-1">{{ $clip->rejection_reason }}</p>
                    </div>
                @endif
            </div>

            <div class="card p-6">
                <h2 class="font-display text-lg font-bold text-ink-50">Historique des relevés</h2>

                @if ($snapshots->isEmpty())
                    <p class="mt-3 text-sm text-ink-300">Aucun relevé pour l'instant.</p>
                @else
                    <ul class="mt-4 divide-y divide-ink-700 text-sm">
                        @foreach ($snapshots as $snapshot)
                            <li class="flex items-baseline justify-between py-2.5">
                                <span class="text-ink-400">{{ $snapshot->captured_at->format('d/m/Y H:i') }}</span>
                                <span class="font-semibold tabular text-ink-50">{{ Money::views($snapshot->views) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <dl class="mt-5 space-y-2.5 border-t border-ink-700 pt-4 text-sm">
                    @if ($clip->posted_at)
                        <div class="flex justify-between">
                            <dt class="text-ink-400">Publié le</dt>
                            <dd class="text-ink-100">{{ $clip->posted_at->format('d/m/Y') }}</dd>
                        </div>
                    @endif
                    @if ($clip->duration_seconds)
                        <div class="flex justify-between">
                            <dt class="text-ink-400">Durée</dt>
                            <dd class="tabular text-ink-100">{{ $clip->duration_seconds }} s</dd>
                        </div>
                    @endif
                    @if ($clip->last_synced_at)
                        <div class="flex justify-between">
                            <dt class="text-ink-400">Dernier relevé</dt>
                            <dd class="text-ink-100">{{ $clip->last_synced_at->diffForHumans() }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
