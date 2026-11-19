import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    // Toggled explicitly via a `dark` class on <html> (see the theme
    // toggle in layouts/app.blade.php and layouts/admin.blade.php), not
    // the OS-preference `media` strategy, so the choice is a deliberate
    // per-user setting rather than following the device automatically.
    darkMode: 'class',
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
                // Professional-portal redesign: a deep navy primary (trust,
                // corporate finance — see brand-600 as the flagship button/
                // link color) replacing the earlier green-fintech identity,
                // paired with a muted gold/bronze `accent` for highlights and
                // money-needs-attention cues. Semantic colors (emerald for
                // success, red/amber for danger/warning) are left as
                // Tailwind's own palette, used directly, not aliased here.
                brand: {
                    50: '#f3f6fb',
                    100: '#e4eaf6',
                    200: '#c3d0e9',
                    300: '#9bb0d8',
                    400: '#7b9bcf',
                    500: '#52709f',
                    600: '#334e82',
                    700: '#28406c',
                    800: '#1f3254',
                    900: '#16233c',
                    950: '#0c1526',
                },
                accent: {
                    50: '#fbf6ec',
                    100: '#f5e9d2',
                    200: '#e9cd9e',
                    300: '#dbb069',
                    400: '#cc953f',
                    500: '#b8802c',
                    600: '#966321',
                    700: '#734b1a',
                    800: '#553816',
                    900: '#3c2912',
                    950: '#211709',
                },
            },
            boxShadow: {
                soft: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.04)',
                card: '0 1px 2px 0 rgb(15 23 42 / 0.03), 0 8px 24px -8px rgb(15 23 42 / 0.08)',
                'card-hover': '0 4px 12px 0 rgb(15 23 42 / 0.05), 0 16px 32px -12px rgb(15 23 42 / 0.14)',
                glow: '0 0 0 4px rgb(184 128 44 / 0.16)',
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
                // Ambient background shapes for the "3D" hero/CTA treatment
                // (see .orb-3d in app.css) — a slow, organic drift in three
                // dimensions rather than a flat left-right float.
                drift: {
                    '0%, 100%': { transform: 'translate3d(0, 0, 0) rotate3d(1, 1, 0, 0deg) scale(1)' },
                    '33%': { transform: 'translate3d(14px, -18px, 0) rotate3d(1, 1, 0, 8deg) scale(1.05)' },
                    '66%': { transform: 'translate3d(-10px, 10px, 0) rotate3d(1, 1, 0, -6deg) scale(0.97)' },
                },
                'spin-slow': {
                    '0%': { transform: 'rotate(0deg)' },
                    '100%': { transform: 'rotate(360deg)' },
                },
            },
            animation: {
                'fade-in': 'fade-in .5s ease-out both',
                'fade-up': 'fade-up .6s cubic-bezier(.16,1,.3,1) both',
                'scale-in': 'scale-in .2s cubic-bezier(.16,1,.3,1) both',
                shimmer: 'shimmer 2.5s linear infinite',
                float: 'float 6s ease-in-out infinite',
                drift: 'drift 12s ease-in-out infinite',
                'spin-slow': 'spin-slow 18s linear infinite',
            },
            transitionTimingFunction: {
                smooth: 'cubic-bezier(.16,1,.3,1)',
            },
        },
    },
    plugins: [],
};
