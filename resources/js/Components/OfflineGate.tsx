import { useEffect, useState } from 'react';

export default function OfflineGate({ children }: { children: React.ReactNode }) {
    const [isOffline, setIsOffline] = useState(
        typeof navigator !== 'undefined' ? !navigator.onLine : false
    );

    useEffect(() => {
        const goOffline = () => setIsOffline(true);
        const goOnline = () => setIsOffline(false);

        window.addEventListener('offline', goOffline);
        window.addEventListener('online', goOnline);

        return () => {
            window.removeEventListener('offline', goOffline);
            window.removeEventListener('online', goOnline);
        };
    }, []);

    if (isOffline) {
        return (
            <div className="fixed inset-0 z-100 flex flex-col items-center justify-center bg-primary-600 px-6 text-center text-white">
                <span className="text-5xl drop-shadow-sm" aria-hidden>
                    📡
                </span>
                <h1 className="mt-4 text-xl font-bold text-white">
                    Connessione assente
                </h1>
                <p className="mt-2 max-w-sm text-sm text-primary-100">
                    Per usare Finanzamente serve una connessione internet attiva. I tuoi dati non
                    vengono salvati in locale su questo dispositivo.
                </p>
                <button
                    type="button"
                    className="mt-6 rounded-xl border border-white/30 bg-white px-4 py-2.5 text-sm font-semibold text-primary-700 shadow-lg transition hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-primary-600"
                    onClick={() => window.location.reload()}
                >
                    Riprova
                </button>
            </div>
        );
    }

    return <>{children}</>;
}
