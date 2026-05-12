// Umami Analytics integration for React layouts
// Usage: <UmamiAnalytics />
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import { useEffect } from 'react';

export default function UmamiAnalytics({ enabled = false }: { enabled?: boolean }) {
    const { umami } = usePage<PageProps>().props;
    const websiteId =
        (umami?.websiteId?.trim() ?? '') ||
        (typeof import.meta.env.VITE_UMAMI_ID === 'string' ? String(import.meta.env.VITE_UMAMI_ID).trim() : '');

    useEffect(() => {
        const existingScript = document.getElementById('umami-script');

        if (!enabled || !websiteId) {
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
        script.setAttribute('data-website-id', websiteId);
        script.id = 'umami-script';
        document.body.appendChild(script);
    }, [enabled, websiteId]);
    return null;
}
