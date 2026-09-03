import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Identité visuelle, reprise du logo : noir profond et vert lime.
 *
 * Une seule couleur d'accent, et elle porte deux choses à la fois — la marque
 * et l'argent acquis. Sur cette palette, un montant en lime veut donc toujours
 * dire « ce gain vous appartient ». L'ambre signale l'attente, le rouge la
 * perte : trois signaux, jamais de couleur décorative.
 *
 * L'échelle `ink` garde sa convention habituelle — 50 le plus clair, 950 le
 * plus sombre — même si l'interface est sombre : c'est ce qu'un développeur
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
            },

            colors: {
                // Neutres très légèrement tirés vers le vert : posés à côté du
                // lime, des gris parfaitement neutres paraissent sales.
                ink: {
                    50: '#F4F6F1',
                    100: '#E3E7DE',
                    200: '#C6CCC0',
                    300: '#9AA294',
                    400: '#6E766A',
                    500: '#4B5247',
                    600: '#33382F',
                    700: '#232722',   // bordures et surfaces surélevées
                    800: '#181A17',   // cartes
                    900: '#101210',   // fond des sections
                    950: '#080908',   // fond de page
                },

                brand: {
                    50: '#F5FCE8',
                    100: '#E8F8C9',
                    200: '#D3F09B',
                    300: '#BAE566',
                    400: '#A6DC42',
                    500: '#93CE2E',   // le lime du logo
                    600: '#79AC21',
                    700: '#5C831C',
                    800: '#45631A',
                    900: '#2F4413',
                },

                // Les gains partagent la couleur de la marque : sur cette
                // palette, identité et argent acquis sont la même idée.
                money: {
                    50: '#F5FCE8',
                    100: '#E8F8C9',
                    300: '#BAE566',
                    500: '#93CE2E',
                    600: '#79AC21',
                    700: '#5C831C',
                },
            },

            boxShadow: {
                card: '0 1px 2px rgba(0,0,0,.5), 0 12px 32px -20px rgba(0,0,0,.9)',
                lifted: '0 2px 6px rgba(0,0,0,.55), 0 24px 48px -24px rgba(0,0,0,1)',
                glow: '0 0 0 1px rgba(147,206,46,.35), 0 0 32px -8px rgba(147,206,46,.45)',
            },
        },
    },

    plugins: [forms],
};
