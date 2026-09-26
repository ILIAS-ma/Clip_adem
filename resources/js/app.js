/**
 * Les animations sont chargées à la demande.
 *
 * GSAP et ScrollTrigger pèsent une quarantaine de kilo-octets compressés,
 * contre quatre pour tout le reste du script. Les inclure d'office les
 * ferait télécharger avant le premier affichage, sur des téléphones et des
 * réseaux qui n'ont rien demandé — et pour quelqu'un qui préfère moins de
 * mouvement, ils ne serviraient à rien du tout.
 *
 * En fragment séparé, la page s'affiche d'abord, les animations arrivent
 * ensuite. Rien ne dépend d'elles pour être lisible : le CSS laisse tout
 * visible tant que le script n'a pas pris la main.
 */
async function armerAnimations() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const { armerAnimations: armer } = await import('./animations');

    armer();
}

/**
 * Alpine vient toujours de Livewire (voir AppServiceProvider::boot(),
 * Livewire::forceAssetInjection()), même sur une page sans composant
 * Livewire : importer et démarrer notre propre copie d'Alpine ici ferait
 * tourner deux instances en même temps sur une page qui, elle, a un
 * composant Livewire — la seconde à démarrer perd sa méthode `navigate`,
 * et tout wire:navigate casse silencieusement. `alpine:init` se déclenche
 * une fois, quelle que soit la page, une fois l'unique instance prête.
 */
document.addEventListener('alpine:init', () => {
    /**
     * Remplace le <select> natif (voir resources/views/components/select.blade.php) :
     * lit les <option> du select caché pour construire la liste personnalisée,
     * et écrit dedans au clic pour que le formulaire ou wire:model reçoive un
     * `change` natif, exactement comme avec un select classique.
     */
    Alpine.data('customSelect', () => ({
        open: false,
        options: [],
        selectedValue: '',
        highlighted: 0,
        native: null,

        get currentLabel() {
            const found = this.options.find((o) => o.value === this.selectedValue);
            return found ? found.label : '';
        },

        init() {
            // Une requête DOM directe plutôt que $refs : après un re-rendu
            // Livewire, x-init peut être rejoué avant qu'Alpine n'ait fini
            // d'enregistrer les x-ref du sous-arbre fraîchement inséré.
            const native = this.$el.querySelector('select');
            if (!native) return;

            this.native = native;
            this.readOptions();

            // Livewire remplace les <option> après un aller-retour réseau
            // (ex : la liste des créateurs change avec la plateforme choisie) —
            // sans ça, le bouton personnalisé continuerait d'afficher un choix
            // qui n'existe plus dans la liste. Livewire remplace parfois le
            // <select> lui-même plutôt que de le patcher en place : une fois
            // Alpine détruit sur ce nœud, `this` n'a plus rien de fiable, d'où
            // la garde avant de lire quoi que ce soit et la déconnexion propre.
            const observer = new MutationObserver(() => {
                if (!this.native || !this.native.isConnected) {
                    observer.disconnect();
                    return;
                }
                this.readOptions();
            });
            observer.observe(native, {
                childList: true,
                subtree: true,
                attributes: true,
            });
        },

        readOptions() {
            if (!this.native) return;
            this.options = Array.from(this.native.options).map((o) => ({
                value: o.value,
                label: o.textContent.trim(),
            }));
            this.syncFromNative();
        },

        syncFromNative() {
            this.selectedValue = this.native.value;
            this.highlighted = this.options.findIndex((o) => o.value === this.selectedValue);
            if (this.highlighted < 0) this.highlighted = 0;
        },

        select(value) {
            this.native.value = value;
            this.native.dispatchEvent(new Event('change', { bubbles: true }));
            this.selectedValue = value;
            this.open = false;
        },

        toggle() {
            this.open = !this.open;
        },

        openAndMove(delta) {
            if (!this.open) {
                this.open = true;
                return;
            }
            const next = Math.min(Math.max(this.highlighted + delta, 0), this.options.length - 1);
            this.highlighted = next;
            this.select(this.options[next].value);
        },
    }));
});


/* =====================================================================
   Balayage de navigation.

   Livewire remplace le document sans rechargement : l'indicateur natif du
   navigateur ne s'affiche pas, et rien ne dit que le clic a été pris en
   compte. Ce trait comble ce silence — la seconde où l'on doute est celle
   où l'on reclique.
   ===================================================================== */

let sweepBar = null;

function sweep() {
    if (! sweepBar) {
        sweepBar = document.createElement('div');
        sweepBar.className = 'page-sweep';
        // Annoncé aux technologies d'assistance comme un état, pas comme un
        // contenu : personne n'a besoin d'entendre « barre de progression »
        // à chaque lien suivi.
        sweepBar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(sweepBar);
    }

    return sweepBar;
}

function startSweep() {
    const bar = sweep();

    bar.classList.remove('is-done');
    // Force un reflow : sans lui, retirer puis remettre la classe dans le
    // même cycle ne relance pas l'animation.
    void bar.offsetWidth;
    bar.classList.add('is-loading');
}

function finishSweep() {
    if (! sweepBar) {
        return;
    }

    sweepBar.classList.remove('is-loading');
    void sweepBar.offsetWidth;
    sweepBar.classList.add('is-done');
}

function initPageSweep() {
    // Navigation Livewire (wire:navigate).
    document.addEventListener('livewire:navigate', startSweep);
    document.addEventListener('livewire:navigated', () => {
        finishSweep();

        // Le nouveau document apporte ses propres éléments à animer, et les
        // déclencheurs du précédent pointent dans le vide : tout est réarmé
        // d'un bloc plutôt que rafistolé morceau par morceau.
        armerAnimations();
    });

    // Navigation classique : on n'anime que ce qui va réellement changer de
    // page. Un lien externe, un téléchargement, une ancre ou un clic avec
    // Ctrl/Cmd (nouvel onglet) laisseraient la barre tourner dans le vide.
    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a');

        if (! link || ! link.href) return;
        if (link.target && link.target !== '_self') return;
        if (link.hasAttribute('download')) return;
        if (link.origin !== window.location.origin) return;
        if (link.getAttribute('href')?.startsWith('#')) return;
        if (link.pathname === window.location.pathname && link.search === window.location.search) return;

        startSweep();
    });

    // Retour arrière depuis le cache du navigateur : la page est déjà là,
    // la barre ne doit pas rester figée à l'écran.
    window.addEventListener('pageshow', finishSweep);
    window.addEventListener('beforeunload', startSweep);
}



/* =====================================================================
   La barre se détache dès qu'on quitte le haut de page.

   Un écouteur passif et une seule classe à basculer : le gestionnaire
   tourne à chaque pixel de défilement, il ne doit rien lire qui force
   un recalcul de mise en page. `scrollY` est mis en cache par le
   navigateur, contrairement à `getBoundingClientRect()`.
   ===================================================================== */

function initScrollAwareNav() {
    const bar = document.querySelector('.nav-bar');

    if (! bar) {
        return;
    }

    // Un seuil franc plutôt que zéro : sans lui, la barre clignote entre ses
    // deux états au moindre rebond de défilement, sur les pavés tactiles
    // comme au relâchement d'un doigt sur mobile.
    const seuil = 12;

    const appliquer = () => bar.classList.toggle('is-scrolled', window.scrollY > seuil);

    appliquer();
    window.addEventListener('scroll', appliquer, { passive: true });
}

document.addEventListener('DOMContentLoaded', initScrollAwareNav);

// Après une navigation instantanée, la barre du nouveau document n'a pas
// d'écouteur : elle resterait figée dans l'état de la page précédente.
document.addEventListener('livewire:navigated', initScrollAwareNav);

document.addEventListener('DOMContentLoaded', () => {
    initPageSweep();
    armerAnimations();
});
