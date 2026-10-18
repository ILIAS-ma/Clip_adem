@php use App\Support\Money; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink-50">
                    Salut {{ $clipper->displayName() }}
                </h1>
                <p class="mt-1 text-ink-300">
                    {{ $openCampaigns > 0
                        ? $openCampaigns.' campagne'.($openCampaigns > 1 ? 's' : '').' ouverte'.($openCampaigns > 1 ? 's' : '').' en ce moment'
                        : 'Aucune campagne ouverte pour l\'instant' }}
                </p>
            </div>

            <a href="{{ route('campaigns.index') }}" wire:navigate class="btn-primary">Voir les campagnes</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">

        @if (session('status') && ! str_contains(session('status'), '-'))
            <div class="alert-ok">{{ session('status') }}</div>
        @endif

        @foreach ($bonuses as $bonus)
            <div class="card relative overflow-hidden border-brand-500/50 p-6" id="prime-{{ $bonus->id }}">
                <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-16 h-48 w-48 rounded-full bg-brand-500/25 blur-3xl"></div>
                <div class="relative flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <span class="flex h-12 w-12 flex-none items-center justify-center rounded-full bg-brand-500 text-ink-950">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-400">Prime débloquée</p>
                            <p class="font-display text-xl font-bold text-ink-50">
                                +{{ Money::euros($bonus->data['amount_cents'] ?? 0) }}
                            </p>
                            <p class="mt-0.5 text-sm text-ink-300">{{ $bonus->data['body'] ?? '' }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('notifications.read', $bonus->id) }}">
                        @csrf
                        <button type="submit" class="btn-primary">Voir mes revenus</button>
                    </form>
                </div>
            </div>
        @endforeach

        {{-- L'état du compte en quelques pastilles, avant de lire quoi que ce
             soit : on sait tout de suite si l'on est en mesure de participer
             ou s'il manque quelque chose. --}}
        <div class="status-row">
            <span class="pill is-ok">{{ $progression->level->label() }}</span>

            <span @class(['pill', 'is-bad' => $accountsCount === 0, 'is-ok' => $accountsCount > 0])>
                {{ $accountsCount }} compte{{ $accountsCount > 1 ? 's' : '' }} lié{{ $accountsCount > 1 ? 's' : '' }}
            </span>

            @if ($needsReconnect > 0)
                <span class="pill is-bad">{{ $needsReconnect }} à reconnecter</span>
            @endif

            <span class="pill">{{ $openCampaigns }} campagne{{ $openCampaigns > 1 ? 's' : '' }} ouverte{{ $openCampaigns > 1 ? 's' : '' }}</span>
            <span class="pill">{{ $clips->count() }} clip{{ $clips->count() > 1 ? 's' : '' }}</span>

            @if ($unpaidViews > 0)
                <span class="pill">{{ Money::views($unpaidViews) }} vues non rémunérées</span>
            @endif
        </div>

        @php
            $minimum = (int) config('clipping.payouts.minimum_cents');
            $atteint = $minimum > 0 ? min(100, intdiv($balanceCents * 100, $minimum)) : 100;
            $manque = max(0, $minimum - $balanceCents);
        @endphp

        {{--
            Un seul chiffre domine : celui qu'on vient consulter.

            Quatre cartes de même poids obligeaient à lire les quatre étiquettes
            pour trouver la seule qui répond à « combien puis-je retirer ». Les
            autres restent là — elles expliquent ce solde — mais en second plan.
        --}}
        @php
            /*
             * Le parcours d'un nouveau clippeur, dans l'ordre où il se fait.
             * Chaque étape se coche d'elle-même à partir de ce qui existe : on
             * voit d'un coup d'œil où l'on en est, et la prochaine action est
             * toujours la première étape non cochée.
             */
            $studioOn = (bool) config('clipping.studio.enabled');
            $etapes = [
                ['fait' => $accountsCount > 0, 'titre' => 'Lier votre compte TikTok',
                 'detail' => 'C\'est par lui que vos vues sont comptées.',
                 'lien' => route('accounts.index'), 'action' => 'Lier', 'navigate' => true],
                ['fait' => $joinedCampaigns > 0, 'titre' => 'Rejoindre une campagne',
                 'detail' => 'Choisissez un créateur ou un streamer à promouvoir.',
                 'lien' => route('campaigns.index'), 'action' => 'Voir', 'navigate' => true],
                // Echo Studio en pause : l'étape mène à la campagne plutôt qu'à l'outil.
                $studioOn
                    ? ['fait' => $clips->isNotEmpty(), 'titre' => 'Publier votre premier clip',
                       'detail' => 'Trouvez-le et montez-le dans Echo Studio, puis collez son lien.',
                       'lien' => url('/fetchclip'), 'action' => 'Studio', 'navigate' => false]
                    : ['fait' => $clips->isNotEmpty(), 'titre' => 'Publier votre premier clip',
                       'detail' => 'Publiez-le sur votre compte, puis collez son lien sur la campagne.',
                       'lien' => route('campaigns.index'), 'action' => 'Campagnes', 'navigate' => true],
                ['fait' => $manque === 0, 'titre' => 'Atteindre '.Money::euros($minimum),
                 'detail' => 'Le minimum pour demander un retrait PayPal.',
                 'lien' => route('earnings.index'), 'action' => 'Revenus', 'navigate' => true],
            ];
            $faites = collect($etapes)->where('fait', true)->count();
            $prochaine = collect($etapes)->search(fn ($e) => ! $e['fait']);
        @endphp

        <div class="grid gap-4 lg:grid-cols-5">

            {{-- Le solde, et en dessous les trois chiffres qui l'expliquent :
                 une seule carte au lieu de quatre, sans vide au milieu. --}}
            <div class="hero-balance card relative flex flex-col overflow-hidden lg:col-span-3">
                <div class="flex-1 p-7 sm:p-8">
                    <p class="text-sm font-medium text-ink-300">Solde retirable</p>

                    <p class="mt-2 font-display text-5xl font-extrabold tabular leading-none text-brand-400 sm:text-6xl"
                       data-count-up>{{ Money::euros($balanceCents) }}</p>

                    @if ($manque > 0)
                        <div class="mt-6 max-w-sm">
                            {{-- La barre répond à « c'est encore loin ? », qu'un simple
                                 « minimum 20 € » laisse calculer de tête. --}}
                            <div class="h-1.5 overflow-hidden rounded-full bg-ink-700">
                                <div class="h-full rounded-full bg-brand-500 transition-all duration-700"
                                     style="width: {{ $atteint }}%"></div>
                            </div>
                            <p class="mt-2 text-sm text-ink-300">
                                Encore <span class="font-semibold tabular text-ink-100">{{ Money::euros($manque) }}</span>
                                avant de pouvoir retirer.
                            </p>
                        </div>
                    @else
                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <a href="{{ route('earnings.index') }}" wire:navigate class="btn-brand">Demander un retrait</a>
                            <span class="text-sm text-ink-300">Vous avez atteint le minimum.</span>
                        </div>
                    @endif
                </div>

                <dl class="grid grid-cols-3 divide-x divide-ink-700 border-t border-ink-700 bg-ink-900/60">
                    <div class="px-5 py-4 sm:px-7">
                        <dt class="text-xs text-ink-400">Vues cumulées</dt>
                        <dd class="mt-1 font-display text-xl font-bold tabular text-ink-50">{{ Money::views($views) }}</dd>
                        @if ($unpaidViews > 0)
                            <dd class="mt-0.5 text-[11px] text-ink-500">{{ Money::views($unpaidViews) }} non rémunérées</dd>
                        @endif
                    </div>
                    <div class="px-5 py-4 sm:px-7">
                        <dt class="text-xs text-ink-400">Gains validés</dt>
                        <dd class="mt-1 font-display text-xl font-bold tabular text-brand-400">{{ Money::euros($earnedCents) }}</dd>
                    </div>
                    <div class="px-5 py-4 sm:px-7">
                        <dt class="text-xs text-ink-400">Comptes liés</dt>
                        <dd @class(['mt-1 font-display text-xl font-bold tabular', 'text-brand-400' => $accountsCount === 0, 'text-ink-50' => $accountsCount > 0])>{{ $accountsCount }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Pour démarrer : tant que tout n'est pas fait. Ensuite, la place
                 revient à ce qu'on fait tous les jours. --}}
            <div class="card flex flex-col p-6 lg:col-span-2">
                @if ($faites < count($etapes))
                    <div class="flex items-baseline justify-between gap-3">
                        <h2 class="font-display text-lg font-bold text-ink-50">Pour démarrer</h2>
                        <span class="text-sm tabular text-ink-400">{{ $faites }}/{{ count($etapes) }}</span>
                    </div>
                    <div class="mt-3 h-1 overflow-hidden rounded-full bg-ink-700">
                        <div class="h-full rounded-full bg-brand-500" style="width: {{ intdiv($faites * 100, count($etapes)) }}%"></div>
                    </div>

                    <ol class="mt-5 flex-1 space-y-3">
                        @foreach ($etapes as $i => $etape)
                            <li @class([
                                'flex items-center gap-3 rounded-xl border px-3.5 py-3',
                                'border-brand-500/50 bg-brand-500/5' => $i === $prochaine,
                                'border-ink-700' => $i !== $prochaine,
                            ])>
                                @if ($etape['fait'])
                                    <span class="grid h-7 w-7 flex-none place-items-center rounded-full bg-brand-500 text-white" aria-label="Fait">
                                        <svg viewBox="0 0 20 20" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 10.5l3 3 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </span>
                                @else
                                    <span class="grid h-7 w-7 flex-none place-items-center rounded-full border border-ink-600 text-xs font-bold text-ink-300">{{ $i + 1 }}</span>
                                @endif

                                <span class="min-w-0 flex-1">
                                    <span @class(['block text-sm font-semibold', 'text-ink-500 line-through' => $etape['fait'], 'text-ink-50' => ! $etape['fait']])>{{ $etape['titre'] }}</span>
                                    @if (! $etape['fait'])
                                        <span class="block text-xs text-ink-400">{{ $etape['detail'] }}</span>
                                    @endif
                                </span>

                                @if ($i === $prochaine)
                                    <a href="{{ $etape['lien'] }}" @if ($etape['navigate']) wire:navigate @endif class="btn-brand flex-none px-3 py-1.5 text-xs">{{ $etape['action'] }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <h2 class="font-display text-lg font-bold text-ink-50">Tout est prêt</h2>
                    <p class="mt-2 text-sm text-ink-300">
                        Compte lié, campagne rejointe, clips publiés : continuez sur votre lancée.
                    </p>
                    @if ($studioOn)
                        <a href="{{ url('/fetchclip') }}" class="btn-brand mt-auto self-start">Ouvrir Echo Studio</a>
                    @else
                        <a href="{{ route('campaigns.index') }}" wire:navigate class="btn-brand mt-auto self-start">Voir les campagnes</a>
                    @endif
                @endif
            </div>
        </div>

        {{-- Les trois endroits où l'on va tous les jours. --}}
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                $studioOn
                    ? ['href' => url('/fetchclip'), 'navigate' => false, 'titre' => 'Echo Studio',
                       'texte' => 'Trouvez les meilleurs moments Twitch et montez-les en 9:16.',
                       'icone' => 'M15 10l4.55-2.28A1 1 0 0121 8.62v6.76a1 1 0 01-1.45.9L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z']
                    // Studio en pause : la formation prend sa place.
                    : ['href' => route('training.index'), 'navigate' => true, 'titre' => 'Formation',
                       'texte' => 'Les formats qui marchent et les règles, en quelques minutes.',
                       'icone' => 'M12 14l9-5-9-5-9 5 9 5zm0 0v6m-6-9.2V16c0 1.1 2.7 3 6 3s6-1.9 6-3v-5.2'],
                ['href' => route('campaigns.index'), 'navigate' => true, 'titre' => 'Campagnes',
                 'texte' => $openCampaigns > 0 ? $openCampaigns.' ouverte'.($openCampaigns > 1 ? 's' : '').' en ce moment.' : 'Aucune ouverte pour l\'instant.',
                 'icone' => 'M3 10v4a1 1 0 001 1h3l5 4V5L7 9H4a1 1 0 00-1 1zm13.5-3a5 5 0 010 10'],
                ['href' => route('messages.index'), 'navigate' => true, 'titre' => 'Messages',
                 'texte' => $unreadMessages > 0 ? $unreadMessages.' réponse'.($unreadMessages > 1 ? 's' : '').' de l\'équipe à lire.' : 'Une question ? L\'équipe vous répond ici.',
                 'icone' => 'M8 10h8M8 14h5m-9 6l2.5-3H18a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v14z',
                 'badge' => $unreadMessages],
            ] as $raccourci)
                <a href="{{ $raccourci['href'] }}" @if ($raccourci['navigate']) wire:navigate @endif
                   class="card group flex items-start gap-4 p-5 transition hover:border-brand-500/50 hover:bg-ink-800/60">
                    <span class="grid h-11 w-11 flex-none place-items-center rounded-xl bg-brand-500/10 text-brand-400 ring-1 ring-brand-500/30 transition group-hover:bg-brand-500 group-hover:text-white">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $raccourci['icone'] }}"/></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2 font-display font-bold text-ink-50">
                            {{ $raccourci['titre'] }}
                            @if (! empty($raccourci['badge']))
                                <span class="rounded-full bg-brand-500 px-1.5 py-0.5 text-[11px] font-bold leading-none text-white">{{ $raccourci['badge'] }}</span>
                            @endif
                        </span>
                        <span class="mt-1 block text-sm text-ink-400">{{ $raccourci['texte'] }}</span>
                    </span>
                    <span class="self-center text-ink-600 transition group-hover:translate-x-0.5 group-hover:text-ink-200" aria-hidden="true">→</span>
                </a>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-level-card class="lg:col-span-2" :progression="$progression" />

            <div class="card p-6">
                <p class="text-sm font-medium text-ink-300">Comment gagner de l'XP</p>
                <ul class="mt-4 space-y-3 text-sm">
                    <li class="flex items-baseline justify-between gap-3">
                        <span class="text-ink-300">Chaque vue rémunérée</span>
                        <span class="font-semibold tabular text-ink-50">1 XP</span>
                    </li>
                    <li class="flex items-baseline justify-between gap-3">
                        <span class="text-ink-300">Clip validé</span>
                        <span class="font-semibold tabular text-brand-400">
                            +{{ number_format(config('clipping.progression.xp_per_approved_clip'), 0, ',', ' ') }}
                        </span>
                    </li>
                    <li class="flex items-baseline justify-between gap-3">
                        <span class="text-ink-300">Nouvelle campagne rejointe</span>
                        <span class="font-semibold tabular text-brand-400">
                            +{{ number_format(config('clipping.progression.xp_per_campaign'), 0, ',', ' ') }}
                        </span>
                    </li>
                    <li class="flex items-baseline justify-between gap-3 border-t border-ink-700 pt-3">
                        <span class="text-ink-300">Clip invalidé</span>
                        <span class="font-semibold tabular text-red-400">
                            −{{ number_format(config('clipping.progression.xp_penalty_per_invalidated_clip'), 0, ',', ' ') }}
                        </span>
                    </li>
                </ul>

                {{-- Seules les vues réellement payées comptent : dit d'emblée,
                     ça enlève tout intérêt à gonfler ses compteurs. --}}
                <p class="hint mt-4 border-t border-ink-700 pt-4">
                    Seules les vues effectivement rémunérées comptent. Un clip invalidé perd les
                    siennes.
                </p>
            </div>
        </div>

        @if ($upcomingCampaigns->isNotEmpty())
            {{-- Anticiper plutôt que découvrir une campagne déjà à moitié
                 consommée par d'autres au moment où elle s'ouvre. --}}
            <div class="card">
                <div class="border-b border-ink-700 px-6 py-4">
                    <h2 class="font-display text-lg font-bold text-ink-50">Campagnes à venir</h2>
                    <p class="mt-0.5 text-sm text-ink-300">Déjà budgétées, pas encore ouvertes.</p>
                </div>
                <ul class="divide-y divide-ink-700">
                    @foreach ($upcomingCampaigns as $item)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                            <div>
                                <p class="font-semibold text-ink-50">{{ $item['campaign']->title }}</p>
                                <p class="mt-0.5 text-sm text-ink-400">{{ $item['campaign']->creator?->name }}</p>
                            </div>
                            <span class="chip-wait">
                                Ouvre {{ $item['opensAt']->isFuture() ? $item['opensAt']->diffForHumans() : "aujourd'hui" }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="flex items-center justify-between border-b border-ink-700 px-6 py-4">
                <h2 class="font-display text-lg font-bold text-ink-50">Mes clips</h2>
                @if ($clips->isNotEmpty())
                    <a href="{{ route('clips.index') }}" wire:navigate class="text-sm font-semibold text-ink-300 underline-offset-2 hover:text-ink-50 hover:underline">
                        Tout voir
                    </a>
                @endif
            </div>

            @if ($clips->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="font-display text-base font-bold text-ink-50">Aucun clip pour l'instant</p>
                    <p class="mx-auto mt-2 max-w-sm text-sm text-ink-300">
                        Rejoignez une campagne, publiez votre clip, puis collez son lien.
                    </p>
                </div>
            @else
                <ul class="divide-y divide-ink-700">
                    @foreach ($clips->take(5) as $clip)
                        <li>
                            <a href="{{ route('clips.show', $clip) }}" wire:navigate
                               class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 transition hover:bg-ink-800">
                                <div class="min-w-0">
                                    <p class="font-semibold text-ink-50">{{ $clip->campaign?->title }}</p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-2 text-sm text-ink-400">
                                        <span>{{ $clip->platform->label() }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span class="{{ $clip->status->value === 'approved' ? 'chip-ok' : ($clip->status->value === 'pending_review' ? 'chip-wait' : 'chip-neutral') }}">
                                            {{ $clip->status->label() }}
                                        </span>
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="font-display font-bold tabular text-ink-50">{{ Money::euros($clip->earned_cents) }}</p>
                                    <p class="text-xs tabular text-ink-400">{{ Money::views($clip->views_total) }} vues</p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

    </div>
</x-app-layout>
