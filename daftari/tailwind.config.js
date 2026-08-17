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
                // Figtree was declared here but never actually loaded
                // anywhere (no <link>/@font-face), so every page has been
                // silently falling back to the OS default font the whole
                // time. Cairo is loaded via Google Fonts in every layout
                // and the PDF print template, and — unlike Figtree —
                // actually supports Arabic, matching this app's bilingual
                // content instead of leaving Arabic text to an inconsistent
                // system fallback.
                sans: ['Cairo', ...defaultTheme.fontFamily.sans],
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
            boxShadow: {
                soft: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.04)',
                card: '0 1px 2px 0 rgb(15 23 42 / 0.03), 0 8px 24px -8px rgb(15 23 42 / 0.08)',
                'card-hover': '0 4px 12px 0 rgb(15 23 42 / 0.05), 0 16px 32px -12px rgb(15 23 42 / 0.14)',
                glow: '0 0 0 4px rgb(26 178 126 / 0.12)',
                nav: '0 1px 0 0 rgb(255 255 255 / 0.06) inset',
            },
            borderRadius: {
                xl2: '1.25rem',
            },
            keyframes: {
                'fade-in': {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'scale-in': {
                    '0%': { opacity: '0', transform: 'scale(0.96)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
                shimmer: {
                    '0%': { backgroundPosition: '-200% 0' },
                    '100%': { backgroundPosition: '200% 0' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-8px)' },
                },
            },
            animation: {
                'fade-in': 'fade-in .5s ease-out both',
                'fade-up': 'fade-up .6s cubic-bezier(.16,1,.3,1) both',
                'scale-in': 'scale-in .2s cubic-bezier(.16,1,.3,1) both',
                shimmer: 'shimmer 2.5s linear infinite',
                float: 'float 6s ease-in-out infinite',
            },
            transitionTimingFunction: {
                smooth: 'cubic-bezier(.16,1,.3,1)',
            },
        },
    },
    plugins: [],
};
