import '../css/app.css';
import './bootstrap';
// Import helpers (trasferimenti) e li espone su window per uso non-SPA
import * as Transfers from './transfers/estimate';
// Importa il wrapper di tracking e lo espone su window per l'uso sia in React che in script inline
import { trackEvent } from './utils/tracking';
declare global {
    interface Window {
        Transfers?: typeof Transfers;
        trackEvent?: typeof trackEvent;
    }
}
if (typeof window !== 'undefined') {
    window.Transfers = window.Transfers || Transfers;
    window.trackEvent = trackEvent;
}

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@/contexts/ThemeContext';

const appName = import.meta.env.VITE_APP_NAME || 'Finanzamente';

function extractInitialThemeFromPage(initialPage: { props?: Record<string, unknown> } | undefined): string | undefined {
    const auth = initialPage?.props?.auth as { user?: { preferences?: Record<string, unknown> } } | undefined;
    const raw =
        auth?.user?.preferences && typeof auth.user.preferences === 'object'
            ? (auth.user.preferences as Record<string, unknown>).theme
            : undefined;
    return raw === 'dark' || raw === 'light' ? raw : undefined;
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        const initialTheme = extractInitialThemeFromPage(props.initialPage);
        console.info('[Finanzamente] Mount Inertia React', {
            initialComponent: props.initialPage?.component,
        });
        root.render(
            <ThemeProvider initialTheme={initialTheme}>
                <App {...props} />
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
