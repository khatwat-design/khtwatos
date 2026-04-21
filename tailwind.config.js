import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

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
                    50: '#fdf4f3',
                    100: '#fce8e6',
                    200: '#f7cfcb',
                    300: '#eda8a2',
                    400: '#e07870',
                    500: '#c94a42',
                    600: '#B2342E',
                    700: '#942a25',
                    800: '#7b2420',
                    900: '#66211e',
                    950: '#3f1310',
                },
            },
            fontFamily: {
                sans: ['Cairo', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
