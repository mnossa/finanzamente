import { trackEvent } from './utils/tracking';
import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Estensione del tipo AxiosRequestConfig per aggiungere metadata
import type { InternalAxiosRequestConfig } from 'axios';

declare module 'axios' {
    interface InternalAxiosRequestConfig {
        metadata?: { startTime: number };
    }
}

// Interceptor per tracking richieste lente
axios.interceptors.request.use((config) => {
    config.metadata = { startTime: Date.now() };
    return config;
});
axios.interceptors.response.use(
    (response) => {
        const duration: number = Date.now() - (response.config.metadata?.startTime ?? 0);
        if (duration > 3000) {
            trackEvent('ajax_lenta', {
                url: response.config.url,
                method: response.config.method,
                durata_ms: duration,
            });
        }
        return response;
    },
    (error) => {
        if (error.config && error.config.metadata && error.config.url) {
            const duration: number = Date.now() - (error.config.metadata.startTime ?? 0);
            if (duration > 3000) {
                trackEvent('ajax_lenta', {
                    url: error.config.url,
                    method: error.config.method,
                    durata_ms: duration,
                    errore: true,
                });
            }
        }
        return Promise.reject(error);
    }
);
