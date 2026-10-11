@php use App\Support\Money; @endphp

<div class="stack">

    {{--
        Le panneau de recherche, à deux étages séparés d'un filet.

        En haut, ce qui coûte un appel réseau : les chaînes, la période. En
        bas, ce qui ne fait que retrier ce qui est déjà là. La séparation
        n'est pas décorative — le quota Twitch est celui de la plateforme
        entière, et un changement de tri ne doit pas le dépenser.
    --}}
    <form wire:submit="search" class="surface search-card">
        <div class="search-row">
            <div class="segmented" role="group" aria-label="Portée">
                <button type="button" wire:click="setScope('tous')"
                        @class(['segment', 'active' => $scope === 'tous'])>
                    Toutes
                </button>
                <button type="button" wire:click="setScope('payes')"
                        @class(['segment', 'active' => $scope === 'payes'])>
                    Payées
                </button>
            </div>

            <label class="grow">
                <span class="sr-only">Chaînes à explorer</span>
                <input type="text" wire:model="channels" class="field"
                       placeholder="squeezie, kamet0, gotaga" autocomplete="off">
            </label>

            <label>
                <span class="sr-only">Période</span>
                <select wire:model="period" class="field narrow">
                    @foreach ($periodes as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </label>

            <button type="submit" class="btn primary">
                <span wire:loading.remove wire:target="search">Chercher</span>
                <span wire:loading wire:target="search">Recherche…</span>
            </button>
        </div>

        <div class="filter-row">
            <span class="filter-label">Filtres</span>

            <label class="mini-field">
                Vues min
                <input type="number" min="0" step="100" wire:model.live="minViews">
            </label>

            <label class="mini-field">
                Tri
                <select wire:model.live="sort">
                    @foreach ($tris as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </select>
            </label>

            <label class="switch">
                <input type="checkbox" wire:model.live="onlyShort">
                <span class="track"></span>
                <span>Moins de {{ config('clipping.discovery.max_duration') }} s</span>
            </label>

            <label class="switch">
                <input type="checkbox" wire:model.live="hideKept">
                <span class="track"></span>
                <span>Masquer ceux de côté</span>
            </label>

            <p class="hint" style="margin-left: auto">
                {{ config('clipping.discovery.max_channels') }} chaînes au maximum, séparées par des virgules
            </p>
        </div>
    </form>

    @if ($error !== '')
        <div class="banner err">{{ $error }}</div>
    @endif

    {{-- Le lecteur intégré : on passe dix clips en revue d'affilée au lieu
         d'ouvrir dix onglets, puis de les refermer en ayant perdu la
         liste. --}}
    @if ($playing && $lecturePossible)
        <div class="surface" data-lecteur>
            <div class="modal-head">
                <h2>{{ collect($results)->firstWhere('id', $playing)['title'] ?? '' }}</h2>

                <button type="button" wire:click="closePlayer" class="icon-btn" style="margin-left: auto"
                        aria-label="Fermer le lecteur">
                    <svg class="ico" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M4 4l8 8M12 4l-8 8" />
                    </svg>
                </button>
            </div>

            <div style="aspect-ratio: 16 / 9; background: #000">
                <iframe
                    src="https://clips.twitch.tv/embed?clip={{ urlencode($playing) }}&parent={{ $parent }}&autoplay=true"
                    style="width: 100%; height: 100%; border: 0" allowfullscreen
                    title="Lecture du clip"></iframe>
            </div>
        </div>
    @endif

    {{-- Squelettes au même gabarit que les cartes réelles : la grille garde
         sa forme au lieu de sauter d'un écran vide à vingt cartes. --}}
    <div wire:loading wire:target="search" class="grid">
        @for ($i = 0; $i < 8; $i++)
            <div class="skeleton">
                <div class="sk-thumb"></div>
                <div class="sk-line"></div>
                <div class="sk-line short"></div>
            </div>
        @endfor
    </div>

    <div wire:loading.remove wire:target="search" class="stack">
        @if ($searched && $error === '')
            <div class="toolbar">
                <p class="summary">
                    <strong>{{ count($affichables) }}</strong>
                    clip{{ count($affichables) > 1 ? 's' : '' }}
                    @if (count($affichables) !== $trouves)
                        {{-- Dire ce qu'un filtre a écarté : « 12 » tout seul
                             laisse croire que la recherche a échoué. --}}
                        sur {{ $trouves }} trouvés
                    @endif
                </p>

                @if ($affichables !== [])
                    <div class="actions">
                        <button type="button" wire:click="keepAll" class="btn ghost sm">
                            Tout mettre de côté
                        </button>
                    </div>
                @endif
            </div>

            @if ($affichables === [])
                <div class="surface empty">
                    @if ($trouves > 0)
                        <strong>Tout est filtré</strong>
                        {{ $trouves }} clip{{ $trouves > 1 ? 's ont' : ' a' }} été
                        trouvé{{ $trouves > 1 ? 's' : '' }}, mais les filtres les écartent tous.
                        Baissez les vues minimales, ou repassez la portée sur toutes les chaînes.
                    @else
                        <strong>Rien sur cette période</strong>
                        Élargissez la période, ou coupez la limite de durée : les meilleurs
                        moments dépassent souvent la minute.
                    @endif
                </div>
            @else
                <div class="grid">
                    @foreach ($affichables as $clip)
                        @php
                            $campagne = $campagnes->get($clip['login']);
                            $cachet = $campagne?->rateFor($tiktok);
                            $garde = in_array($clip['id'], $dejaPris, true);
                        @endphp

                        <article @class(['card', 'selected' => $garde])>
                            @if ($lecturePossible)
                                <button type="button" class="thumb"
                                        wire:click="play('{{ $clip['id'] }}')"
                                        aria-label="Lire « {{ $clip['title'] }} »">
                                    @include('livewire.partials.clip-thumb-inner')
                                </button>
                            @else
                                <a href="{{ $clip['url'] }}" target="_blank" rel="noopener"
                                   class="thumb" aria-label="Ouvrir « {{ $clip['title'] }} » sur Twitch">
                                    @include('livewire.partials.clip-thumb-inner')
                                </a>
                            @endif

                            <div class="body">
                                <h3 class="title">{{ $clip['title'] }}</h3>

                                <p class="card-meta">
                                    <span class="who">{{ $clip['broadcaster'] }}</span>
                                    @if ($clip['creator'])
                                        <span>clip de {{ $clip['creator'] }}</span>
                                    @endif
                                </p>

                                {{-- Ce qu'aucun outil générique ne peut dire : ce
                                     clip-là est payé, et par quelle campagne. --}}
                                @if ($campagne && $cachet)
                                    <a href="{{ route('campaigns.show', $campagne) }}" class="link"
                                       style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap">
                                        {{ $campagne->title }}
                                    </a>
                                @else
                                    <p class="hint">Aucune campagne ouverte</p>
                                @endif

                                <div class="card-actions">
                                    @if ($garde)
                                        <span class="btn ghost" aria-disabled="true">De côté</span>
                                    @else
                                        <button type="button" wire:click="keep('{{ $clip['id'] }}')"
                                                class="btn ghost">
                                            Mettre de côté
                                        </button>
                                    @endif

                                    <a href="{{ $clip['url'] }}" target="_blank" rel="noopener"
                                       class="btn ghost" style="flex: 0 0 auto; padding: 6px 9px"
                                       aria-label="Ouvrir sur Twitch">
                                        <svg class="ico" viewBox="0 0 16 16" aria-hidden="true">
                                            <path d="M6.5 3.5H3.5v9h9v-3M10 2.5h3.5V6M13 3l-5.5 5.5" />
                                        </svg>
                                    </a>
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
    <div class="stack">
        <div class="toolbar">
            <p class="summary">
                <strong>Ma sélection</strong>
                {{ $selection->count() }} clip{{ $selection->count() > 1 ? 's' : '' }},
                visible de vous seul
            </p>
        </div>

        @if ($selection->isEmpty())
            <div class="surface empty">
                <strong>Rien de côté</strong>
                Mettez un clip de côté depuis les résultats : vous le retrouverez ici,
                avec la campagne qu'il alimente, après le montage.
            </div>
        @else
            <div class="grid">
                @foreach ($selection as $garde)
                    <article @class(['card', 'done' => $garde->isTaken()])>
                        <a href="{{ $garde->url }}" target="_blank" rel="noopener" class="thumb">
                            @if ($garde->thumbnail_url)
                                <img src="{{ $garde->thumbnail_url }}" alt="" loading="lazy">
                            @endif
                            <span class="badge dur">{{ $garde->dureeLisible() }}</span>
                            <span class="badge views">{{ Money::views($garde->views) }} vues</span>
                        </a>

                        <div class="body">
                            <h3 class="title">{{ $garde->title }}</h3>

                            <p class="card-meta">
                                <span class="who">{{ $garde->broadcaster }}</span>
                                @if ($garde->isTaken())
                                    <span style="display: inline-flex; align-items: center; gap: 4px">
                                        <span class="done-dot" aria-hidden="true"></span> publié
                                    </span>
                                @endif
                            </p>

                            @if ($garde->campaign)
                                <a href="{{ route('campaigns.show', $garde->campaign) }}" class="link"
                                   style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap">
                                    {{ $garde->campaign->title }}
                                </a>
                            @endif

                            <div class="card-actions">
                                @if ($garde->isTaken())
                                    <button type="button" wire:click="markFree({{ $garde->id }})"
                                            class="btn ghost">
                                        Pas encore publié
                                    </button>
                                @else
                                    <button type="button" wire:click="markTaken({{ $garde->id }})"
                                            class="btn ghost">
                                        Marquer publié
                                    </button>
                                @endif

                                <button type="button" wire:click="forget({{ $garde->id }})"
                                        class="btn ghost" style="flex: 0 0 auto; padding: 6px 9px"
                                        aria-label="Retirer de ma sélection">
                                    <svg class="ico" viewBox="0 0 16 16" aria-hidden="true">
                                        <path d="M4 4l8 8M12 4l-8 8" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</div>
