import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const withOpacity = (cssVar) => ({ opacityValue }) =>
    opacityValue === undefined
        ? `rgb(var(${cssVar}) / 1)`
        : `rgb(var(${cssVar}) / ${opacityValue})`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                brand: {
                    50: withOpacity('--color-brand-50'),
                    100: withOpacity('--color-brand-100'),
                    200: withOpacity('--color-brand-200'),
                    300: withOpacity('--color-brand-300'),
                    400: withOpacity('--color-brand-400'),
                    500: withOpacity('--color-brand-500'),
                    600: withOpacity('--color-brand-600'),
                    700: withOpacity('--color-brand-700'),
                    800: withOpacity('--color-brand-800'),
                    900: withOpacity('--color-brand-900'),
                    950: withOpacity('--color-brand-950'),
                },
                app: {
                    bg: {
                        primary: withOpacity('--color-bg-primary'),
                        secondary: withOpacity('--color-bg-secondary'),
                    },
                    surface: {
                        DEFAULT: withOpacity('--color-surface'),
                        strong: withOpacity('--color-surface-strong'),
                        border: withOpacity('--color-surface-border'),
                    },
                    text: {
                        primary: withOpacity('--color-text-primary'),
                        secondary: withOpacity('--color-text-secondary'),
                        muted: withOpacity('--color-text-muted'),
                    },
                    light: {
                        text: {
                            primary: withOpacity('--color-light-text-primary'),
                            secondary: withOpacity('--color-light-text-secondary'),
                        },
                        border: withOpacity('--color-light-border'),
                    },
                },
            },
            fontFamily: {
                sans: ['Cairo', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
