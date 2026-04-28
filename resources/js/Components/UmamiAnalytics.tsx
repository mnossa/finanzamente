// Umami Analytics integration for React layouts
// Usage: <UmamiAnalytics />
import { useEffect } from 'react';


export default function UmamiAnalytics({ enabled = false }: { enabled?: boolean }) {
    useEffect(() => {
        const existingScript = document.getElementById('umami-script');

        if (!enabled) {
            if (existingScript) existingScript.remove();
            if (typeof window !== 'undefined' && 'umami' in window) {
                delete (window as Window & { umami?: unknown }).umami;
            }
            return;
        }

        if (document.getElementById('umami-script')) return;
        const script = document.createElement('script');
        script.defer = true;
        script.src = 'https://cloud.umami.is/script.js';
        script.setAttribute('data-website-id', import.meta.env.VITE_UMAMI_ID);
        script.id = 'umami-script';
        document.body.appendChild(script);
    }, [enabled]);
    return null;
}
