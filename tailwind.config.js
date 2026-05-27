import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#4f46e5', // indigo-600 — primary action
                    hover:   '#4338ca', // indigo-700
                    light:   '#e0e7ff', // indigo-100 — tag backgrounds
                },
                affiliate: {
                    DEFAULT: '#f97316', // orange-500 — Amazon CTA
                    hover:   '#ea580c', // orange-600
                },
            },
            maxWidth: {
                content: '72rem', // 1152px — public content width (max-w-6xl)
                admin:   '80rem', // 1280px — admin content width (max-w-7xl)
            },
        },
    },

    plugins: [forms, typography],
};
