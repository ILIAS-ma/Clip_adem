import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Identité visuelle : fond clair, texte noir, accent rouge.
 *
 * Le rouge porte la marque et l'action. Il ne peut donc PAS signifier la
 * perte, comme c'était le cas sur l'ancienne charte sombre : un bouton et une
 * alerte d'erreur ne doivent pas se confondre.
 *
 * D'où la répartition :
 *   rouge  = marque, actions, élément actif
 *   noir   = tous les chiffres, quel que soit leur sens : sur fond clair,
 *            un montant coloré attire l'œil avant qu'on ait lu son étiquette
 *   ambre  = attente
 *   rose   = erreur et perte, distinct du rouge de marque par sa saturation
 *
 * L'échelle `ink` garde sa convention : 50 le plus clair, 950 le plus sombre.
 * Les neutres sont tirés vers le chaud — à côté du rouge, des gris
 * parfaitement froids paraîtraient bleutés.
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
                ink: {
                    50: '#FAFAF9',    // fond de page
                    100: '#F5F4F2',   // sections en retrait
                    200: '#E7E5E1',   // bordures
                    300: '#D6D3CD',
                    400: '#A8A29B',   // texte le plus discret
                    500: '#78746D',   // texte secondaire
                    600: '#57534E',
                    700: '#44403B',
                    800: '#2C2825',
                    900: '#1C1917',   // titres
                    950: '#0F0D0C',
                },

                brand: {
                    50: '#FEF2F2',
                    100: '#FEE2E2',
                    200: '#FECACA',
                    300: '#FCA5A5',
                    400: '#F26A6A',
                    500: '#E02424',   // le rouge de la marque
                    600: '#C81E1E',
                    700: '#A11616',
                    800: '#7F1414',
                    900: '#611414',
                },

            },

            /*
             * Trois couches plutôt qu'une.
             *
             * Une ombre unique paraît collée sous l'élément : la lumière réelle
             * produit un contact net tout près, une pénombre moyenne, et une
             * diffusion large et très pâle. C'est la superposition des trois
             * qui donne l'épaisseur — et c'est ce qui manque à la plupart des
             * interfaces claires, où une ombre trop dense vire tout de suite
             * au sale.
             *
             * Teinte prise sur le neutre du thème (28 25 23) et non sur du
             * noir pur : une ombre grise sur un fond légèrement chaud se voit
             * immédiatement, elle tire au bleu.
             */
            boxShadow: {
                card: '0 1px 1px rgb(28 25 23 / .04), 0 2px 6px -1px rgb(28 25 23 / .05), 0 10px 20px -8px rgb(28 25 23 / .06)',
                lifted: '0 1px 2px rgb(28 25 23 / .05), 0 8px 16px -4px rgb(28 25 23 / .08), 0 24px 44px -16px rgb(28 25 23 / .14)',
                glow: '0 0 0 1px rgb(224 36 36 / .18), 0 2px 6px -1px rgb(224 36 36 / .14), 0 14px 28px -10px rgb(224 36 36 / .28)',
            },
        },
    },

    plugins: [forms],
};
