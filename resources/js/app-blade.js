// Entry point for Blade templates (non-Inertia pages)
// This file imports only the CSS without React/Inertia dependencies
import '../css/app.css';
import { trackEvent } from './utils/tracking';

if (typeof window !== 'undefined') {
    window.trackEvent = trackEvent;
}
