import '../css/app.css';
import './bootstrap';
// Import helpers (trasferimenti) e li espone su window per uso non-SPA
import * as Transfers from './transfers/estimate';
declare global {
    interface Window {
        Transfers?: typeof Transfers;
    }
}
if (typeof window !== 'undefined') {
    window.Transfers = window.Transfers || Transfers;
    // Bridge base per notifiche PWA: il service worker può inviare
    // postMessage con payload { type: 'FINANZAMENTE_NOTIFY', title, body }.
    if ('serviceWorker' in navigator && 'Notification' in window) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            const payload = event.data as { type?: string; title?: string; body?: string; url?: string };
            if (payload?.type !== 'FINANZAMENTE_NOTIFY') {
                return;
            }
            if (Notification.permission === 'granted') {
                navigator.serviceWorker.ready
                    .then((registration) => registration.showNotification(payload.title ?? 'Finanzamente', {
                        body: payload.body ?? '',
                        data: payload.url ? { url: payload.url } : undefined,
                    }))
                    .catch(() => undefined);
            }
        });
    }
}

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@/contexts/ThemeContext';
import { BalancePrivacyProvider } from '@/contexts/BalancePrivacyContext';
import NavigationBlockingOverlay from '@/Components/NavigationBlockingOverlay';
import PwaUpdatePrompt from '@/Components/PwaUpdatePrompt';

const appName = import.meta.env.VITE_APP_NAME || 'Finanzamente';

function extractUserPreferences(initialPage: { props?: Record<string, unknown> } | undefined): Record<string, unknown> | undefined {
    const auth = initialPage?.props?.auth as { user?: { preferences?: Record<string, unknown> } } | undefined;

    return auth?.user?.preferences && typeof auth.user.preferences === 'object'
        ? (auth.user.preferences as Record<string, unknown>)
        : undefined;
}

function extractInitialThemeFromPage(initialPage: { props?: Record<string, unknown> } | undefined): string | undefined {
    const raw = extractUserPreferences(initialPage)?.theme;

    return raw === 'dark' || raw === 'light' ? raw : undefined;
}

function extractInitialHideBalances(initialPage: { props?: Record<string, unknown> } | undefined): boolean | undefined {
    const raw = extractUserPreferences(initialPage)?.hide_balances;

    return typeof raw === 'boolean' ? raw : undefined;
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
        const initialHideBalances = extractInitialHideBalances(props.initialPage);
        console.info('[Finanzamente] Mount Inertia React', {
            initialComponent: props.initialPage?.component,
        });
        root.render(
            <ThemeProvider initialTheme={initialTheme}>
                <BalancePrivacyProvider initialHideBalances={initialHideBalances}>
                    <NavigationBlockingOverlay>
                        <App {...props} />
                        <PwaUpdatePrompt />
                    </NavigationBlockingOverlay>
                </BalancePrivacyProvider>
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
