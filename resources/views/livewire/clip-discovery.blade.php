@php use App\Support\Money; @endphp

<div class="space-y-6">

    {{-- Les filtres sur une seule ligne, comme dans FetchClip : trois
         décisions — la chaîne, la période, le format court — et le bouton au
         bout. Un panneau à déplier pour trois champs coûte un clic de plus à
         chaque recherche. --}}
    <div class="card p-5 sm:p-6">
        <form wire:submit="search" class="space-y-4">
            <div class="grid gap-3 lg:grid-cols-[1fr_7rem_auto_auto] lg:items-end">
                <div>
                    <label class="label" for="channels">Chaînes</label>
                    <input id="channels" type="text" wire:model="channels" class="input mt-1.5"
                           placeholder="squeezie, gotaga">
                </div>

                <div>
                    <label class="label" for="period">Période</label>
                    <select id="period" wire:model="period" class="input mt-1.5">
                        @foreach ($periodes as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </select>
                </div>

                <label class="flex items-center gap-2.5 pb-2.5 text-sm text-ink-200 lg:pb-3">
                    <input type="checkbox" wire:model="onlyShort"
                           class="rounded border-ink-600 bg-ink-900 text-ink-50 focus:ring-ink-400">
                    <span class="whitespace-nowrap">≤ {{ config('clipping.discovery.max_duration') }} s</span>
                </label>

                <button type="submit" class="btn-brand lg:mb-0.5">
                    <span wire:loading.remove wire:target="search">Chercher</span>
                    <span wire:loading wire:target="search">Recherche…</span>
                </button>
            </div>

            <p class="hint">
                Séparées par des virgules, {{ config('clipping.discovery.max_channels') }} au maximum.
                Pré-rempli avec les streamers qui ont une campagne ouverte.
            </p>
        </form>
    </div>

    @if ($error !== '')
        <div class="alert-warn">{{ $error }}</div>
    @endif

    {{-- Le lecteur intégré : on passe dix clips en revue d'affilée au lieu
         d'ouvrir dix onglets puis de les refermer en ayant perdu la liste. --}}
    @if ($playing && $lecturePossible)
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-ink-700 px-5 py-3">
                <p class="truncate font-semibold text-ink-50">
                    {{ collect($results)->firstWhere('id', $playing)['title'] ?? '' }}
                </p>
                <button type="button" wire:click="closePlayer"
                        class="rounded-lg p-1.5 text-ink-400 transition hover:bg-ink-800 hover:text-ink-100"
                        aria-label="Fermer le lecteur">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="aspect-video bg-black">
                <iframe
                    src="https://clips.twitch.tv/embed?clip={{ urlencode($playing) }}&parent={{ $parent }}&autoplay=true"
                    class="h-full w-full" allowfullscreen
                    title="Lecture du clip"></iframe>
            </div>
        </div>
    @endif

    {{-- Squelettes pendant la recherche : la grille garde sa forme au lieu de
         sauter d'un écran vide à vingt cartes. --}}
    <div wire:loading wire:target="search" class="clip-grid">
        @for ($i = 0; $i < 8; $i++)
            <div class="clip-skeleton">
                <div class="sk-thumb"></div>
                <div class="sk-line"></div>
                <div class="sk-line is-short"></div>
            </div>
        @endfor
    </div>

    <div wire:loading.remove wire:target="search">
        @if ($searched && $error === '')
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-display text-lg font-bold text-ink-50">
                    {{ count($results) }} clip{{ count($results) > 1 ? 's' : '' }}
                </h2>
                <p class="text-sm text-ink-400">Les plus vus d'abord.</p>
            </div>

            @if ($results === [])
                <div class="card px-6 py-14 text-center">
                    <p class="font-display text-base font-bold text-ink-50">Rien sur cette période</p>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-ink-300">
                        Élargissez la période, ou décochez la limite de durée : les meilleurs
                        moments dépassent souvent la minute.
                    </p>
                </div>
            @else
                <div class="clip-grid">
                    @foreach ($results as $clip)
                        @php
                            $campagne = $campagnes->get($clip['login']);
                            $cachet = $campagne?->rateFor($tiktok);
                            $garde = in_array($clip['id'], $dejaPris, true);
                        @endphp

                        <article @class(['clip-card', 'is-kept' => $garde])>
                            @if ($lecturePossible)
                                <button type="button" class="clip-thumb"
                                        wire:click="play('{{ $clip['id'] }}')"
                                        aria-label="Lire « {{ $clip['title'] }} »">
                                    @include('livewire.partials.clip-thumb-inner')
                                </button>
                            @else
                                <a href="{{ $clip['url'] }}" target="_blank" rel="noopener"
                                   class="clip-thumb" aria-label="Ouvrir « {{ $clip['title'] }} » sur Twitch">
                                    @include('livewire.partials.clip-thumb-inner')
                                </a>
                            @endif

                            <div class="clip-body">
                                <h3 class="clip-title">{{ $clip['title'] }}</h3>

                                <p class="clip-meta">
                                    <span class="font-semibold text-ink-200">{{ $clip['broadcaster'] }}</span>
                                    @if ($clip['creator'])
                                        <span aria-hidden="true">·</span>
                                        <span>clip de {{ $clip['creator'] }}</span>
                                    @endif
                                </p>

                                {{-- Ce qu'aucun outil générique ne peut dire : ce
                                     clip-là est payé, et par quelle campagne. --}}
                                @if ($campagne && $cachet)
                                    <a href="{{ route('campaigns.show', $campagne) }}" wire:navigate
                                       class="truncate text-xs font-semibold text-ink-100 underline underline-offset-2">
                                        {{ $campagne->title }}
                                    </a>
                                @else
                                    <p class="text-xs text-ink-500">Aucune campagne ouverte</p>
                                @endif

                                <div class="clip-actions">
                                    @if ($garde)
                                        <span class="btn-ghost pointer-events-none flex-1 justify-center text-xs opacity-60">
                                            De côté ✓
                                        </span>
                                    @else
                                        <button type="button" wire:click="keep('{{ $clip['id'] }}')"
                                                class="btn-ghost flex-1 justify-center text-xs">
                                            Mettre de côté
                                        </button>
                                    @endif

                                    <a href="{{ $clip['url'] }}" target="_blank" rel="noopener"
                                       class="btn-ghost justify-center text-xs" title="Ouvrir sur Twitch">↗</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    {{-- La sélection. Par clippeur, et visible de lui seul : dans un système
         où le budget part au premier arrivé, lire la liste de repérage d'un
         concurrent serait un avantage qu'aucun règlement ne rattrape. --}}
    <div>
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-display text-lg font-bold text-ink-50">Ma sélection</h2>
            <p class="text-sm text-ink-400">Visible de vous seul.</p>
        </div>

        @if ($selection->isEmpty())
            <div class="card px-6 py-12 text-center">
                <p class="font-display text-base font-bold text-ink-50">Rien de côté</p>
                <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-ink-300">
                    Mettez un clip de côté depuis les résultats : vous le retrouverez ici,
                    avec la campagne qu'il alimente, après le montage.
                </p>
            </div>
        @else
            <div class="clip-grid">
                @foreach ($selection as $garde)
                    <article @class(['clip-card', 'is-taken' => $garde->isTaken()])>
                        <a href="{{ $garde->url }}" target="_blank" rel="noopener" class="clip-thumb">
                            @if ($garde->thumbnail_url)
                                <img src="{{ $garde->thumbnail_url }}" alt="" loading="lazy">
                            @endif
                            <span class="clip-badge is-duration">{{ $garde->dureeLisible() }}</span>
                            <span class="clip-badge is-views">{{ Money::views($garde->views) }} vues</span>
                            @if ($garde->isTaken())
                                <span class="clip-badge is-rate">Publié</span>
                            @endif
                        </a>

                        <div class="clip-body">
                            <h3 class="clip-title">{{ $garde->title }}</h3>
                            <p class="clip-meta">
                                <span class="font-semibold text-ink-200">{{ $garde->broadcaster }}</span>
                            </p>

                            @if ($garde->campaign)
                                <a href="{{ route('campaigns.show', $garde->campaign) }}" wire:navigate
                                   class="truncate text-xs font-semibold text-ink-100 underline underline-offset-2">
                                    {{ $garde->campaign->title }}
                                </a>
                            @endif

                            <div class="clip-actions">
                                @if ($garde->isTaken())
                                    <button type="button" wire:click="markFree({{ $garde->id }})"
                                            class="btn-ghost flex-1 justify-center text-xs">
                                        Pas encore publié
                                    </button>
                                @else
                                    <button type="button" wire:click="markTaken({{ $garde->id }})"
                                            class="btn-ghost flex-1 justify-center text-xs">
                                        Marquer publié
                                    </button>
                                @endif

                                <button type="button" wire:click="forget({{ $garde->id }})"
                                        class="btn-ghost justify-center text-xs" title="Retirer de ma sélection">
                                    ✕
                                </button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</div>
