import { useEffect, useState } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';

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
            <div className="fixed inset-0 z-100 flex flex-col items-center justify-center bg-white px-6 text-center dark:bg-gray-900">
                <span className="text-5xl" aria-hidden>
                    📡
                </span>
                <h1 className="mt-4 text-xl font-bold text-gray-900 dark:text-white">
                    Connessione assente
                </h1>
                <p className="mt-2 max-w-sm text-sm text-gray-600 dark:text-gray-400">
                    Per usare Finanzamente serve una connessione internet attiva. I tuoi dati non
                    vengono salvati in locale su questo dispositivo.
                </p>
                <PrimaryButton className="mt-6" onClick={() => window.location.reload()}>
                    Riprova
                </PrimaryButton>
            </div>
        );
    }

    return <>{children}</>;
}
