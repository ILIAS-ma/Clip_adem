@php use App\Support\Money; @endphp

<div class="app">

    {{-- ═══════════════ BARRE LATÉRALE ═══════════════ --}}
    <aside class="sidebar">
        <div class="brand">
            {{-- Un monogramme, jamais une image ni un emoji. --}}
            <span class="logo">C</span>
            <div class="brand-text">
                <h1>Mode créateur</h1>
                <p>Repérer · Couper · Publier</p>
            </div>
        </div>

        <div class="side-scroll">
            {{--
                Le classement des directs.

                C'est la réponse à « je cherche quoi ? », et donc le point de
                départ du travail : on regarde qui diffuse maintenant, par
                audience, et on clique. C'est là que les clips se font, parce
                que c'est là qu'il y a des spectateurs pour les faire.

                Chaque ligne charge les clips de la chaîne — d'où le rail dans
                le composant et non dans le gabarit : hors du composant, il
                serait décoratif.
            --}}
            <section class="side-block">
                <div class="side-head">
                    <span class="side-title"><span class="live-pulse"></span> En direct</span>

                    <select wire:model.live="liveLanguage" class="mini" aria-label="Langue">
                        @foreach ($langues as $code => $libelle)
                            <option value="{{ $code }}">{{ $libelle }}</option>
                        @endforeach
                    </select>

                    <button type="button" wire:click="$refresh" class="icon-btn" title="Rafraîchir">
                        <svg class="ico" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="M13.6 8a5.6 5.6 0 1 1-1.7-4" />
                            <path d="M13.8 2.2v3.4h-3.4" />
                        </svg>
                    </button>
                </div>

                <div class="side-list">
                    @forelse ($lives as $rang => $live)
                        <button type="button" wire:click="pick('{{ $live->login }}')" class="side-item"
                                title="Voir les clips de {{ $live->name }}">
                            <span class="rank">{{ $rang + 1 }}</span>
                            <span class="who">
                                <span class="cname">{{ $live->name }}</span>
                                <span class="cmeta">{{ $live->game ?? '—' }}</span>
                            </span>
                            <span class="count">{{ $live->audienceLisible() }}</span>
                        </button>
                    @empty
                        <span class="side-item empty-row">
                            {{ $utilisable ? 'Aucun direct trouvé.' : 'Twitch n’est pas relié.' }}
                        </span>
                    @endforelse
                </div>

                <p class="side-note">Classés sur l'audience du moment.</p>
            </section>

            {{-- Les chaînes déjà explorées : on revient toujours aux mêmes. --}}
            @if ($favoris !== [])
                <section class="side-block">
                    <div class="side-head"><span class="side-title">Mes chaînes</span></div>

                    <div class="chips">
                        @foreach ($favoris as $login)
                            <button type="button" wire:click="pick('{{ $login }}')" class="chip">
                                {{ $login }}
                            </button>
                        @endforeach
                    </div>
                </section>
            @endif

            <details class="panel">
                <summary>
                    Comment ça marche
                    <span class="resume">4 étapes</span>
                </summary>

                <div class="panel-body">
                    <ol class="steps">
                        <li><span>1</span> Cliquez un streamer en direct, ou tapez son pseudo.</li>
                        <li><span>2</span> Mettez de côté les moments qui valent le coup.</li>
                        <li><span>3</span> Récupérez le mp4 : le serveur le télécharge.</li>
                        <li><span>4</span> Montez-le en vertical, publiez, collez l'adresse.</li>
                    </ol>
                </div>
            </details>
        </div>

        <div class="status">
            <span class="pill {{ $simule ? 'bad' : 'ok' }}">
                {{ $simule ? 'Démonstration' : 'Twitch relié' }}
            </span>
            <span class="pill">{{ $selection->count() }} de côté</span>
            <span class="pill">{{ $fichiers->count() }} fichier{{ $fichiers->count() > 1 ? 's' : '' }}</span>
        </div>

        <a href="{{ route('dashboard') }}" class="btn ghost full">
            <svg class="ico" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M9.5 3.5 5 8l4.5 4.5" />
            </svg>
            Quitter le mode
        </a>
    </aside>

    {{-- ═══════════════ CONTENU ═══════════════ --}}
    <main class="main">
        <div class="stack">

            @if (! $utilisable)
                <div class="banner warn">
                    <strong>Twitch n'est pas relié.</strong>
                    Il manque les identifiants de l'application. Mieux vaut cet écran vide qu'une
                    liste de clips inventés : vous perdriez du temps à chercher des moments qui
                    n'existent pas.
                </div>
            @elseif ($simule)
                <div class="banner warn">
                    <strong>Mode démonstration.</strong>
                    Les clips et les directs affichés sont fictifs : crédibles mais faux. Les
                    filtres, la sélection et les fichiers fonctionnent.
                </div>
            @endif

            {{--
                Le panneau de recherche, à deux étages séparés d'un filet.

                En haut, ce qui coûte un appel réseau. En bas, ce qui ne fait
                que retrier ce qui est déjà là. La séparation n'est pas
                décorative : changer un tri ne doit pas redemander Twitch.
            --}}
            <form wire:submit="search" class="surface search-card">
                <div class="search-row">
                    <div class="segmented" role="group" aria-label="Portée">
                        <button type="button" wire:click="setScope('mes')"
                                @class(['segment', 'active' => $scope === 'mes'])>
                            Mes streamers
                        </button>
                        <button type="button" wire:click="setScope('tout')"
                                @class(['segment', 'active' => $scope === 'tout'])>
                            Tout le site
                        </button>
                    </div>

                    @if ($scope === 'mes')
                        <label class="grow">
                            <span class="sr-only">Chaînes à explorer</span>
                            <input type="text" wire:model="channels" class="field"
                                   placeholder="squeezie, kamet0, gotaga" autocomplete="off">
                        </label>
                    @else
                        <p class="hint grow">
                            Les clips les plus vus de toute la plateforme, sans nommer personne.
                            Reconstitué en balayant les plus grosses catégories du moment.
                        </p>
                    @endif

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
                </div>
            </form>

            @if ($error !== '')
                <div class="banner err">{{ $error }}</div>
            @endif

            {{-- Le lecteur intégré : on passe dix clips en revue d'affilée au
                 lieu d'ouvrir dix onglets, puis de les refermer en ayant perdu
                 la liste. --}}
            @if ($playing && $lecturePossible)
                <div class="surface" data-lecteur>
                    <div class="modal-head">
                        <h2>{{ collect($results)->firstWhere('id', $playing)['title'] ?? '' }}</h2>

                        <button type="button" wire:click="closePlayer" class="icon-btn"
                                style="margin-left: auto" aria-label="Fermer le lecteur">
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

            {{-- Squelettes au même gabarit que les cartes : la grille garde sa
                 forme au lieu de sauter d'un écran vide à vingt cartes. --}}
            <div wire:loading wire:target="search, pick" class="grid">
                @for ($i = 0; $i < 8; $i++)
                    <div class="skeleton">
                        <div class="sk-thumb"></div>
                        <div class="sk-line"></div>
                        <div class="sk-line short"></div>
                    </div>
                @endfor
            </div>

            <div wire:loading.remove wire:target="search, pick" class="stack">
                @if ($searched && $error === '')
                    <div class="toolbar">
                        <p class="summary">
                            <strong>{{ count($affichables) }}</strong>
                            clip{{ count($affichables) > 1 ? 's' : '' }}
                            @if (count($affichables) !== $trouves)
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
                                trouvé{{ $trouves > 1 ? 's' : '' }}, mais les filtres les écartent
                                tous. Baissez les vues minimales ou coupez la limite de durée.
                            @else
                                <strong>Rien sur cette période</strong>
                                Élargissez la période, ou coupez la limite de durée : les meilleurs
                                moments dépassent souvent la minute.
                            @endif
                        </div>
                    @else
                        <div class="grid">
                            @foreach ($affichables as $clip)
                                @include('livewire.partials.clip-card', [
                                    'clip' => $clip,
                                    'garde' => in_array($clip['id'], $dejaPris, true),
                                ])
                            @endforeach
                        </div>
                    @endif
                @elseif ($error === '')
                    <div class="surface empty">
                        <strong>Choisissez un streamer</strong>
                        Cliquez un direct dans la colonne de gauche, ou tapez un pseudo.
                        « Tout le site » montre les clips les plus vus du moment, toutes chaînes
                        confondues.
                    </div>
                @endif
            </div>

            {{--
                Mes fichiers.

                Rafraîchi toutes les cinq secondes, et seulement s'il y a un
                travail en cours : un sondage permanent sur une page ouverte
                toute la journée coûterait une requête toutes les cinq
                secondes pour rien.
            --}}
            <div class="stack"
                 @if ($fichiers->contains(fn ($f) => $f->enTravail())) wire:poll.5s @endif>

                <div class="toolbar">
                    <p class="summary">
                        <strong>Mes fichiers</strong>
                        {{ $fichiers->count() }} source{{ $fichiers->count() > 1 ? 's' : '' }} ·
                        {{ number_format($espace->occupeOctets() / 1024 / 1024, 0, ',', ' ') }}
                        sur {{ config('clipping.media.quota_mo') }} Mo
                    </p>

                    <div class="actions">
                        <span class="pill {{ $espace->resteOctets() > 0 ? '' : 'bad' }}">
                            {{ number_format($espace->resteOctets() / 1024 / 1024, 0, ',', ' ') }} Mo libres
                        </span>
                        <span class="pill">effacés après {{ config('clipping.media.purge_days') }} jours</span>
                    </div>
                </div>

                @if ($fichiers->isEmpty())
                    <div class="surface empty">
                        <strong>Aucun fichier</strong>
                        Depuis votre sélection, cliquez « Récupérer le mp4 » : le serveur le
                        télécharge en arrière-plan, puis vous le reprenez ici pour le monter.
                    </div>
                @else
                    <div class="surface">
                        <table class="files">
                            <tbody>
                                @foreach ($fichiers as $fichier)
                                    <tr>
                                        <td>
                                            <div class="fname">{{ $fichier->label }}</div>
                                            <div class="meta">
                                                @if ($fichier->estPret())
                                                    {{ $fichier->tailleLisible() }}
                                                    @if ($fichier->duration_seconds)
                                                        · {{ $fichier->dureeLisible() }}
                                                    @endif
                                                    · expire le {{ $fichier->expires_at?->format('d/m') }}
                                                @elseif ($fichier->aEchoue())
                                                    <span style="color: var(--err)">{{ $fichier->error }}</span>
                                                @else
                                                    {{ $fichier->libelleStatut() }}…
                                                @endif
                                            </div>
                                        </td>

                                        <td class="right">
                                            @if ($fichier->estPret())
                                                <a href="{{ route('media.download', $fichier) }}" class="btn ghost sm">
                                                    Télécharger
                                                </a>
                                            @elseif ($fichier->aEchoue())
                                                <button type="button" wire:click="retryDownload({{ $fichier->id }})"
                                                        class="btn ghost sm">Réessayer</button>
                                            @endif

                                            <button type="button" wire:click="deleteFile({{ $fichier->id }})"
                                                    class="icon-btn" aria-label="Supprimer">
                                                <svg class="ico" viewBox="0 0 16 16" aria-hidden="true">
                                                    <path d="M3 5h10M6.5 5V3.5h3V5M4.5 5l.6 8h5.8l.6-8" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- La sélection. Par clippeur, et visible de lui seul : lire la
                 liste de repérage d'un concurrent serait un avantage qu'aucun
                 règlement ne rattrape. --}}
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
                        Mettez un clip de côté depuis les résultats : vous le retrouverez ici
                        pour en récupérer le fichier.
                    </div>
                @else
                    <div class="grid">
                        @foreach ($selection as $garde)
                            @php $fichier = $garde->media(); @endphp

                            <article @class(['card', 'done' => $garde->isTaken()])>
                                <a href="{{ $garde->url }}" target="_blank" rel="noopener" class="thumb">
                                    @if ($garde->thumbnail_url)
                                        <img src="{{ $garde->thumbnail_url }}" alt="" loading="lazy">
                                    @endif
                                    <span class="badge views">{{ Money::views($garde->views) }} vues</span>
                                    <span class="badge dur">{{ $garde->dureeLisible() }}</span>
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

                                    @if ($fichier?->aEchoue())
                                        <p class="hint" style="color: var(--err)">{{ $fichier->error }}</p>
                                    @elseif ($fichier?->enTravail())
                                        <p class="hint">Téléchargement {{ mb_strtolower($fichier->libelleStatut()) }}…</p>
                                    @elseif ($fichier?->estPret())
                                        <p class="hint">Fichier prêt · {{ $fichier->tailleLisible() }}</p>
                                    @endif

                                    <div class="card-actions">
                                        @if ($fichier?->estPret())
                                            <a href="{{ route('media.download', $fichier) }}" class="btn primary">
                                                Télécharger
                                            </a>
                                        @elseif ($fichier?->aEchoue())
                                            <button type="button" wire:click="retryDownload({{ $fichier->id }})"
                                                    class="btn ghost">Réessayer</button>
                                        @elseif ($fichier?->enTravail())
                                            {{-- Désactivé plutôt que masqué : le bouton garde sa
                                                 place, la carte ne saute pas quand l'état change. --}}
                                            <span class="btn ghost" aria-disabled="true">En cours…</span>
                                        @else
                                            <button type="button" wire:click="download({{ $garde->id }})"
                                                    class="btn ghost">Récupérer le mp4</button>
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
    </main>
</div>
