

import Alpine from 'alpinejs';

window.Alpine = Alpine;

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
