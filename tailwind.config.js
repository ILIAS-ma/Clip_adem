import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Identité visuelle de l'espace clippeur.
 *
 * Bleu nuit pour le socle et les actions : on manipule de l'argent, l'interface
 * doit inspirer la fiabilité avant l'énergie. L'ambre ne sert qu'aux montants et
 * aux points d'attention, le vert exclusivement aux gains acquis — un code
 * couleur constant vaut mieux qu'une palette décorative.
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
            },

            colors: {
                bone: '#F6F5F2',
                ink: {
                    50: '#F2F4F8',
                    100: '#E6E9F0',
                    200: '#C9CEDD',
                    300: '#9BA3BC',
                    400: '#6E7899',
                    500: '#4A5680',
                    600: '#2C3A63',
                    700: '#1E2947',
                    800: '#131A2E',
                    900: '#0E1424',
                },
                brand: {
                    50: '#FFF8EB',
                    100: '#FEEFC7',
                    200: '#FDE08A',
                    300: '#FBCB4D',
                    400: '#F9B723',
                    500: '#F2A007',
                    600: '#D68203',
                    700: '#B15E06',
                    800: '#90490C',
                    900: '#763C0D',
                },
                money: {
                    50: '#ECFDF5',
                    100: '#D1FAE5',
                    500: '#0E9F6E',
                    600: '#057A55',
                    700: '#046C4E',
                },
            },

            boxShadow: {
                card: '0 1px 2px rgba(19,26,46,.06), 0 8px 24px -16px rgba(19,26,46,.28)',
                lifted: '0 2px 4px rgba(19,26,46,.06), 0 16px 40px -20px rgba(19,26,46,.35)',
            },

            borderRadius: {
                xl2: '1rem',
            },
        },
    },

    plugins: [forms],
};
