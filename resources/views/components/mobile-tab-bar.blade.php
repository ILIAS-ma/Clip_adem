{{--
    Barre d'onglets du téléphone, comme dans une application.

    Les quatre rubriques qu'un clippeur ouvre tous les jours sont à portée de
    pouce ; le reste — formation, classement, succès, compte — passe par
    « Plus », qui ouvre le tiroir du menu. Avant, tout passait par le menu en
    haut à droite : la zone de l'écran la plus loin du pouce.

    Absente sur la page d'une campagne : son bouton « Soumettre mes vidéos »
    occupe déjà le bas de l'écran.
--}}
@php
    $tabs = [
        ['route' => 'dashboard', 'pattern' => 'dashboard', 'label' => 'Accueil',
         'icon' => 'M3 11l9-8 9 8M5 10v9a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1v-9'],
        ['route' => 'campaigns.index', 'pattern' => 'campaigns.*', 'label' => 'Campagnes',
         'icon' => 'M3 10v4a1 1 0 001 1h3l5 4V5L7 9H4a1 1 0 00-1 1zm13.5-3a5 5 0 010 10'],
        ['route' => 'clips.index', 'pattern' => 'clips.*', 'label' => 'Mes clips',
         'icon' => 'M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zm6 4.5l4 2.5-4 2.5v-5z'],
        ['route' => 'earnings.index', 'pattern' => 'earnings.*', 'label' => 'Revenus',
         'icon' => 'M3 8a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm13 4a2 2 0 11-4 0 2 2 0 014 0z'],
    ];
@endphp

<nav x-data class="fixed inset-x-0 bottom-0 z-30 border-t border-ink-800 bg-ink-950/95 backdrop-blur lg:hidden"
     style="padding-bottom: env(safe-area-inset-bottom, 0px);"
     aria-label="Navigation principale">
    <div class="mx-auto grid max-w-md grid-cols-5">
        @foreach ($tabs as $tab)
            @php $active = request()->routeIs($tab['pattern']); @endphp
            <a href="{{ route($tab['route']) }}" wire:navigate
               @class(['flex flex-col items-center gap-0.5 pb-2 pt-2.5 text-[11px] font-semibold transition',
                       'text-brand-400' => $active, 'text-ink-400 active:text-ink-100' => ! $active])
               @if ($active) aria-current="page" @endif>
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2.2 : 1.7 }}"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $tab['icon'] }}" /></svg>
                {{ $tab['label'] }}
            </a>
        @endforeach

        <button type="button" @click="$dispatch('open-nav')"
                class="flex flex-col items-center gap-0.5 pb-2 pt-2.5 text-[11px] font-semibold text-ink-400 transition active:text-ink-100">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
            Plus
        </button>
    </div>
</nav>
