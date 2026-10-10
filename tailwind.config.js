import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Identité visuelle, reprise du logo : noir, chrome, lueur rouge.
 *
 * Le logo est un « E » en métal brossé sur fond noir, dont les arêtes
 * rougeoient. Trois matières, et le site en reprend la logique :
 *
 *   le noir      porte tout, et il est vraiment noir — pas un gris sombre
 *   l'argent     fait les surfaces et le texte : des gris légèrement froids,
 *                comme de l'acier, jamais des gris neutres qui paraîtraient
 *                ternes à côté du métal
 *   le rouge     n'est pas un aplat mais une lueur : il apparaît sur les
 *                arêtes, les bordures actives et les halos, rarement en fond
 *
 * Conséquence sur le sens des couleurs : le rouge portant la marque, il ne
 * peut pas dire aussi « perte ». L'erreur passe au rose, distinct par sa
 * saturation ; l'attente à l'ambre ; et les montants restent blancs — sur
 * fond noir, un chiffre blanc se lit mieux que n'importe quelle couleur.
 *
 * L'échelle `ink` garde sa convention habituelle : 50 le plus clair, 950 le
 * plus sombre, même si l'interface est sombre — c'est ce qu'un développeur
 * attend en lisant `text-ink-50`.
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['"Bricolage Grotesque"', 'Figtree', ...defaultTheme.fontFamily.sans],
                serif: ['Fraunces', 'Georgia', ...defaultTheme.fontFamily.serif],
            },

            colors: {
                // Neutres très légèrement tirés vers le vert : posés à côté du
                // lime, des gris parfaitement neutres paraissent sales.
                // Gris légèrement froids : à côté du chrome, des neutres parfaits
                // paraîtraient jaunes.
                ink: {
                    50: '#F7F8FA',
                    100: '#E6E9EE',
                    200: '#C9CFD8',
                    300: '#9BA4B2',
                    400: '#6E7684',
                    500: '#4E5663',
                    600: '#363D48',
                    700: '#262B33',   // bordures et surfaces surélevées
                    800: '#191D23',   // cartes
                    900: '#101318',   // fond des sections
                    950: '#07080A',   // fond de page, presque noir
                },

                brand: {
                    50: '#FFF1F1',
                    100: '#FFDFDF',
                    200: '#FFC2C2',
                    300: '#FF9A9A',
                    400: '#FF6B6B',   // la lueur des arêtes
                    500: '#F02C2C',   // le rouge du logo
                    600: '#D11A1A',
                    700: '#A51414',
                    800: '#7C1111',
                    900: '#5C1010',
                },
            },

            boxShadow: {
                card: '0 1px 2px rgb(0 0 0 / .5), 0 12px 32px -20px rgb(0 0 0 / .9)',
                lifted: '0 2px 6px rgb(0 0 0 / .55), 0 24px 48px -24px rgb(0 0 0 / 1)',

                // Le halo du logo : un liseré net, puis une diffusion large.
                glow: '0 0 0 1px rgb(240 44 44 / .35), 0 0 24px -4px rgb(240 44 44 / .45)',
            },
        },
    },

    plugins: [forms],
};
