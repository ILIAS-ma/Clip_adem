@php use App\Support\Money; @endphp

<div class="space-y-6">

    {{-- Les filtres. Trois décisions, pas douze : la chaîne, la période, et
         si l'on veut du format court. Le reste est du réglage que personne
         ne touche. --}}
    <div class="card p-6 sm:p-8">
        <form wire:submit="search" class="space-y-5">
            <div>
                <label class="label" for="channels">Chaînes à explorer</label>
                <input id="channels" type="text" wire:model="channels" class="input mt-1.5"
                       placeholder="squeezie, gotaga">
                <p class="hint">
                    Séparées par des virgules, {{ config('clipping.discovery.max_channels') }} au maximum.
                    Pré-rempli avec les streamers qui ont une campagne ouverte.
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="label" for="period">Période</label>
                    <select id="period" wire:model="period" class="input mt-1.5">
                        @foreach ($periodes as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </select>
                </div>

                <label class="flex items-center gap-2.5 pb-2.5 text-sm text-ink-200">
                    <input type="checkbox" wire:model="onlyShort"
                           class="rounded border-ink-600 bg-ink-900 text-ink-50 focus:ring-ink-400">
                    <span>Moins de {{ config('clipping.discovery.max_duration') }} s</span>
                </label>

                <button type="submit" class="btn-brand ms-auto">
                    <span wire:loading.remove wire:target="search">Chercher</span>
                    <span wire:loading wire:target="search">Recherche…</span>
                </button>
            </div>
        </form>
    </div>

    @if ($error !== '')
        <div class="alert-warn">{{ $error }}</div>
    @endif

    {{-- Résultats --}}
    @if ($searched && $error === '')
        <div class="card">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-ink-700 px-6 py-4">
                <h2 class="font-display text-lg font-bold text-ink-50">
                    {{ count($results) }} clip{{ count($results) > 1 ? 's' : '' }} trouvé{{ count($results) > 1 ? 's' : '' }}
                </h2>
                <p class="text-sm text-ink-400">Les plus vus d'abord.</p>
            </div>

            @if ($results === [])
                <div class="px-6 py-14 text-center">
                    <p class="font-display text-base font-bold text-ink-50">Rien sur cette période</p>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-ink-300">
                        Élargissez la période, ou décochez la limite de durée : les meilleurs
                        moments dépassent souvent la minute.
                    </p>
                </div>
            @else
                <ul class="divide-y divide-ink-700">
                    @foreach ($results as $clip)
                        @php
                            $campagne = $campagnes->get($clip['login']);
                            $cachet = $campagne?->rateFor($tiktok);
                            $garde = in_array($clip['id'], $dejaPris, true);
                        @endphp

                        <li class="flex flex-wrap items-start gap-4 px-6 py-4">
                            @if ($clip['thumbnail'])
                                <img src="{{ $clip['thumbnail'] }}" alt="" loading="lazy"
                                     class="h-16 w-28 shrink-0 rounded-lg object-cover">
                            @else
                                <span class="flex h-16 w-28 shrink-0 items-center justify-center rounded-lg bg-ink-800 text-xs text-ink-500">
                                    {{ $clip['duration_label'] }}
                                </span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-ink-50">{{ $clip['title'] }}</p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-400">
                                    <span>{{ $clip['broadcaster'] }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span class="tabular">{{ Money::views($clip['views']) }} vues</span>
                                    <span aria-hidden="true">·</span>
                                    <span class="tabular">{{ $clip['duration_label'] }}</span>
                                </p>

                                {{-- Ce qu'aucun outil générique ne peut dire : ce
                                     clip-là est payé, et combien. --}}
                                @if ($campagne && $cachet)
                                    <p class="mt-1.5 flex flex-wrap items-center gap-2 text-sm">
                                        <span class="chip-ok">{{ Money::euros($cachet) }} / 1000 vues</span>
                                        <a href="{{ route('campaigns.show', $campagne) }}" wire:navigate
                                           class="font-semibold text-ink-100 underline underline-offset-2">
                                            {{ $campagne->title }}
                                        </a>
                                    </p>
                                @else
                                    <p class="mt-1.5 text-sm text-ink-500">
                                        Aucune campagne ouverte pour cette chaîne.
                                    </p>
                                @endif
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-2">
                                <a href="{{ $clip['url'] }}" target="_blank" rel="noopener"
                                   class="text-sm font-semibold text-ink-100 underline underline-offset-2">
                                    Voir ↗
                                </a>

                                @if ($garde)
                                    <span class="chip-neutral">Dans ma sélection</span>
                                @else
                                    <button type="button" wire:click="keep('{{ $clip['id'] }}')"
                                            class="btn-ghost text-sm">
                                        Mettre de côté
                                    </button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    {{-- La sélection. Par clippeur, et visible de lui seul : dans un système
         où le budget part au premier arrivé, lire la liste de repérage d'un
         concurrent serait un avantage qu'aucun règlement ne rattrape. --}}
    <div class="card">
        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-ink-700 px-6 py-4">
            <h2 class="font-display text-lg font-bold text-ink-50">Ma sélection</h2>
            <p class="text-sm text-ink-400">Visible de vous seul.</p>
        </div>

        @if ($selection->isEmpty())
            <div class="px-6 py-14 text-center">
                <p class="font-display text-base font-bold text-ink-50">Rien de côté pour l'instant</p>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-ink-300">
                    Mettez un clip de côté depuis les résultats : vous le retrouverez ici,
                    avec la campagne qu'il alimente, après le montage.
                </p>
            </div>
        @else
            <ul class="divide-y divide-ink-700">
                @foreach ($selection as $garde)
                    <li @class([
                        'flex flex-wrap items-start gap-4 px-6 py-4',
                        'opacity-60' => $garde->isTaken(),
                    ])>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-ink-50">{{ $garde->title }}</p>
                            <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-400">
                                <span>{{ $garde->broadcaster }}</span>
                                <span aria-hidden="true">·</span>
                                <span class="tabular">{{ Money::views($garde->views) }} vues</span>
                                <span aria-hidden="true">·</span>
                                <span class="tabular">{{ $garde->dureeLisible() }}</span>
                            </p>

                            @if ($garde->campaign)
                                <p class="mt-1.5 text-sm">
                                    <a href="{{ route('campaigns.show', $garde->campaign) }}" wire:navigate
                                       class="font-semibold text-ink-100 underline underline-offset-2">
                                        {{ $garde->campaign->title }}
                                    </a>
                                </p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <a href="{{ $garde->url }}" target="_blank" rel="noopener"
                               class="text-sm font-semibold text-ink-100 underline underline-offset-2">
                                Voir ↗
                            </a>

                            @if ($garde->isTaken())
                                <span class="chip-ok">Publié</span>
                                <button type="button" wire:click="markFree({{ $garde->id }})"
                                        class="text-sm text-ink-400 underline underline-offset-2">
                                    Annuler
                                </button>
                            @else
                                <button type="button" wire:click="markTaken({{ $garde->id }})"
                                        class="btn-ghost text-sm">
                                    Marquer publié
                                </button>
                            @endif

                            <button type="button" wire:click="forget({{ $garde->id }})"
                                    class="text-sm text-ink-500 underline underline-offset-2 hover:text-ink-300">
                                Retirer
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
