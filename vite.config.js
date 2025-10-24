import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    // Configurazione server per ambiente Docker
    server: {
        host: true, // 0.0.0.0
        port: parseInt(process.env.VITE_PORT ?? '5174'),
        strictPort: true,
        hmr: {
            host: 'localhost',
            port: parseInt(process.env.VITE_PORT ?? '5174'),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
