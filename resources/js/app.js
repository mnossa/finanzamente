// Entry point JS per compatibilità manifest Vite.
// Importa il vero bootstrap React scritto in TypeScript (app.tsx)
import './app.tsx';import './bootstrap';
import * as Transfers from './transfers/estimate';

// Expose helpers globally for Blade or non-SPA usage
if (typeof window !== 'undefined') {
	window.Transfers = Transfers;
}
