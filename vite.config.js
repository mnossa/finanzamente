import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const port = env.VITE_PORT ? parseInt(env.VITE_PORT) : 5174;
    const hmrHost = env.APP_URL ? new URL(env.APP_URL).hostname : 'localhost';
    return {
        server: {
            port,
            host: '0.0.0.0',
            hmr: {
                host: hmrHost,
                port: port,
            },
            allowedHosts: ['pi-server', 'localhost'],
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
        build: {
            chunkSizeWarningLimit: 900,
            rollupOptions: {
                output: {
                    manualChunks(id) {
                        if (!id.includes('node_modules')) return;

                        if (id.includes('recharts')) {
                            return 'vendor-recharts';
                        }
                        if (id.includes('d3-') || id.includes('d3/') || id.includes('victory-vendor')) {
                            return 'vendor-d3';
                        }
                        if (id.includes('@tremor')) {
                            return 'vendor-tremor';
                        }
                        if (id.includes('@dnd-kit')) {
                            return 'vendor-dnd';
                        }
                        if (
                            id.includes('@inertiajs') ||
                            id.includes('react-dom') ||
                            id.includes('node_modules/react/') ||
                            id.includes('node_modules/react-is') ||
                            id.includes('scheduler')
                        ) {
                            return 'vendor-inertia';
                        }
                    },
                },
            },
        },
    };
});
