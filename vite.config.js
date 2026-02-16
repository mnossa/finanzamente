import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const port = env.VITE_PORT ? parseInt(env.VITE_PORT) : 5174;
    return {
        server: {
            port,
            host: '0.0.0.0',
            hmr: {
                host: 'localhost',
                port: port,
            },
        },
        plugins: [
            laravel({
                input: [
                    'resources/js/app.tsx',
                    'resources/js/app-blade.js',
                ],
                refresh: true,
            }),
            react(),
        ],
    };
});
