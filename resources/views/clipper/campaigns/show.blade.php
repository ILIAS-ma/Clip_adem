@php
    use App\Support\Money;

    $cover = $campaign->coverUrl();
    $platforms = $campaign->rates->where('is_enabled', true);
    $bestRate = $platforms->max('rate_per_1k_cents');
    $minClips = (int) $campaign->min_clips_per_clipper;
    $valides = $minClips > 0 ? $campaign->payableClipCountFor(auth()->id()) : 0;
    $isOver = $campaign->ends_at && $campaign->ends_at->isPast();
@endphp

<x-app-layout>
    <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">

        <a href="{{ route('campaigns.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-400 transition hover:text-ink-50">
            ← Toutes les campagnes
        </a>

        {{--
            En-tête de campagne.

            La couverture est montrée en entier, comme une pochette, et la même
            image floutée habille le fond : quel que soit son format, elle reste
            nette et ne mange pas la page.
        --}}
        <section class="relative isolate overflow-hidden rounded-3xl border border-ink-700 bg-ink-900">
            @if ($cover)
                <img src="{{ $cover }}" alt="" aria-hidden="true"
                     class="absolute inset-0 -z-10 h-full w-full scale-125 object-cover opacity-50 blur-3xl">
            @else
                <div aria-hidden="true" class="absolute -left-20 -top-24 -z-10 h-72 w-72 rounded-full bg-brand-500/25 blur-3xl"></div>
            @endif
            <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-t from-ink-950 via-ink-950/75 to-ink-950/40"></div>

            <div class="flex flex-col gap-6 p-6 sm:flex-row sm:items-end sm:gap-8 sm:p-8">
                @if ($cover)
                    <img src="{{ $cover }}" alt="{{ $campaign->title }}"
                         class="h-36 w-36 flex-none rounded-2xl object-cover shadow-2xl ring-1 ring-white/10 sm:h-44 sm:w-44">
                @else
                    <div class="flex h-36 w-36 flex-none items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 font-display text-6xl font-bold text-white shadow-2xl sm:h-44 sm:w-44">
                        {{ mb_strtoupper(mb_substr($campaign->creator?->name ?? $campaign->title, 0, 1)) }}
                    </div>
                @endif

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="chip bg-brand-500/20 text-brand-200">{{ $campaign->isContest() ? 'Classement' : 'Payée aux vues' }}</span>
                        <span @class([
                            'chip',
                            'bg-emerald-500/15 text-emerald-300' => $campaign->isOpenForClips(),
                            'bg-ink-700 text-ink-300' => ! $campaign->isOpenForClips(),
                        ])>{{ $campaign->isOpenForClips() ? 'En cours' : ($isOver ? 'Terminée' : $campaign->status->label()) }}</span>
                        @foreach ($platforms as $rate)
                            <span class="chip bg-white/10 text-ink-100">{{ $rate->platform->label() }}</span>
                        @endforeach
                    </div>

                    <p class="mt-4 text-sm font-semibold uppercase tracking-wide text-ink-300">
                        {{ $campaign->creator?->name }}
                        @if ($campaign->creator?->isStreamer())
                            <span class="ms-1 normal-case tracking-normal text-ink-400">· Streamer</span>
                        @endif
                    </p>
                    <h1 class="mt-1 font-display text-3xl font-bold leading-tight text-ink-50 sm:text-4xl">{{ $campaign->title }}</h1>

                    {{-- Les trois chiffres qu'on vient chercher, lisibles sans rien ouvrir. --}}
                    <dl class="mt-6 grid grid-cols-2 gap-4 sm:flex sm:flex-wrap sm:gap-8">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ $campaign->isContest() ? 'À gagner' : 'Rémunération' }}</dt>
                            <dd class="mt-1 font-display text-2xl font-bold tabular text-ink-50">
                                @if ($campaign->isContest())
                                    @if ($campaign->prizePoolCents() > 0)
                                        {{ Money::wholeEuros($campaign->prizePoolCents()) }}
                                    @elseif ($campaign->allRewards() !== [])
                                        {{ count($campaign->allRewards()) }} lot{{ count($campaign->allRewards()) > 1 ? 's' : '' }}
                                    @else
                                        La gloire
                                    @endif
                                @elseif ($bestRate)
                                    {{ Money::rate($bestRate) }}<span class="text-sm font-semibold text-ink-400"> / 1000 vues</span>
                                @endif
                            </dd>
                        </div>

                        @if ($campaign->ends_at)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ $isOver ? 'Terminée' : 'Fin' }}</dt>
                                <dd class="mt-1 font-display text-2xl font-bold text-ink-50">
                                    {{ $isOver ? $campaign->ends_at->format('d/m') : $campaign->ends_at->diffForHumans(['parts' => 1]) }}
                                </dd>
                            </div>
                        @endif

                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-ink-400">Participants</dt>
                            <dd class="mt-1 font-display text-2xl font-bold tabular text-ink-50">{{ $participantsCount }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

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
                    <p class="font-semibold">Ouvre le {{ $opensAt->format('d/m/Y à H\hi') }} pour vous</p>
                    <p class="mt-1 leading-relaxed">
                        Les niveaux élevés y accèdent en avance. Le budget partant au premier arrivé,
                        cette avance compte.
                    </p>
                @endif
            </div>
        @endif

        {{-- Le volume minimum, dit en clair : quelqu'un qui voit ses vues monter
             sans que ses gains bougent cherche une panne, pas une règle. --}}
        @if ($minClips > 0 && $valides < $minClips)
            <div class="alert-warn">
                <p class="font-semibold">{{ $minClips }} clips soumis avant d'être rémunéré — vous en avez {{ $valides }}</p>
                <p class="mt-1 leading-relaxed">
                    Vos vues sont comptées dès maintenant, mais rien n'est crédité avant le
                    {{ $minClips }}<sup>e</sup> clip. Dès qu'il est soumis, tout l'arriéré est rattrapé d'un coup.
                </p>
            </div>
        @endif

        @if ($campaign->isContest())
            @include('clipper.campaigns.partials.contest', ['campaign' => $campaign, 'boards' => $boards])
        @endif

        <div class="grid gap-6 lg:grid-cols-3">

            <div class="space-y-6 lg:col-span-2">
                <div class="card p-6 sm:p-8">
                    <h2 class="font-display text-lg font-bold text-ink-50">Le brief</h2>
                    <p class="mt-4 whitespace-pre-line leading-relaxed text-ink-200">{{ $campaign->brief }}</p>

                    @if ($campaign->required_hashtags)
                        <div class="mt-6 rounded-2xl border border-ink-700 bg-ink-900 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Hashtags obligatoires</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($campaign->required_hashtags as $hashtag)
                                    <span class="chip bg-brand-500/15 text-brand-300">{{ $hashtag }}</span>
                                @endforeach
                            </div>
                            <p class="hint">Leur absence sera signalée à la modération.</p>
                        </div>
                    @endif
                </div>

                {{-- Un streamer ne joint pas d'extrait : il produit des heures de
                     direct. Sans ce lien, le brief parle de moments introuvables. --}}
                @if ($campaign->creator?->isStreamer() && $campaign->creator->vod_url)
                    <div class="card p-6 sm:p-8">
                        <h2 class="font-display text-lg font-bold text-ink-50">Où trouver la matière</h2>
                        <p class="mt-1 text-sm text-ink-400">
                            Les rediffusions de {{ $campaign->creator->name }}. C'est là que se prennent les moments à clipper.
                        </p>

                        <a href="{{ $campaign->creator->vod_url }}" target="_blank" rel="noopener" class="btn-ghost mt-5 inline-flex">
                            Voir les rediffusions ↗
                        </a>

                        @if ($campaign->creator->twitch_handle || $campaign->creator->kick_handle)
                            <p class="mt-4 flex flex-wrap items-center gap-2 text-sm text-ink-400">
                                <span>En direct :</span>
                                @if ($campaign->creator->twitch_handle)
                                    <a href="https://twitch.tv/{{ $campaign->creator->twitch_handle }}" target="_blank" rel="noopener"
                                       class="font-semibold text-ink-100 underline underline-offset-2">Twitch</a>
                                @endif
                                @if ($campaign->creator->kick_handle)
                                    <a href="https://kick.com/{{ $campaign->creator->kick_handle }}" target="_blank" rel="noopener"
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
                            Le son, les rushes et les visuels fournis par le créateur. Ce qui est marqué « imposé » est vérifié en modération.
                        </p>

                        <div class="mt-5 space-y-4">
                            @foreach ($campaign->assets as $asset)
                                <x-campaign-asset :asset="$asset" />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($clips->isNotEmpty())
                    <div class="card overflow-hidden">
                        <div class="flex items-center justify-between border-b border-ink-700 px-6 py-4">
                            <h2 class="font-display text-lg font-bold text-ink-50">Mes clips</h2>
                            <span class="text-sm text-ink-400">{{ $clips->count() }} clip{{ $clips->count() > 1 ? 's' : '' }}</span>
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
                                            {{ $clip->status->label() }}@if ($clip->rejection_reason) — {{ $clip->rejection_reason }}@endif
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        @unless ($campaign->isContest())
                                            <p class="font-display font-bold tabular text-ink-50">{{ Money::euros($clip->earned_cents) }}</p>
                                        @endunless
                                        <p @class(['tabular', 'text-xs text-ink-400' => ! $campaign->isContest(), 'font-display font-bold text-ink-50' => $campaign->isContest()])>
                                            {{ Money::views($clip->views_total) }} vues
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- Colonne d'action, qui suit le défilement : participer ou
                 soumettre reste toujours à portée de main. --}}
            <aside class="space-y-6 lg:sticky lg:top-24 lg:self-start">
                <div class="card p-6">
                    @if ($participations->isEmpty())
                        <h2 class="font-display text-lg font-bold text-ink-50">Participer</h2>

                        @if ($isOpen)
                            <p class="mt-1 text-sm text-ink-300">Choisissez le compte avec lequel vous publierez, et c'est parti.</p>
                            <div class="mt-4">
                                @livewire('join-campaign', ['campaign' => $campaign])
                            </div>
                        @else
                            <p class="mt-2 rounded-xl bg-ink-900 p-3 text-sm text-ink-300">
                                {{ ! $campaign->isContest() && $remainingCents <= 0
                                    ? "Le budget de cette campagne est épuisé : elle n'accepte plus de participants."
                                    : "Cette campagne n'accepte plus de nouveaux participants." }}
                            </p>
                        @endif
                    @else
                        <h2 class="font-display text-lg font-bold text-ink-50">Soumettre un clip</h2>

                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($participations as $participation)
                                <span class="chip-ok">
                                    {{ $participation->socialAccount?->platform->label() }}@if ($participation->socialAccount?->handle) · &#64;{{ $participation->socialAccount->handle }}@endif
                                </span>
                            @endforeach
                        </div>

                        @if ($canSubmit)
                            <p class="mt-3 text-sm text-ink-300">Publiez depuis votre compte, puis collez ici le lien de la publication.</p>
                            @livewire('submit-clip', ['campaign' => $campaign])
                        @elseif ($opensAt)
                            <p class="mt-3 text-sm text-ink-300">
                                La campagne ouvre le {{ $campaign->starts_at->format('d/m/Y à H\hi') }}. Préparez votre clip : vous pourrez le soumettre dès l'ouverture.
                            </p>
                        @else
                            <p class="mt-3 text-sm text-ink-300">Cette campagne n'accepte plus de nouveaux clips. Ceux déjà soumis continuent d'être suivis.</p>
                        @endif

                        @if ($isOpen)
                            {{-- Engager un compte de plus, s'il en reste un. --}}
                            <details class="mt-4 text-sm">
                                <summary class="cursor-pointer text-ink-400 hover:text-ink-100">Ajouter un autre compte</summary>
                                <div class="mt-3">@livewire('join-campaign', ['campaign' => $campaign])</div>
                            </details>
                        @endif
                    @endif
                </div>

                <div class="card p-6">
                    <h2 class="font-display text-base font-bold text-ink-50">En bref</h2>

                    @unless ($campaign->isContest())
                        <div class="mt-4">
                            <x-budget-bar :campaign="$campaign" :remaining="$remainingCents" />
                        </div>
                    @endunless

                    <dl class="mt-4 space-y-3 text-sm">
                        @foreach ($platforms as $rate)
                            <div class="flex items-baseline justify-between gap-2">
                                <dt class="text-ink-400">{{ $rate->platform->label() }}</dt>
                                <dd class="font-semibold tabular text-ink-50">
                                    {{ $campaign->isContest() ? 'acceptée' : Money::rate($rate->rate_per_1k_cents).' / 1000' }}
                                </dd>
                            </div>
                        @endforeach

                        @if ($campaign->isContest())
                            {{-- Deux classements, deux règles : le seuil de vues ne
                                 vaut que pour le classement du nombre de clips. --}}
                            <div class="flex justify-between gap-3 border-t border-ink-700 pt-3">
                                <dt class="text-ink-400">Classement des vues</dt>
                                <dd class="text-right text-ink-100">toutes les vues comptent</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-ink-400">Classement des clips</dt>
                                <dd class="text-right text-ink-100">
                                    @if ($campaign->postMinViews() > 0)
                                        un clip compte dès <span class="tabular font-semibold">{{ Money::views($campaign->postMinViews()) }}</span> vues
                                    @else
                                        chaque clip validé compte
                                    @endif
                                </dd>
                            </div>
                        @endif

                        @if ($campaign->min_views_per_clip > 0)
                            <div class="flex justify-between gap-2 border-t border-ink-700 pt-3">
                                <dt class="text-ink-400">Vues minimum</dt>
                                <dd class="tabular text-ink-100">{{ Money::views($campaign->min_views_per_clip) }}</dd>
                            </div>
                        @endif

                        @if ($minClips > 0)
                            <div class="flex justify-between gap-2">
                                <dt class="text-ink-400">Clips minimum</dt>
                                <dd class="tabular {{ $valides >= $minClips ? 'text-ink-100' : 'text-amber-300' }}">{{ $valides }} / {{ $minClips }}</dd>
                            </div>
                        @endif

                        @if ($campaign->max_payout_per_clip_cents)
                            <div class="flex justify-between gap-2">
                                <dt class="text-ink-400">Plafond par clip</dt>
                                <dd class="tabular text-ink-100">{{ Money::euros($campaign->max_payout_per_clip_cents) }}</dd>
                            </div>
                        @endif

                        @if (($slotsLeft = $campaign->launchBonusSlotsLeft()) !== null)
                            <div class="flex justify-between gap-2 border-t border-ink-700 pt-3">
                                <dt class="text-ink-400">Prime de lancement</dt>
                                <dd class="text-right">
                                    <span class="tabular font-semibold text-brand-400">{{ Money::euros($campaign->launch_bonus_cents) }}</span>
                                    <span class="block text-xs text-ink-400">
                                        {{ $slotsLeft > 0
                                            ? $slotsLeft.' place'.($slotsLeft > 1 ? 's' : '').' restante'.($slotsLeft > 1 ? 's' : '')
                                            : 'Toutes les places sont prises' }}
                                    </span>
                                </dd>
                            </div>
                        @endif

                        @if ($campaign->starts_at)
                            <div class="flex justify-between gap-2 border-t border-ink-700 pt-3">
                                <dt class="text-ink-400">Début</dt>
                                <dd class="text-ink-100">{{ $campaign->starts_at->format('d/m/Y à H\hi') }}</dd>
                            </div>
                        @endif

                        @if ($campaign->ends_at)
                            <div class="flex justify-between gap-2 {{ $campaign->starts_at ? '' : 'border-t border-ink-700 pt-3' }}">
                                <dt class="text-ink-400">Fin</dt>
                                <dd class="text-ink-100">{{ $campaign->ends_at->format('d/m/Y à H\hi') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
