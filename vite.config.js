import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            /*
             * Le mode créateur a son propre couple CSS/JS.
             *
             * Deux raisons, et aucune n'est esthétique : sa feuille de style
             * redéfinit toute la palette en clair, et la laisser dans le
             * paquet commun la ferait charger — donc peser — sur chacune des
             * autres pages ; et GSAP n'a de sens que là, pour trois
             * transitions, pas sur un tableau de bord.
             */
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/studio.css',
                'resources/js/studio.js',
            ],
            refresh: true,
        }),
    ],
});
