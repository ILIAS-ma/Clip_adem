

import Alpine from 'alpinejs';

window.Alpine = Alpine;

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

Alpine.start();

/**
 * Révèle au scroll les éléments marqués [data-reveal] : un fade + léger
 * déplacement plutôt qu'un chargement figé section par section. Ignoré si
 * l'utilisateur préfère moins de mouvement, ou si l'élément est déjà visible
 * au chargement (pas d'animation sur ce qui est à l'écran d'entrée).
 */
function initScrollReveal() {
    const elements = document.querySelectorAll('[data-reveal]');
    if (!elements.length) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        elements.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.15, rootMargin: '0px 0px -40px 0px' }
    );

    elements.forEach((el) => observer.observe(el));
}

document.addEventListener('DOMContentLoaded', initScrollReveal);
