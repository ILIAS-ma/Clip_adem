{{--
    Colonne de navigation, à partir du grand écran.

    Elle remplace le menu au bouton là où la place existe, pour une raison
    précise : la rubrique courante reste visible en permanence. Avec un menu
    fermé, savoir où l'on se trouve demande de l'ouvrir — ce qu'un menu devrait
    justement éviter.

    Elle encaisse aussi la croissance du produit. Parrainage, classement et
    succès sont arrivés en cours de route ; une barre horizontale se serre à
    chaque ajout, une colonne s'allonge sans rien déranger.

    Sous `lg`, elle disparaît et le tiroir reprend la main : une colonne fixe
    mangerait la moitié d'un écran de téléphone.
--}}
<aside x-data="{
           mini: false,
           init() {
               // L'état survit aux pages : rouvrir la colonne à chaque
               // navigation annulerait le geste de l'avoir repliée.
               try { this.mini = localStorage.getItem('nav-mini') === '1'; } catch (e) {}
               this.$watch('mini', (v) => {
                   try { localStorage.setItem('nav-mini', v ? '1' : '0'); } catch (e) {}
               });
           },
       }"
       x-bind:class="mini && 'is-mini'"
       class="app-sidebar hidden lg:flex">

    <a href="{{ $navHome }}" class="sidebar-brand" x-bind:title="mini ? @js(config('app.name')) : null">
        <x-brand-mark />
    </a>

    <nav class="sidebar-nav">
        @foreach ($navLinks as $link)
            @php
                // Une entrée porte soit un nom de route, soit une adresse
                // brute : le mode créateur n'appartient pas à Laravel.
                $cible = $link['url'] ?? route($link['route']);
                $actif = isset($link['url'])
                    ? request()->is(ltrim($link['pattern'], '/').'*')
                    : request()->routeIs($link['pattern']);
            @endphp

            <a href="{{ $cible }}" @if ($link['navigate'] ?? true) wire:navigate @endif
               @class(['sidebar-link', 'is-active' => $actif])
               x-bind:title="mini ? @js($link['label']) : null"
               @if ($actif) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="{{ $link['icon'] }}" />
                </svg>
                <span>{{ $link['label'] }}</span>
                @if (! empty($link['badge']))
                    <span class="ms-auto rounded-full bg-brand-500 px-1.5 py-0.5 text-[11px] font-bold leading-none text-white"
                          aria-label="{{ $link['badge'] }} non lu{{ $link['badge'] > 1 ? 's' : '' }}">{{ $link['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    {{--
        Le bloc du compte.

        Il n'existait que dans le tiroir mobile : sur grand écran, où la
        colonne remplace le bouton, on ne pouvait ni ouvrir son profil, ni
        changer son moyen de paiement, ni se déconnecter. Trois liens
        atteignables uniquement en réduisant la fenêtre.
    --}}
    <div class="sidebar-footer">
        <div class="sidebar-identity">
            <x-avatar-badge :user="auth()->user()" />
            <span class="min-w-0">
                <span class="block truncate font-semibold text-ink-100">{{ auth()->user()->displayName() }}</span>
                <span class="block truncate text-xs text-ink-400">{{ auth()->user()->email }}</span>
            </span>
        </div>

        @php $surCompte = request()->routeIs('profile.*') || request()->routeIs('phone.*'); @endphp

        <a href="{{ route('profile.edit') }}" wire:navigate
           @class(['sidebar-link', 'is-active' => $surCompte])
           x-bind:title="mini ? 'Mon compte' : null"
           @if ($surCompte) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span>Mon compte</span>
        </a>

        @if (auth()->user()->isClipper())
            <a href="{{ route('payout-method.edit') }}" wire:navigate
               @class(['sidebar-link', 'is-active' => request()->routeIs('payout-method.*')])
               x-bind:title="mini ? 'Moyen de paiement' : null">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 8a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm0 3h18" />
                </svg>
                <span>Moyen de paiement</span>
            </a>
        @endif

        {{-- Un POST, pas un lien : une déconnexion déclenchable par une image
             distante serait un GET qui change l'état de la session. --}}
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sidebar-link" x-bind:title="mini ? 'Déconnexion' : null">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 17l5-5-5-5m5 5H9m3 7H6a2 2 0 01-2-2V7a2 2 0 012-2h6" />
                </svg>
                <span>Déconnexion</span>
            </button>
        </form>
    </div>

    <button type="button" @click="mini = ! mini" class="sidebar-toggle"
            x-bind:aria-label="mini ? 'Déplier la navigation' : 'Replier la navigation'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
             x-bind:class="mini && 'rotate-180'">
            <path d="M15 6l-6 6 6 6" />
        </svg>
        <span x-show="! mini">Replier</span>
    </button>
</aside>
