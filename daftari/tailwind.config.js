import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#eefdf6',
                    100: '#d6f9e8',
                    200: '#aef1d3',
                    300: '#75e3b6',
                    400: '#3fcd97',
                    500: '#1ab27e',
                    600: '#0f9068',
                    700: '#0d7355',
                    800: '#0e5c46',
                    900: '#0c4b3a',
                    950: '#052a20',
                },
            },
        },
    },
    plugins: [],
};
