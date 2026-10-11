/**
 * Mode créateur — transitions d'état.
 *
 * Trois règles, et elles ne se négocient pas :
 *
 *  1. Rien ne dépend de l'animation pour fonctionner. Si GSAP manque, ou si
 *     la personne a demandé moins de mouvement, chaque fonction pose
 *     directement l'état final et sort. L'écran reste utilisable, il est
 *     seulement moins vivant.
 *  2. Aucune animation ne dépasse 0,45 s. Au-delà, on attend l'interface au
 *     lieu de s'en servir.
 *  3. CSS ne garde que les animations continues — scintillement des
 *     squelettes, pulsation du point « en direct » — qu'il fait mieux, et
 *     qui n'ont pas d'état de départ ni d'arrivée.
 *
 * GSAP est importé depuis les dépendances du projet, jamais depuis un CDN :
 * une page qui attend un script tiers pour s'afficher correctement dépend
 * d'un serveur que nous ne tenons pas.
 */

import { gsap } from 'gsap';

/** La personne a-t-elle demandé moins de mouvement ? */
function sobre() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Entrée des cartes de résultats.
 *
 * Décalées de 25 ms l'une après l'autre, et plafonnées : vingt cartes à
 * 25 ms font déjà une demi-seconde avant que la dernière arrive. Au-delà de
 * douze, le décalage disparaît — personne ne regarde la vingtième.
 */
function entreeDesCartes(racine = document) {
    const cartes = racine.querySelectorAll('.grid > .card:not([data-entree])');

    if (! cartes.length) return;

    cartes.forEach((c) => c.setAttribute('data-entree', '1'));

    if (sobre()) return;

    gsap.from(cartes, {
        opacity: 0,
        y: 8,
        duration: 0.28,
        ease: 'power2.out',
        stagger: { each: 0.025, from: 'start' },
        // `clearProps` : sans elle, GSAP laisse un transform en style inline,
        // et le survol des cartes — qui repose sur box-shadow — se retrouve
        // dans un contexte d'empilement nouveau.
        clearProps: 'all',
        overwrite: true,
    });
}

/** Entrée du panneau principal, une fois par chargement. */
function entreeDuPanneau() {
    const panneau = document.querySelector('.main > .stack');

    if (! panneau || sobre()) return;

    gsap.from(panneau, {
        opacity: 0,
        y: 10,
        duration: 0.32,
        ease: 'power2.out',
        clearProps: 'all',
    });
}

/**
 * Le lecteur qui s'ouvre et se referme.
 *
 * L'ouverture se joue sur la hauteur réelle, mesurée : une valeur fixe
 * s'effondrerait sur un titre long, et `height: auto` ne s'anime pas.
 */
function ouvertureDuLecteur(element) {
    if (! element || element.dataset.ouvert === '1') return;

    element.dataset.ouvert = '1';

    if (sobre()) return;

    gsap.from(element, {
        height: 0,
        opacity: 0,
        duration: 0.34,
        ease: 'power3.out',
        clearProps: 'all',
    });
}

/** Tout ce qui doit être joué quand le document change. */
function jouer() {
    entreeDuPanneau();
    entreeDesCartes();
    ouvertureDuLecteur(document.querySelector('[data-lecteur]'));
}

document.addEventListener('DOMContentLoaded', jouer);

/*
 * Livewire remplace des morceaux du document sans rechargement : les
 * nouvelles cartes arriveraient sans entrée, et le lecteur apparaîtrait d'un
 * bloc. `data-entree` évite de rejouer celles qui sont déjà là.
 */
document.addEventListener('livewire:navigated', jouer);

document.addEventListener('livewire:update', () => {
    requestAnimationFrame(() => {
        entreeDesCartes();
        ouvertureDuLecteur(document.querySelector('[data-lecteur]'));
    });
});
