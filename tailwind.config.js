import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** Stack sans allineata al default storico Tailwind (senza import da `tailwindcss/defaultTheme`: v4 + exports rompono il resolver dell'estensione VS Code / Cursor). */
const defaultSansStack = [
    'ui-sans-serif',
    'system-ui',
    'sans-serif',
    'Apple Color Emoji',
    'Segoe UI Emoji',
    'Segoe UI Symbol',
    'Noto Color Emoji',
];

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
        './node_modules/@tremor/**/*.{js,ts,jsx,tsx}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', 'Inter', ...defaultSansStack],
            },
            colors: {
                // Design System Finanzamente
                // Deep Indigo - Autorità, stabilità, sicurezza
                primary: {
                    50: '#f0f4ff',
                    100: '#e0e8ff',
                    200: '#c7d4fe',
                    300: '#a4b8fc',
                    400: '#8093f8',
                    500: '#636ff1',
                    600: '#4f4ce5',
                    700: '#433dca',
                    800: '#3834a3',
                    900: '#323181',
                    950: '#1e1b4b',
                },
                // Emerald Green - Crescita, denaro, positività
                accent: {
                    50: '#ecfdf5',
                    100: '#d1fae5',
                    200: '#a7f3d0',
                    300: '#6ee7b7',
                    400: '#34d399',
                    500: '#10b981',
                    600: '#059669',
                    700: '#047857',
                    800: '#065f46',
                    900: '#064e3b',
                    950: '#022c22',
                },
                // Soft Gray - Sfondo pulito, riduce affaticamento visivo
                surface: {
                    50: '#f8fafc',
                    100: '#f1f5f9',
                    200: '#e2e8f0',
                    300: '#cbd5e1',
                    400: '#94a3b8',
                    500: '#64748b',
                    600: '#475569',
                    700: '#334155',
                    800: '#1e293b',
                    900: '#0f172a',
                    950: '#020617',
                },
            },
            boxShadow: {
                // Ombre soffuse per cards
                'soft': '0 2px 10px -4px rgba(0, 0, 0, 0.05)',
                'soft-md': '0 4px 16px -6px rgba(0, 0, 0, 0.08)',
                'soft-lg': '0 8px 24px -8px rgba(0, 0, 0, 0.1)',
                // Ombra colorata per CTA primaria
                'accent': '0 4px 14px -3px rgba(16, 185, 129, 0.35)',
                'accent-lg': '0 8px 20px -4px rgba(16, 185, 129, 0.4)',
                // Ombra per sidebar
                'sidebar': '4px 0 24px -4px rgba(0, 0, 0, 0.15)',
            },
            borderRadius: {
                '2xl': '1rem',
                '3xl': '1.5rem',
            },
            animation: {
                'fade-in': 'fadeIn 0.3s ease-out',
                'slide-in': 'slideIn 0.3s ease-out',
                'slide-in-left': 'slideInLeft 0.3s ease-out',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideIn: {
                    '0%': { opacity: '0', transform: 'translateY(-10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                slideInLeft: {
                    '0%': { opacity: '0', transform: 'translateX(-100%)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
            },
            transitionDuration: {
                '250': '250ms',
            },
        },
    },

    plugins: [forms, typography],
};
