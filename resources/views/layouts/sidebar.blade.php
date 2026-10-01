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
            @php $actif = request()->routeIs($link['pattern']); @endphp

            <a href="{{ route($link['route']) }}" wire:navigate
               @class(['sidebar-link', 'is-active' => $actif])
               x-bind:title="mini ? @js($link['label']) : null"
               @if ($actif) aria-current="page" @endif>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="{{ $link['icon'] }}" />
                </svg>
                <span>{{ $link['label'] }}</span>
            </a>
        @endforeach
    </nav>

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
