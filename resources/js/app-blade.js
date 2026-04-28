// Entry point for Blade templates (non-Inertia pages)
// This file imports only the CSS without React/Inertia dependencies
import '../css/app.css';
import Alpine from 'alpinejs';
import { trackEvent } from './utils/tracking';

window.Alpine = Alpine;
Alpine.start();

const ANALYTICS_CONSENT_KEY = 'fm_analytics_consent';

function loadUmamiScript(websiteId) {
    if (!websiteId) return;
    if (document.getElementById('umami-script')) return;

    const script = document.createElement('script');
    script.defer = true;
    script.src = 'https://cloud.umami.is/script.js';
    script.setAttribute('data-website-id', websiteId);
    script.id = 'umami-script';
    document.body.appendChild(script);
}

function setupAnalyticsConsentBanner() {
    const banner = document.getElementById('analytics-consent-banner');
    const acceptBtn = document.getElementById('analytics-consent-accept');
    const rejectBtn = document.getElementById('analytics-consent-reject');
    const websiteId = document.body.getAttribute('data-umami-id') ?? '';

    if (!banner) return;

    const storedChoice = localStorage.getItem(ANALYTICS_CONSENT_KEY);
    if (storedChoice === 'accepted') {
        loadUmamiScript(websiteId);
        banner.classList.add('hidden');
        return;
    }
    if (storedChoice === 'rejected') {
        banner.classList.add('hidden');
        return;
    }

    banner.classList.remove('hidden');

    acceptBtn?.addEventListener('click', () => {
        localStorage.setItem(ANALYTICS_CONSENT_KEY, 'accepted');
        loadUmamiScript(websiteId);
        banner.classList.add('hidden');
    });

    rejectBtn?.addEventListener('click', () => {
        localStorage.setItem(ANALYTICS_CONSENT_KEY, 'rejected');
        banner.classList.add('hidden');
    });
}

if (typeof window !== 'undefined') {
    window.trackEvent = trackEvent;
    setupAnalyticsConsentBanner();
}
