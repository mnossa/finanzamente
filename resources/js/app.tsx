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

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        console.info('[Finanzamente] Mount Inertia React', {
            initialComponent: props.initialPage?.component,
        });
        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
