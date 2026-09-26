import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

/*
 * GSAP ne remplace pas le CSS ici, il prend ce que le CSS fait mal.
 *
 * Les survols, les changements d'état d'un bouton, le dépoli de la barre
 * restent en CSS : le navigateur les compose sans passer par JavaScript, et
 * une bibliothèque n'y apporterait qu'un coût.
 *
 * Ce qui justifie GSAP, c'est l'orchestration — une séquence dont chaque
 * élément dépend du précédent — et le défilement, où l'on veut contrôler la
 * position exacte du déclenchement plutôt que subir un seuil binaire.
 *
 * Tout est neutralisé sous `prefers-reduced-motion` : une animation qu'on ne
 * peut pas refuser est une animation de trop.
 */

const MOUVEMENT_REDUIT = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Décompose « 1 234,56 € » en valeur, décimales et suffixe.
 *
 * Renvoie null pour tout ce qui n'est pas un nombre : le tiret cadratin des
 * valeurs absentes ne doit surtout pas être animé depuis zéro.
 *
 * Les milliers sont des groupes de trois précédés d'une espace, jamais « des
 * chiffres et des espaces » en vrac : sinon l'espace qui sépare le montant du
 * symbole € est avalée, et « 12 € » se rejoue en « 12€ ».
 */
function decomposer(texte) {
    const m = texte.trim().match(/^(-?\d+(?:[\s  ]\d{3})*)(?:,(\d+))?(\D*)$/);

    if (! m) {
        return null;
    }

    const entier = m[1].replace(/[\s  ]/g, '');
    const decimales = m[2] ?? '';

    return {
        valeur: parseFloat(decimales ? `${entier}.${decimales}` : entier),
        decimales: decimales.length,
        suffixe: m[3] ?? '',
    };
}

/** Remet un nombre au format du site : espace pour les milliers, virgule pour les décimales. */
function formater(valeur, decimales) {
    const [entier, reste] = valeur.toFixed(decimales).split('.');
    const groupe = entier.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

    return reste ? `${groupe},${reste}` : groupe;
}

/**
 * Les chiffres défilent jusqu'à leur valeur, quand ils entrent à l'écran.
 *
 * Ce sont des montants d'argent : la valeur finale reste écrite dans le HTML,
 * on la lit, on l'anime, puis on restitue la chaîne d'origine plutôt que de la
 * reconstruire — aucun arrondi ne peut afficher un total faux.
 */
export function animerChiffres() {
    const cibles = document.querySelectorAll('[data-count-up]:not([data-count-done])');

    cibles.forEach((el) => {
        const origine = el.textContent;
        const lu = decomposer(origine);

        el.dataset.countDone = '1';

        if (! lu || lu.valeur === 0 || MOUVEMENT_REDUIT()) {
            return;
        }

        const proxy = { v: 0 };

        gsap.to(proxy, {
            v: lu.valeur,
            duration: 1.1,
            ease: 'power2.out',
            scrollTrigger: { trigger: el, start: 'top 90%', once: true },
            onUpdate: () => {
                el.textContent = formater(proxy.v, lu.decimales) + lu.suffixe;
            },
            onComplete: () => {
                el.textContent = origine;
            },
        });
    });
}

/**
 * Révélation au défilement.
 *
 * Remplace l'IntersectionObserver par ScrollTrigger : le seuil de
 * déclenchement s'exprime en position réelle (« le haut de l'élément atteint
 * 88 % de la hauteur d'écran ») plutôt qu'en fraction d'élément visible, ce
 * qui donne le même rendu quelle que soit la taille du bloc — un observateur
 * à 15 % déclenche beaucoup trop tard sur une carte haute.
 */
export function revelerAuDefilement() {
    const cibles = gsap.utils.toArray('[data-reveal]:not([data-revealed])');

    cibles.forEach((el) => {
        el.dataset.revealed = '1';

        if (MOUVEMENT_REDUIT()) {
            el.classList.add('is-visible');

            return;
        }

        // La classe reste posée : le CSS garde la main sur l'état final, et un
        // élément déjà révélé ne peut pas se retrouver coincé à opacité zéro
        // si GSAP est interrompu en cours de route.
        gsap.fromTo(el,
            { autoAlpha: 0, y: 24 },
            {
                autoAlpha: 1,
                y: 0,
                duration: .7,
                ease: 'power2.out',
                scrollTrigger: { trigger: el, start: 'top 88%', once: true },
                onStart: () => el.classList.add('is-visible'),
            },
        );
    });
}

/**
 * Entrée de page orchestrée.
 *
 * Une seule timeline plutôt que des délais CSS indépendants : les blocs
 * s'enchaînent réellement, et si l'un manque sur une page donnée, les
 * suivants se décalent au lieu d'attendre dans le vide un délai figé.
 */
export function entreeDePage() {
    if (MOUVEMENT_REDUIT()) {
        return;
    }

    const principal = document.querySelector('main');

    if (! principal) {
        return;
    }

    const tl = gsap.timeline({ defaults: { ease: 'power2.out' } });

    const hero = principal.querySelector('.hero-balance');
    const cartes = gsap.utils.toArray(principal.querySelectorAll('.stat-card'));

    tl.from(principal, { autoAlpha: 0, y: 8, duration: .28 });

    if (hero) {
        tl.from(hero, { autoAlpha: 0, y: 16, duration: .45 }, '-=.12');
    }

    if (cartes.length) {
        tl.from(cartes, { autoAlpha: 0, y: 14, duration: .4, stagger: .06 }, '-=.28');
    }

    return tl;
}

/** Tout réarmer : au chargement, et après chaque navigation instantanée. */
export function armerAnimations() {
    // Les déclencheurs du document remplacé pointent vers des éléments qui
    // n'existent plus. Sans ce nettoyage, ils s'accumulent à chaque page
    // visitée et finissent par peser sur le défilement.
    ScrollTrigger.getAll().forEach((t) => t.kill());

    entreeDePage();
    revelerAuDefilement();
    animerChiffres();

    ScrollTrigger.refresh();
}
