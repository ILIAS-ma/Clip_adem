@php
    use App\Support\Money;

    $cover = $campaign->coverUrl();
    $platforms = $campaign->rates->where('is_enabled', true);
    $bestRate = $platforms->max('rate_per_1k_cents');
    $minClips = (int) $campaign->min_clips_per_clipper;
    $valides = $minClips > 0 ? $campaign->payableClipCountFor(auth()->id()) : 0;
    $isOver = $campaign->hasEnded();
    /*
     * « Rejoint » ne veut pas dire « peut poster » : un compte lié avec lequel on a
     * rejoint peut être devenu inutilisable (délié, jeton mort). Ce qui compte, c'est
     * d'avoir au moins un compte utilisable — déjà engagé, ou encore à engager, car
     * choisir une vidéo d'un compte l'engage.
     */
    $joined = $participations->contains(fn ($p) => $p->socialAccount?->isSyncable()) || $eligibleAccounts->isNotEmpty();
    $canPick = $joined && $canSubmit;
@endphp

{{--
    La page d'une campagne, pensée pour le téléphone d'abord.

    L'essentiel en haut — ce qu'on gagne, quand ça finit — puis trois onglets
    au lieu d'un long défilé : le classement, le brief, ses clips. L'action qui
    compte, soumettre ses vidéos, reste collée en bas de l'écran.
--}}
<x-app-layout>
    <div @class(['mx-auto max-w-6xl space-y-5 px-4 pt-4 sm:px-6 lg:px-8', 'pb-28 lg:pb-6' => $canPick, 'pb-6' => ! $canPick])
         x-data="{ tab: '{{ session('campaign_tab', $campaign->isContest() ? 'classement' : 'brief') }}' }"
         x-init="if (location.hash === '#mes-clips') tab = 'clips'">

        <a href="{{ route('campaigns.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-sm font-medium text-ink-400 transition hover:text-ink-50">
            ← Campagnes
        </a>

        {{-- En-tête --}}
        <section class="relative isolate overflow-hidden rounded-3xl border border-ink-700 bg-ink-900 p-4 sm:p-6">
            @if ($cover)
                <img src="{{ $cover }}" alt="" aria-hidden="true"
                     class="absolute inset-0 -z-10 h-full w-full scale-125 object-cover opacity-40 blur-3xl">
            @else
                <div aria-hidden="true" class="absolute -left-16 -top-20 -z-10 h-60 w-60 rounded-full bg-brand-500/25 blur-3xl"></div>
            @endif
            <div aria-hidden="true" class="absolute inset-0 -z-10 bg-gradient-to-t from-ink-950 via-ink-950/80 to-ink-950/50"></div>

            <div class="flex items-center gap-4">
                @if ($cover)
                    <img src="{{ $cover }}" alt="{{ $campaign->title }}"
                         class="h-20 w-20 flex-none rounded-2xl object-cover shadow-xl ring-1 ring-white/10 sm:h-24 sm:w-24">
                @else
                    <div class="flex h-20 w-20 flex-none items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 font-display text-4xl font-bold text-white shadow-xl sm:h-24 sm:w-24">
                        {{ mb_strtoupper(mb_substr($campaign->creator?->name ?? $campaign->title, 0, 1)) }}
                    </div>
                @endif

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span @class([
                            'chip',
                            'bg-emerald-500/15 text-emerald-300' => $campaign->isOpenForClips(),
                            'bg-ink-700 text-ink-300' => ! $campaign->isOpenForClips(),
                        ])>{{ $campaign->isOpenForClips() ? 'En cours' : ($isOver ? 'Terminée' : $campaign->status->label()) }}</span>
                        <span class="chip bg-brand-500/20 text-brand-200">{{ $campaign->isContest() ? 'Classement' : 'Payée aux vues' }}</span>
                    </div>
                    <p class="mt-2 truncate text-xs font-semibold uppercase tracking-wide text-ink-300">{{ $campaign->creator?->name }}</p>
                    <h1 class="font-display text-xl font-bold leading-tight text-ink-50 sm:text-3xl">{{ $campaign->title }}</h1>
                </div>
            </div>

            {{-- Les trois chiffres qu'on vient chercher. --}}
            <dl class="mt-4 grid grid-cols-3 divide-x divide-ink-700/70 rounded-2xl bg-ink-950/50 py-3 text-center ring-1 ring-white/5">
                <div class="px-2">
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-ink-400">{{ $campaign->isContest() ? 'À gagner' : 'Par 1000 vues' }}</dt>
                    <dd class="mt-0.5 font-display text-lg font-bold tabular text-ink-50 sm:text-2xl">
                        @if ($campaign->isContest())
                            @if ($campaign->prizePoolCents() > 0)
                                {{ Money::wholeEuros($campaign->prizePoolCents()) }}
                            @elseif ($campaign->allRewards() !== [])
                                {{ count($campaign->allRewards()) }} lot{{ count($campaign->allRewards()) > 1 ? 's' : '' }}
                            @else
                                La gloire
                            @endif
                        @elseif ($bestRate)
                            {{ Money::rate($bestRate) }}
                        @endif
                    </dd>
                </div>
                <div class="px-2">
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-ink-400">{{ $isOver ? 'Terminée' : 'Fin' }}</dt>
                    <dd class="mt-0.5 font-display text-lg font-bold text-ink-50 sm:text-2xl">
                        @if ($campaign->ends_at)
                            {{ $isOver ? $campaign->ends_at->format('d/m') : $campaign->ends_at->diffForHumans(['parts' => 1, 'short' => true, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div class="px-2">
                    <dt class="text-[11px] font-medium uppercase tracking-wide text-ink-400">Participants</dt>
                    <dd class="mt-0.5 font-display text-lg font-bold tabular text-ink-50 sm:text-2xl">{{ $participantsCount }}</dd>
                </div>
            </dl>
        </section>

        @if (session('status'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        {{-- Campagne finie : on le dit d'emblée, et on rappelle ce qui reste possible. --}}
        @if ($isOver)
            <div class="rounded-2xl border border-ink-600 bg-ink-900 p-4">
                <p class="font-display text-lg font-bold text-ink-50">Cette campagne est terminée</p>
                <p class="mt-1 text-sm leading-relaxed text-ink-300">
                    @if ($campaign->ends_at)
                        Elle s'est arrêtée le {{ $campaign->ends_at->format('d/m/Y à H\hi') }}.
                    @endif
                    Tu peux toujours consulter {{ $campaign->isContest() ? 'le classement final et ' : '' }}tes statistiques,
                    mais il n'est plus possible de soumettre ni de retirer des clips.
                </p>
            </div>
        @endif

        @if ($opensAt)
            <div class="{{ $isOpen ? 'alert-ok' : 'alert-warn' }} text-sm">
                @if ($isOpen)
                    <p class="font-semibold">Accès anticipé : vous pouvez rejoindre dès maintenant.</p>
                    <p class="mt-0.5">Ouverture officielle le {{ $campaign->starts_at->format('d/m/Y à H\hi') }}.</p>
                @else
                    <p class="font-semibold">Ouvre le {{ $opensAt->format('d/m/Y à H\hi') }} pour vous</p>
                @endif
            </div>
        @endif

        {{-- Le volume minimum, dit en clair : quelqu'un qui voit ses vues monter
             sans que ses gains bougent cherche une panne, pas une règle. --}}
        @if ($minClips > 0 && $valides < $minClips)
            <div class="alert-warn text-sm">
                <p class="font-semibold">{{ $minClips }} clips soumis avant d'être rémunéré — vous en avez {{ $valides }}</p>
                <p class="mt-0.5">Vos vues sont déjà comptées : tout l'arriéré est payé d'un coup au {{ $minClips }}<sup>e</sup> clip.</p>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="min-w-0 space-y-4 lg:col-span-2">

                {{-- Rejoindre : sur téléphone, c'est la première chose à faire, donc en haut. --}}
                @unless ($joined)
                    <div class="card p-5 lg:hidden">
                        @include('clipper.campaigns.partials.join', ['campaign' => $campaign, 'isOpen' => $isOpen, 'remainingCents' => $remainingCents, 'placement' => 'mobile'])
                    </div>
                @endunless

                {{-- Onglets --}}
                <div class="flex gap-1 rounded-2xl bg-ink-900 p-1 ring-1 ring-ink-800" role="tablist">
                    @if ($campaign->isContest())
                        <button type="button" role="tab" @click="tab = 'classement'" :aria-selected="tab === 'classement'"
                                :class="tab === 'classement' ? 'bg-ink-50 text-ink-950' : 'text-ink-300 hover:text-ink-50'"
                                class="flex-1 rounded-xl px-3 py-2 text-sm font-semibold transition">{{ $isOver ? 'Classement final' : 'Classement' }}</button>
                    @endif
                    <button type="button" role="tab" @click="tab = 'brief'" :aria-selected="tab === 'brief'"
                            :class="tab === 'brief' ? 'bg-ink-50 text-ink-950' : 'text-ink-300 hover:text-ink-50'"
                            class="flex-1 rounded-xl px-3 py-2 text-sm font-semibold transition">Brief</button>
                    <button type="button" role="tab" @click="tab = 'clips'" :aria-selected="tab === 'clips'"
                            :class="tab === 'clips' ? 'bg-ink-50 text-ink-950' : 'text-ink-300 hover:text-ink-50'"
                            class="flex-1 rounded-xl px-3 py-2 text-sm font-semibold transition">
                        Mes clips
                        @if ($clips->isNotEmpty())
                            <span class="tabular opacity-60">{{ $clips->count() }}</span>
                        @endif
                    </button>
                </div>

                @if ($campaign->isContest())
                    <div x-show="tab === 'classement'" role="tabpanel">
                        @include('clipper.campaigns.partials.contest', ['campaign' => $campaign, 'boards' => $boards])
                    </div>
                @endif

                <div x-show="tab === 'brief'" @if ($campaign->isContest()) x-cloak @endif role="tabpanel" class="space-y-4">
                    @include('clipper.campaigns.partials.brief', compact('campaign', 'platforms', 'minClips', 'valides', 'remainingCents', 'joined', 'isOpen'))
                </div>

                <div x-show="tab === 'clips'" x-cloak role="tabpanel" id="mes-clips">
                    @include('clipper.campaigns.partials.my-clips', ['campaign' => $campaign, 'clips' => $clips, 'canPick' => $canPick])
                </div>
            </div>

            {{-- Colonne d'action sur ordinateur, qui suit le défilement. --}}
            <aside class="hidden space-y-4 lg:sticky lg:top-24 lg:block lg:self-start">
                <div class="card p-6">
                    @if (! $joined)
                        @include('clipper.campaigns.partials.join', ['campaign' => $campaign, 'isOpen' => $isOpen, 'remainingCents' => $remainingCents, 'placement' => 'desktop'])
                    @else
                        <h2 class="font-display text-lg font-bold text-ink-50">Soumettre un clip</h2>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($participations->filter(fn ($p) => $p->socialAccount?->isSyncable()) as $participation)
                                <span class="chip-ok">
                                    {{ $participation->socialAccount?->platform->label() }}@if ($participation->socialAccount?->handle) · &#64;{{ $participation->socialAccount->handle }}@endif
                                </span>
                            @endforeach
                        </div>

                        @if ($canPick)
                            <p class="mt-3 text-sm text-ink-300">Publiez sur TikTok, puis choisissez vos vidéos.</p>
                            <button type="button" @click="$dispatch('open-picker')" class="btn-brand mt-4 w-full py-3">
                                Choisir mes vidéos
                            </button>
                        @else
                            @include('clipper.campaigns.partials.closed-notice', ['campaign' => $campaign, 'opensAt' => $opensAt])
                        @endif
                    @endif
                </div>
            </aside>
        </div>

        {{-- Téléphone : l'action principale, collée en bas de l'écran. --}}
        @if ($canPick)
            <div class="fixed inset-x-0 bottom-0 z-40 border-t border-ink-800 bg-ink-950/95 px-4 pt-3 backdrop-blur lg:hidden"
                 style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                <button type="button" @click="$dispatch('open-picker')" class="btn-brand w-full py-3.5 text-base">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    Soumettre mes vidéos
                </button>
            </div>
        @elseif ($joined)
            <div class="lg:hidden">
                @include('clipper.campaigns.partials.closed-notice', ['campaign' => $campaign, 'opensAt' => $opensAt])
            </div>
        @endif
    </div>

    {{-- À la racine, hors des cartes : voir le composant. --}}
    @if ($canPick)
        @livewire('submit-clip', ['campaign' => $campaign])
    @endif
</x-app-layout>
