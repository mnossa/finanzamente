import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig(({ mode, command }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const port = env.VITE_PORT ? parseInt(env.VITE_PORT) : 5174;
    const hmrHost = env.APP_URL ? new URL(env.APP_URL).hostname : 'localhost';
    return {
        resolve: {
            dedupe: ['react', 'react-dom'],
            alias: {
                react: path.resolve(projectRoot, 'node_modules/react'),
                'react-dom': path.resolve(projectRoot, 'node_modules/react-dom'),
            },
        },
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
            VitePWA({
                registerType: 'prompt',
                injectRegister: false,
                filename: 'sw.js',
                manifestFilename: 'manifest.webmanifest',
                includeAssets: [
                    'images/finanzamente-logo.webp',
                    'pwa/icon-192.png',
                    'pwa/icon-512.png',
                    'pwa/icon-maskable-512.png',
                    'pwa/apple-touch-icon.png',
                    'pwa/apple-splash-1170x2532.png',
                    'pwa/apple-splash-1284x2778.png',
                ],
                manifest: {
                    id: '/',
                    name: 'Finanzamente',
                    short_name: 'Finanzamente',
                    description: 'Gestione finanziaria personale e familiare',
                    theme_color: '#4f4ce5',
                    background_color: '#4f4ce5',
                    display: 'standalone',
                    display_override: ['standalone', 'browser'],
                    orientation: 'portrait',
                    start_url: '/dashboard?source=pwa',
                    scope: '/',
                    lang: 'it',
                    dir: 'ltr',
                    categories: ['finance', 'productivity'],
                    prefer_related_applications: false,
                    icons: [
                        {
                            src: '/pwa/icon-192.png',
                            sizes: '192x192',
                            type: 'image/png',
                            purpose: 'any',
                        },
                        {
                            src: '/pwa/icon-192.png',
                            sizes: '192x192',
                            type: 'image/png',
                            purpose: 'maskable',
                        },
                        {
                            src: '/pwa/icon-512.png',
                            sizes: '512x512',
                            type: 'image/png',
                            purpose: 'any',
                        },
                        {
                            src: '/pwa/icon-maskable-512.png',
                            sizes: '512x512',
                            type: 'image/png',
                            purpose: 'maskable',
                        },
                    ],
                },
                workbox: {
                    cleanupOutdatedCaches: true,
                    clientsClaim: true,
                    skipWaiting: false,
                    navigateFallback: '/dashboard',
                    navigateFallbackDenylist: [
                        /^\/login/,
                        /^\/register/,
                        /^\/forgot-password/,
                        /^\/reset-password/,
                        /^\/verify-email/,
                        /^\/sanctum/,
                        /^\/api\//,
                        /^\/user\/passkeys/,
                        /^\/passkeys\//,
                        /^\/profilo\/sicurezza/,
                    ],
                    globPatterns: ['**/*.{js,css,html,ico,png,webp,svg,woff2}'],
                    runtimeCaching: [
                        {
                            // Passkey XHR must never hit SW cache / offline fallback.
                            urlPattern: ({ url, sameOrigin }) =>
                                sameOrigin &&
                                (url.pathname.startsWith('/user/passkeys') ||
                                    url.pathname.startsWith('/passkeys/')),
                            handler: 'NetworkOnly',
                        },
                        {
                            urlPattern: ({ request, sameOrigin }) =>
                                sameOrigin && request.mode === 'navigate',
                            handler: 'NetworkFirst',
                            options: {
                                cacheName: 'finanzamente-pages',
                                expiration: {
                                    maxEntries: 48,
                                    maxAgeSeconds: 60 * 60 * 24,
                                },
                                networkTimeoutSeconds: 8,
                            },
                        },
                        {
                            urlPattern: /^https:\/\/fonts\.bunny\.net\/.*/i,
                            handler: 'CacheFirst',
                            options: {
                                cacheName: 'bunny-fonts',
                                expiration: {
                                    maxEntries: 10,
                                    maxAgeSeconds: 60 * 60 * 24 * 365,
                                },
                            },
                        },
                    ],
                },
                devOptions: {
                    enabled: false,
                },
            }),
        ],
        build: {
            chunkSizeWarningLimit: 900,
            minify: 'oxc',
            modulePreload: {
                resolveDependencies: (filename, deps) =>
                    deps.filter(
                        (dep) => !dep.includes('vendor-dnd') && !dep.includes('vendor-recharts'),
                    ),
            },
            rollupOptions: {
                output: {
                    ...(command === 'build'
                        ? {
                              minify: {
                                  mangle: true,
                                  compress: {
                                      dropConsole: true,
                                      dropDebugger: true,
                                  },
                              },
                          }
                        : {}),
                    manualChunks(id) {
                        if (!id.includes('node_modules')) return;

                        if (
                            id.includes('/react/') ||
                            id.includes('/react-dom/') ||
                            id.includes('/react-is/') ||
                            id.includes('/scheduler/')
                        ) {
                            return 'vendor-react';
                        }
                        if (id.includes('/clsx/') || id.includes('@inertiajs')) {
                            return 'vendor-inertia';
                        }
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
                    },
                },
            },
        },
    };
});
