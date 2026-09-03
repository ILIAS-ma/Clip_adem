@php
    // La barre s'adapte au rôle : un artiste n'a ni clips ni solde, lui montrer
    // des liens morts vaudrait moins que rien.
    $links = auth()->user()->isArtist()
        ? [
            ['route' => 'artist.dashboard',    'pattern' => 'artist.dashboard', 'label' => 'Mes campagnes'],
            ['route' => 'artist.profile.edit', 'pattern' => 'artist.profile.*', 'label' => 'Ma fiche'],
        ]
        : [
            ['route' => 'dashboard',       'pattern' => 'dashboard',   'label' => 'Tableau de bord'],
            ['route' => 'campaigns.index', 'pattern' => 'campaigns.*', 'label' => 'Campagnes'],
            ['route' => 'clips.index',     'pattern' => 'clips.*',     'label' => 'Mes clips'],
            ['route' => 'accounts.index',  'pattern' => 'accounts.*',  'label' => 'Mes comptes'],
            ['route' => 'earnings.index',  'pattern' => 'earnings.*',  'label' => 'Revenus'],
        ];

    $home = auth()->user()->isArtist() ? route('artist.dashboard') : route('dashboard');
@endphp

<nav x-data="{ open: false }" class="sticky top-0 z-30 border-b border-ink-700 bg-ink-900/85 backdrop-blur">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex">
                <a href="{{ $home }}" class="flex shrink-0 items-center">
                    <x-brand-mark />
                </a>

                <div class="hidden sm:ms-10 sm:flex sm:gap-8">
                    @foreach ($links as $link)
                        <x-nav-link :href="route($link['route'])" :active="request()->routeIs($link['pattern'])">
                            {{ $link['label'] }}
                        </x-nav-link>
                    @endforeach
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:gap-4">
                @if (auth()->user()->isClipper())
                    {{-- Le solde est l'information que le clippeur vient
                         chercher : elle reste visible sur toutes les pages. --}}
                    <a href="{{ route('earnings.index') }}"
                       class="rounded-xl bg-brand-500/15 px-3 py-1.5 text-sm font-semibold tabular text-brand-300 transition hover:bg-brand-500/25">
                        {{ \App\Support\Money::euros(auth()->user()->availableBalanceCents()) }}
                    </a>
                @elseif (auth()->user()->isArtist())
                    <span class="chip-neutral">Espace artiste</span>
                @endif

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="flex items-center gap-2 rounded-xl px-2 py-1.5 text-sm font-medium text-ink-300 transition hover:bg-ink-800 hover:text-ink-50">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-500 text-xs font-bold text-ink-950">
                                {{ mb_strtoupper(mb_substr(auth()->user()->displayName(), 0, 1)) }}
                            </span>
                            {{ auth()->user()->displayName() }}
                            <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">Mon compte</x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                                Déconnexion
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="rounded-lg p-2 text-ink-400 transition hover:bg-ink-800 hover:text-ink-100"
                        :aria-expanded="open" aria-label="Menu">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path x-show="! open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path x-show="open" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div x-show="open" x-cloak class="border-t border-ink-700 sm:hidden">
        <div class="py-2">
            @foreach ($links as $link)
                <x-responsive-nav-link :href="route($link['route'])" :active="request()->routeIs($link['pattern'])">
                    {{ $link['label'] }}
                </x-responsive-nav-link>
            @endforeach
        </div>

        <div class="border-t border-ink-700 py-4">
            <div class="flex items-center justify-between px-4">
                <div>
                    <div class="font-semibold text-ink-100">{{ auth()->user()->displayName() }}</div>
                    <div class="text-sm text-ink-400">{{ auth()->user()->email }}</div>
                </div>

                @if (auth()->user()->isClipper())
                    <span class="chip-ok tabular">
                        {{ \App\Support\Money::euros(auth()->user()->availableBalanceCents()) }}
                    </span>
                @endif
            </div>

            <div class="mt-3">
                <x-responsive-nav-link :href="route('profile.edit')">Mon compte</x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        Déconnexion
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
