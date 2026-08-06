// Entry point for Blade templates (non-Inertia pages)
// This file imports only the CSS without React/Inertia dependencies
import '../css/app.css';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
