// Umami Analytics integration for React layouts
// Usage: <UmamiAnalytics />
import { useEffect } from 'react';


export default function UmamiAnalytics() {
    useEffect(() => {
        if (document.getElementById('umami-script')) return;
        const script = document.createElement('script');
        script.defer = true;
        script.src = 'https://cloud.umami.is/script.js';
        script.setAttribute('data-website-id', import.meta.env.VITE_UMAMI_ID);
        script.id = 'umami-script';
        document.body.appendChild(script);
    }, []);
    return null;
}
