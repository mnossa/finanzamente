// Entry point for Blade templates (non-Inertia pages)
// This file imports only the CSS without React/Inertia dependencies
import '../css/app.css';
import Alpine from 'alpinejs';
import { trackEvent } from './utils/tracking';

window.Alpine = Alpine;
Alpine.start();

if (typeof window !== 'undefined') {
    window.trackEvent = trackEvent;
}
