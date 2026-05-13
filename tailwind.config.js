import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

const withOpacity = (cssVar) => ({ opacityValue }) =>
    opacityValue === undefined
        ? `rgb(var(${cssVar}) / 1)`
        : `rgb(var(${cssVar}) / ${opacityValue})`;

/**
 * Responsive breakpoints (Tailwind defaults — use consistently):
 * - sm: 640px   (large phones / small tablets)
 * - md: 768px   (tablets)
 * - lg: 1024px  (laptops)
 * - xl: 1280px+ (desktops)
 * Mobile-first: unprefixed utilities = 320px+; add sm:/md:/lg: for larger.
 */
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
            /**
             * Operational typography (Phase 2): dense, scannable, consistent hierarchy.
             * Use as text-ops-* utilities; pair with .ops-page-title / .ops-section-title in app.css.
             */
            fontSize: {
                'ops-meta': ['0.625rem', { lineHeight: '0.875rem' }],
                'ops-label': ['0.6875rem', { lineHeight: '1rem' }],
                'ops-body-sm': ['0.75rem', { lineHeight: '1rem' }],
                'ops-body': ['0.8125rem', { lineHeight: '1.125rem' }],
                'ops-body-md': ['0.875rem', { lineHeight: '1.25rem' }],
                'ops-title-sm': ['0.9375rem', { lineHeight: '1.25rem' }],
                'ops-title': ['1rem', { lineHeight: '1.375rem' }],
                'ops-title-md': ['1.0625rem', { lineHeight: '1.375rem' }],
                'ops-title-lg': ['1.125rem', { lineHeight: '1.375rem' }],
                'ops-title-xl': ['1.25rem', { lineHeight: '1.5rem' }],
            },
            /** Rhythm tokens for dashboards / forms (optional spacing-ops-*). */
            spacing: {
                'ops-1': '0.25rem',
                'ops-2': '0.5rem',
                'ops-3': '0.75rem',
                'ops-4': '1rem',
                'ops-5': '1.25rem',
                'ops-6': '1.5rem',
                'ops-8': '2rem',
                'ops-10': '2.5rem',
            },
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

    plugins: [
        forms,
        plugin(({ addVariant }) => {
            /** يطبّق فقط داخل تطبيق Capacitor (يُضاف class على html من init-native) */
            addVariant('native', 'html.capacitor-native &');
            /** PWA مثبت من المتصفح (display-mode standalone / iOS navigator.standalone) */
            addVariant('pwa', 'html.pwa-standalone &');
        }),
    ],
};
