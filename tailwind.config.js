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
                // chrome, des gris parfaitement neutres paraissent sales.
                /*
                 * Gris relevés au compte-gouttes sur le logo : ils y sont
                 * parfaitement neutres (R = G = B). Les tirer vers le bleu,
                 * comme je l'avais fait, suffisait à ce que l'interface ne
                 * colle plus au métal — l'œil ne sait pas nommer l'écart,
                 * mais il le voit.
                 */
                ink: {
                    50: '#F5F5F5',
                    100: '#E8E8E8',
                    200: '#C8C8C8',
                    300: '#A0A0A0',
                    400: '#777777',
                    500: '#585858',
                    600: '#3C3C3C',
                    700: '#2A2A2A',   // bordures et surfaces surélevées
                    800: '#1A1A1A',   // cartes
                    900: '#101010',   // fond des sections
                    950: '#000000',   // fond de page — le noir du logo, pur
                },

                /*
                 * Rouge mesuré sur les arêtes du logo. Il est légèrement rosé
                 * et nettement moins criard qu'un rouge pur : c'est ce qui le
                 * fait lire comme une lueur sur du métal plutôt que comme un
                 * avertissement.
                 */
                brand: {
                    50: '#FCEFF0',
                    100: '#F8DADC',
                    200: '#F0B5B9',
                    300: '#E58890',
                    400: '#D96068',
                    500: '#CC3F45',   // la teinte relevée sur les arêtes
                    600: '#B23239',
                    700: '#93272E',
                    800: '#741E24',
                    900: '#57171C',
                },
            },

            boxShadow: {
                card: '0 1px 2px rgb(0 0 0 / .5), 0 12px 32px -20px rgb(0 0 0 / .9)',
                lifted: '0 2px 6px rgb(0 0 0 / .55), 0 24px 48px -24px rgb(0 0 0 / 1)',

                // Le halo du logo : un liseré net, puis une diffusion large.
                glow: '0 0 0 1px rgb(204 63 69 / .35), 0 0 24px -4px rgb(204 63 69 / .45)',
            },
        },
    },

    plugins: [forms],
};
