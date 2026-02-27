// Wrapper universale per il tracking eventi (Umami o altri provider in futuro)
// Utilizzabile sia in Blade che in React

export function trackEvent(eventName, eventData = {}) {
    if (typeof window !== 'undefined' && window.umami && typeof window.umami.track === 'function') {
        window.umami.track(eventName, eventData);
    } else {
        // Fallback: puoi loggare o ignorare
        if (process.env.NODE_ENV === 'development') {
            // eslint-disable-next-line no-console
            console.log('[Tracking] Evento:', eventName, eventData);
        }
    }
}
