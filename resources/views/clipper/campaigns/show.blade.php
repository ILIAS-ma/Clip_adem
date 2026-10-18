@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-ink-400">
                    <span>{{ $campaign->creator?->name }}</span>
                    @if ($campaign->creator?->isStreamer())
                        <span class="chip-neutral normal-case tracking-normal">Streamer</span>
                    @endif
                </p>
                <h1 class="mt-1 font-display text-2xl font-bold text-ink-50">{{ $campaign->title }}</h1>
            </div>

            <a href="{{ route('campaigns.index') }}" wire:navigate class="btn-ghost">← Toutes les campagnes</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        @if ($cover = $campaign->coverUrl())
            <div class="card overflow-hidden">
                <img src="{{ $cover }}" alt="{{ $campaign->title }}" class="aspect-[21/9] w-full object-cover">
            </div>
        @endif

        @if (session('status'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        @if ($opensAt)
            {{-- Le budget partant au premier arrivé, savoir qu'on peut se
                 positionner avant l'ouverture est l'information la plus utile
                 de la page. --}}
            <div class="{{ $isOpen ? 'alert-ok' : 'alert-warn' }}">
                @if ($isOpen)
                    <p class="font-semibold">Accès anticipé — vous pouvez rejoindre dès maintenant</p>
                    <p class="mt-1 leading-relaxed">
                        La campagne n'ouvre officiellement que le
                        {{ $campaign->starts_at->format('d/m/Y à H\hi') }}. Votre niveau vous permet de
                        prendre position avant les autres.
                    </p>
                @else
                    <p class="font-semibold">
                        Ouvre le {{ $opensAt->format('d/m/Y à H\hi') }} pour vous
                    </p>
                    <p class="mt-1 leading-relaxed">
                        Les niveaux élevés y accèdent en avance. Le budget partant au premier arrivé,
                        cette avance compte.
                    </p>
                @endif
            </div>
        @endif

        {{--
            Le volume minimum, dit en clair et au-dessus de tout le reste.

            La ligne du tableau à droite ne suffit pas : quelqu'un qui voit ses
            vues monter sans que ses gains bougent cherche une panne, pas une
            règle. Autant la lui rappeler là où il regarde.
        --}}
        @php
            $minClips = (int) $campaign->min_clips_per_clipper;
            $valides = $minClips > 0 ? $campaign->payableClipCountFor(auth()->id()) : 0;
        @endphp

        @if ($minClips > 0 && $valides < $minClips)
            <div class="alert-warn">
                <p class="font-semibold">
                    {{ $minClips }} clips validés avant d'être rémunéré —
                    vous en avez {{ $valides }}
                </p>
                <p class="mt-1 leading-relaxed">
                    Vos vues sont comptées dès maintenant, mais rien n'est crédité avant le
                    {{ $minClips }}<sup>e</sup> clip validé. Dès qu'il l'est, tout l'arriéré des
                    précédents est rattrapé d'un coup : rien n'est perdu, tout est différé.
                </p>
            </div>
        @endif

        {{-- Les deux classements en pleine largeur, côte à côte : dans la
             colonne latérale, deux tableaux n'auraient pas tenu. --}}
        @if ($campaign->isContest())
            @include('clipper.campaigns.partials.contest', ['campaign' => $campaign, 'boards' => $boards])
        @endif

        <div class="grid gap-6 lg:grid-cols-3">

            <div class="space-y-6 lg:col-span-2">
                <div class="card p-6 sm:p-8">
                    <h2 class="font-display text-lg font-bold text-ink-50">Le brief</h2>
                    <p class="mt-4 whitespace-pre-line leading-relaxed text-ink-200">{{ $campaign->brief }}</p>

                    @if ($campaign->required_hashtags)
                        <div class="mt-6">
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">
                                Hashtags obligatoires
                            </p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($campaign->required_hashtags as $hashtag)
                                    <span class="chip bg-brand-500/15 text-brand-300">{{ $hashtag }}</span>
                                @endforeach
                            </div>
                            <p class="hint">Leur absence sera signalée à la modération.</p>
                        </div>
                    @endif

                </div>

                {{--
                    Un streamer ne joint pas un extrait de trente secondes : il
                    produit des heures de direct, et la source est la même d'une
                    campagne à l'autre. Sans ce lien, le clippeur lit un brief
                    qui parle de moments qu'il n'a aucun moyen de retrouver.
                --}}
                @if ($campaign->creator?->isStreamer() && $campaign->creator->vod_url)
                    <div class="card p-6 sm:p-8">
                        <h2 class="font-display text-lg font-bold text-ink-50">Où trouver la matière</h2>
                        <p class="mt-1 text-sm text-ink-400">
                            Les rediffusions de {{ $campaign->creator->name }}. C'est là que se
                            prennent les moments à clipper.
                        </p>

                        <a href="{{ $campaign->creator->vod_url }}" target="_blank" rel="noopener"
                           class="btn-ghost mt-5 inline-flex">
                            Voir les rediffusions ↗
                        </a>

                        @if ($campaign->creator->twitch_handle || $campaign->creator->kick_handle)
                            <p class="mt-4 flex flex-wrap items-center gap-2 text-sm text-ink-400">
                                <span>En direct :</span>
                                @if ($campaign->creator->twitch_handle)
                                    <a href="https://twitch.tv/{{ $campaign->creator->twitch_handle }}"
                                       target="_blank" rel="noopener"
                                       class="font-semibold text-ink-100 underline underline-offset-2">Twitch</a>
                                @endif
                                @if ($campaign->creator->kick_handle)
                                    <a href="https://kick.com/{{ $campaign->creator->kick_handle }}"
                                       target="_blank" rel="noopener"
                                       class="font-semibold text-ink-100 underline underline-offset-2">Kick</a>
                                @endif
                            </p>
                        @endif
                    </div>
                @endif

                @if ($campaign->assets->isNotEmpty())
                    <div class="card p-6 sm:p-8">
                        <h2 class="font-display text-lg font-bold text-ink-50">Matière première</h2>
                        <p class="mt-1 text-sm text-ink-400">
                            Le son, les rushes et les visuels fournis par le créateur. Écoutez et
                            regardez avant de tourner : ce qui est marqué « imposé » est vérifié
                            en modération.
                        </p>

                        <div class="mt-5 space-y-4">
                            @foreach ($campaign->assets as $asset)
                                <x-campaign-asset :asset="$asset" />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($participations->isNotEmpty())
                    <div class="card p-6 sm:p-8">
                        <h2 class="font-display text-lg font-bold text-ink-50">Soumettre un clip</h2>

                        @if ($canSubmit)
                            <p class="mt-2 text-sm text-ink-300">
                                Publiez depuis votre compte, puis collez ici l'adresse de la publication.
                            </p>
                            @livewire('submit-clip', ['campaign' => $campaign])
                        @elseif ($opensAt)
                            <p class="mt-3 text-sm text-ink-300">
                                La campagne ouvre le {{ $campaign->starts_at->format('d/m/Y à H\hi') }}.
                                Préparez votre clip : vous pourrez le soumettre dès l'ouverture.
                            </p>
                        @else
                            <p class="mt-3 text-sm text-ink-300">
                                Cette campagne n'accepte plus de nouveaux clips. Ceux déjà soumis
                                continuent d'être suivis.
                            </p>
                        @endif
                    </div>
                @endif

                @if ($clips->isNotEmpty())
                    <div class="card">
                        <div class="border-b border-ink-700 px-6 py-4">
                            <h2 class="font-display text-lg font-bold text-ink-50">Mes clips sur cette campagne</h2>
                        </div>
                        <ul class="divide-y divide-ink-700">
                            @foreach ($clips as $clip)
                                <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                                    <div class="min-w-0">
                                        <a href="{{ route('clips.show', $clip) }}" wire:navigate
                                           class="block truncate font-semibold text-ink-50 underline-offset-2 hover:underline">
                                            {{ $clip->platform->label() }} · {{ $clip->external_post_id }}
                                        </a>
                                        <p class="mt-0.5 text-sm text-ink-400">
                                            {{ $clip->status->label() }}
                                            @if ($clip->rejection_reason)
                                                — {{ $clip->rejection_reason }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-display font-bold tabular text-ink-50">{{ Money::euros($clip->earned_cents) }}</p>
                                        <p class="text-xs tabular text-ink-400">{{ Money::views($clip->views_total) }} vues</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="space-y-6">
                <div class="card p-6">
                    @unless ($campaign->isContest())
                        <x-budget-bar :campaign="$campaign" :remaining="$remainingCents" />
                    @endunless

                    <dl @class(['space-y-3 text-sm', 'mt-6 border-t border-ink-700 pt-5' => ! $campaign->isContest()])>
                        @foreach ($campaign->rates->where('is_enabled', true) as $rate)
                            <div class="flex items-baseline justify-between gap-2">
                                <dt class="text-ink-400">{{ $rate->platform->label() }}</dt>
                                <dd class="font-display font-bold tabular text-ink-50">
                                    @if ($campaign->isContest())
                                        <span class="text-sm font-medium text-ink-300">acceptée</span>
                                    @else
                                        {{ Money::rate($rate->rate_per_1k_cents) }}<span class="text-xs font-medium text-ink-400"> /1000</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach

                        @if ($campaign->min_views_per_clip > 0)
                            <div class="flex justify-between gap-2 border-t border-ink-700 pt-3">
                                <dt class="text-ink-400">Vues minimum</dt>
                                <dd class="tabular text-ink-100">{{ Money::views($campaign->min_views_per_clip) }}</dd>
                            </div>
                        @endif

                        @if ($minClips > 0)
                            {{-- La progression, pas seulement l'exigence : « 5
                                 clips minimum » laisse compter de tête, et se
                                 tromper. --}}
                            <div class="flex justify-between gap-2">
                                <dt class="text-ink-400">Clips minimum</dt>
                                <dd class="tabular {{ $valides >= $minClips ? 'text-ink-100' : 'text-amber-300' }}">
                                    {{ $valides }} / {{ $minClips }}
                                </dd>
                            </div>
                        @endif

                        {{-- Les plafonds sont annoncés d'emblée : les découvrir
                             quand les gains cessent de monter fait croire à un bug. --}}
                        @if ($campaign->max_payout_per_clip_cents)
                            <div class="flex justify-between gap-2">
                                <dt class="text-ink-400">Plafond par clip</dt>
                                <dd class="tabular text-ink-100">{{ Money::euros($campaign->max_payout_per_clip_cents) }}</dd>
                            </div>
                        @endif

                        @if (($slotsLeft = $campaign->launchBonusSlotsLeft()) !== null)
                            {{-- Annoncée avec les places restantes : une prime
                                 « pour les premiers » sans compteur pousse à
                                 publier vite sans savoir s'il reste une chance. --}}
                            <div class="flex justify-between gap-2 border-t border-ink-700 pt-3">
                                <dt class="text-ink-400">Prime de lancement</dt>
                                <dd class="text-right">
                                    <span class="tabular font-semibold text-brand-400">{{ Money::euros($campaign->launch_bonus_cents) }}</span>
                                    <span class="block text-xs text-ink-400">
                                        {{ $slotsLeft > 0
                                            ? $slotsLeft.' place'.($slotsLeft > 1 ? 's' : '').' restante'.($slotsLeft > 1 ? 's' : '').' · au premier clip validé'
                                            : 'Toutes les places sont prises' }}
                                    </span>
                                </dd>
                            </div>
                        @endif

                        @if ($campaign->ends_at)
                            <div class="flex justify-between gap-2 border-t border-ink-700 pt-3">
                                <dt class="text-ink-400">Fin</dt>
                                <dd class="text-ink-100">{{ $campaign->ends_at->format('d/m/Y') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="card p-6">
                    <h2 class="font-display text-lg font-bold text-ink-50">Participation</h2>

                    @forelse ($participations as $participation)
                        <div class="mt-4 rounded-xl bg-ink-800 p-3">
                            <p class="font-semibold text-ink-50">
                                {{ $participation->socialAccount?->platform->label() }}
                                @if ($participation->socialAccount?->handle)
                                    · &#64;{{ $participation->socialAccount->handle }}
                                @endif
                            </p>
                            <span class="{{ $participation->status->value === 'approved' ? 'chip-ok' : 'chip-wait' }} mt-1.5">
                                {{ $participation->status->label() }}
                            </span>
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-ink-300">Vous ne participez pas encore à cette campagne.</p>
                    @endforelse

                    @if ($isOpen)
                        <div class="mt-5">
                            @livewire('join-campaign', ['campaign' => $campaign])
                        </div>
                    @elseif ($participations->isEmpty())
                        <p class="mt-4 rounded-xl bg-ink-800 p-3 text-sm text-ink-300">
                            {{ $remainingCents <= 0
                                ? "Le budget de cette campagne est épuisé : elle n'accepte plus de participants."
                                : "Cette campagne n'accepte plus de nouveaux participants." }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
