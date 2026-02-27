// Umami Analytics integration for React layouts
// Usage: <UmamiAnalytics />
import { useEffect } from 'react';

const UMAMI_ID_PROD = '6366c67f-6c67-402f-a09b-43f6d73b780c';
const UMAMI_ID_DEV = '804a1613-7828-42fb-9cd8-c6ed1f34644d';

function getUmamiId() {
    // Vite exposes import.meta.env.MODE as 'production' or 'development'
    if (import.meta.env.MODE === 'production') return UMAMI_ID_PROD;
    return UMAMI_ID_DEV;
}

export default function UmamiAnalytics() {
    useEffect(() => {
        if (document.getElementById('umami-script')) return;
        const script = document.createElement('script');
        script.defer = true;
        script.src = 'https://cloud.umami.is/script.js';
        script.setAttribute('data-website-id', getUmamiId());
        script.id = 'umami-script';
        document.body.appendChild(script);
    }, []);
    return null;
}
