@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-baseline justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $clip->campaign?->title }}</h2>
                <p class="text-sm text-gray-500">{{ $clip->platform->label() }} · {{ $clip->external_post_id }}</p>
            </div>
            <a href="{{ route('clips.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Mes clips</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm text-gray-500">Vues</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ Money::views($clip->views_total) }}</p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ Money::views($clip->paid_views) }} rémunérées
                    </p>
                </div>

                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm text-gray-500">Gains validés</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ Money::euros($clip->earned_cents) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Inscrits au grand livre</p>
                </div>

                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm text-gray-500">En attente de crédit</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-700">{{ Money::euros($quote->payableCents) }}</p>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $quote->deltaViews > 0 ? Money::views($quote->deltaViews).' vues nouvelles' : 'Tout est crédité' }}
                    </p>
                </div>
            </div>

            @if ($clip->unpaidViews() > 0 && $quote->payableCents === 0)
                {{-- Le cas qui fait le plus douter d'un bug : les vues montent,
                     les gains non. Il faut nommer la raison. --}}
                <div class="rounded-md border-l-4 border-amber-400 bg-amber-50 p-4 text-sm text-amber-800">
                    <p class="font-medium">
                        {{ Money::views($clip->unpaidViews()) }} vues comptées mais non rémunérées
                    </p>
                    <p class="mt-1">
                        {{ match ($quote->outcome->value) {
                            'no_budget_left' => "Le budget de la campagne est épuisé, ou ce clip a atteint son plafond. Les vues continuent d'être comptées mais ne sont plus payées.",
                            'campaign_closed' => "La campagne est terminée ou en pause : les vues ne sont plus rémunérées.",
                            'clip_not_payable' => "Ce clip n'est pas validé par la modération.",
                            'below_threshold' => 'Ce clip n\'a pas encore atteint le seuil de vues minimum de la campagne.',
                            default => $quote->outcome->label(),
                        } }}
                    </p>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-2">

                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-base font-semibold text-gray-900">Conformité au brief</h3>

                    @if (! $clip->compliance || ! ($clip->compliance['checks'] ?? []))
                        <p class="mt-3 text-sm text-gray-500">
                            La vérification aura lieu au premier relevé des vues.
                        </p>
                    @else
                        <ul class="mt-4 space-y-3">
                            @foreach ($clip->compliance['checks'] as $check)
                                <li class="flex gap-3">
                                    <span @class([
                                        'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                        'bg-emerald-100 text-emerald-700' => $check['passed'],
                                        'bg-red-100 text-red-700' => ! $check['passed'],
                                    ])>{{ $check['passed'] ? '✓' : '✕' }}</span>
                                    <div>
                                        <p class="text-sm text-gray-900">{{ $check['label'] }}</p>
                                        @if ($check['detail'])
                                            <p class="text-xs text-red-600">{{ $check['detail'] }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>

                        <p class="mt-4 text-xs leading-relaxed text-gray-500">
                            Ces contrôles sont automatiques. La validation finale reste faite par un modérateur.
                        </p>
                    @endif

                    @if ($clip->rejection_reason)
                        <div class="mt-4 rounded-md bg-red-50 p-3">
                            <p class="text-xs font-medium uppercase tracking-wide text-red-700">Motif de la modération</p>
                            <p class="mt-1 text-sm text-red-800">{{ $clip->rejection_reason }}</p>
                        </div>
                    @endif
                </div>

                <div class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-base font-semibold text-gray-900">Historique des relevés</h3>

                    @if ($snapshots->isEmpty())
                        <p class="mt-3 text-sm text-gray-500">Aucun relevé pour l'instant.</p>
                    @else
                        <ul class="mt-4 divide-y divide-gray-100 text-sm">
                            @foreach ($snapshots as $snapshot)
                                <li class="flex justify-between py-2">
                                    <span class="text-gray-500">{{ $snapshot->captured_at->format('d/m/Y H:i') }}</span>
                                    <span class="font-medium tabular-nums text-gray-900">{{ Money::views($snapshot->views) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <dl class="mt-5 space-y-2 border-t border-gray-100 pt-4 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Publication</dt>
                            <dd><a href="{{ $clip->url }}" target="_blank" rel="noopener" class="text-emerald-700 underline">Ouvrir</a></dd>
                        </div>
                        @if ($clip->posted_at)
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Publié le</dt>
                                <dd class="text-gray-900">{{ $clip->posted_at->format('d/m/Y') }}</dd>
                            </div>
                        @endif
                        @if ($clip->last_synced_at)
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Dernier relevé</dt>
                                <dd class="text-gray-900">{{ $clip->last_synced_at->diffForHumans() }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
