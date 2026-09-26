@php
    // La barre s'adapte au rôle : un créateur n'a ni clips ni solde, lui montrer
    // des liens morts vaudrait moins que rien.
    $links = auth()->user()->isCreator()
        ? [
            ['route' => 'creator.dashboard',    'pattern' => 'creator.dashboard', 'label' => 'Mes campagnes'],
            ['route' => 'creator.profile.edit', 'pattern' => 'creator.profile.*', 'label' => 'Ma fiche'],
        ]
        : [
            ['route' => 'dashboard',       'pattern' => 'dashboard',   'label' => 'Tableau de bord'],
            ['route' => 'campaigns.index', 'pattern' => 'campaigns.*', 'label' => 'Campagnes'],
            ['route' => 'clips.index',     'pattern' => 'clips.*',     'label' => 'Mes clips'],
            ['route' => 'accounts.index',  'pattern' => 'accounts.*',  'label' => 'Mes comptes'],
            ['route' => 'earnings.index',  'pattern' => 'earnings.*',  'label' => 'Revenus'],
            ['route' => 'referrals.index', 'pattern' => 'referrals.*', 'label' => 'Parrainage'],
            ['route' => 'leaderboard.index', 'pattern' => 'leaderboard.*', 'label' => 'Classement'],
            ['route' => 'achievements.index', 'pattern' => 'achievements.*', 'label' => 'Succès'],
        ];

    $home = auth()->user()->isCreator() ? route('creator.dashboard') : route('dashboard');
@endphp

{{--
    Menu au bouton, à toutes les tailles.

    Les huit liens s'étalaient en barre dès le grand écran. Une navigation
    complète affichée en permanence oblige à la relire à chaque page, et elle
    grandit avec le produit : chaque rubrique ajoutée serrait un peu plus les
    autres. Au bouton, la barre garde ce qu'on consulte sans cliquer — le solde
    et les notifications — et le reste s'ouvre quand on le demande.
--}}
<nav x-data="{ open: false }"
     x-effect="document.body.classList.toggle('overflow-hidden', open)"
     @keydown.escape.window="open = false"
     class="sticky top-0 z-30 border-b border-ink-700 bg-ink-900/85 backdrop-blur">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-3">

            <a href="{{ $home }}" class="flex shrink-0 items-center">
                <x-brand-mark />
            </a>

            <div class="flex items-center gap-2 sm:gap-3">
                @if (auth()->user()->isClipper())
                    {{-- Le solde est l'information qu'on vient chercher : elle
                         reste lisible sans ouvrir quoi que ce soit. --}}
                    <a href="{{ route('earnings.index') }}"
                       class="rounded-xl bg-brand-500/15 px-3 py-1.5 text-sm font-semibold tabular text-brand-300 transition hover:bg-brand-500/25">
                        {{ \App\Support\Money::euros(auth()->user()->availableBalanceCents()) }}
                    </a>
                @elseif (auth()->user()->isCreator())
                    <span class="hidden chip-neutral sm:inline-flex">Espace créateur</span>
                @endif

                <x-notifications-bell />

                <button type="button"
                        @click="open = ! open"
                        class="nav-burger"
                        :aria-expanded="open ? 'true' : 'false'"
                        aria-label="Ouvrir le menu">
                    {{-- Trois traits qui deviennent une croix : la même forme
                         se transforme, plutôt que deux icônes qui se
                         remplacent. On suit des yeux ce qui vient de se
                         passer. --}}
                    <span class="burger-line" :class="open && 'is-open-top'"></span>
                    <span class="burger-line" :class="open && 'is-open-mid'"></span>
                    <span class="burger-line" :class="open && 'is-open-bottom'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Téléporté dans <body> plutôt que laissé enfant de <nav> : ce dernier a
         `backdrop-blur` (backdrop-filter), qui — comme `filter` ou `transform`
         — transforme tout descendant `position:fixed` en élément positionné
         relativement à LUI plutôt qu'au viewport. Le tiroir se retrouvait
         contenu dans les 64 px de hauteur du <nav>, au lieu de couvrir
         l'écran. --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-40">

            <div x-show="open"
                 x-transition:enter="transition-opacity ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="open = false"
                 class="absolute inset-0 bg-ink-950/70 backdrop-blur-sm"></div>

            {{-- Plein écran sur mobile, panneau latéral au-delà : un voile
                 entier pour huit liens laisse un grand vide sur un écran
                 large, et l'on perd de vue la page qu'on était en train de
                 lire. --}}
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-250"
                 x-transition:enter-start="opacity-0 translate-x-6"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-6"
                 class="absolute inset-y-0 end-0 flex w-full flex-col overflow-y-auto border-s border-ink-700 bg-ink-900 shadow-lifted sm:max-w-sm">

                <div class="flex h-16 flex-none items-center justify-between border-b border-ink-700 px-4 sm:px-6">
                    <a href="{{ $home }}" class="flex items-center" @click="open = false">
                        <x-brand-mark />
                    </a>
                    <button type="button" @click="open = false"
                            class="rounded-lg p-2 text-ink-400 transition hover:bg-ink-800 hover:text-ink-100"
                            aria-label="Fermer le menu">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex-1 py-3" @click="open = false">
                    @foreach ($links as $index => $link)
                        {{-- Les entrées arrivent en cascade : l'œil descend la
                             liste au lieu de la recevoir d'un bloc. --}}
                        <div class="nav-entry" style="--entry-delay: {{ $index * 35 }}ms">
                            <x-responsive-nav-link :href="route($link['route'])" :active="request()->routeIs($link['pattern'])">
                                {{ $link['label'] }}
                            </x-responsive-nav-link>
                        </div>
                    @endforeach
                </div>

                <div class="flex-none border-t border-ink-700 py-4">
                    <div class="flex items-center gap-3 px-4 sm:px-6">
                        <x-avatar-badge :user="auth()->user()" />
                        <div class="min-w-0">
                            <div class="truncate font-semibold text-ink-100">{{ auth()->user()->displayName() }}</div>
                            <div class="truncate text-sm text-ink-400">{{ auth()->user()->email }}</div>
                        </div>
                    </div>

                    <div class="mt-3" @click="open = false">
                        <x-responsive-nav-link :href="route('profile.edit')">Mon compte</x-responsive-nav-link>

                        @if (auth()->user()->isClipper())
                            <x-responsive-nav-link :href="route('payout-method.edit')">Moyen de paiement</x-responsive-nav-link>
                        @endif

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-responsive-nav-link :href="route('logout')" :navigate="false"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                                Déconnexion
                            </x-responsive-nav-link>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </template>
</nav>
