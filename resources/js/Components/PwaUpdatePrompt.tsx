import { useCallback, useEffect, useState } from 'react';
import clsx from 'clsx';
import { registerSW } from 'virtual:pwa-register';

function scheduleIdleTask(task: () => void): void {
    if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(() => task(), { timeout: 4000 });

        return;
    }

    window.setTimeout(task, 1500);
}

export default function PwaUpdatePrompt() {
    const [needRefresh, setNeedRefresh] = useState(false);
    const [reloadApp, setReloadApp] = useState<((reloadPage?: boolean) => Promise<void>) | null>(null);

    useEffect(() => {
        if (!import.meta.env.PROD) {
            return;
        }

        let cancelled = false;

        const register = () => {
            if (cancelled) {
                return;
            }

            const update = registerSW({
                immediate: true,
                onNeedRefresh() {
                    setNeedRefresh(true);
                },
            });

            setReloadApp(() => update);
        };

        const onLoad = () => scheduleIdleTask(register);
        if (document.readyState === 'complete') {
            onLoad();
        } else {
            window.addEventListener('load', onLoad, { once: true });
        }

        return () => {
            cancelled = true;
            window.removeEventListener('load', onLoad);
        };
    }, []);

    const handleReload = useCallback(() => {
        void reloadApp?.(true);
    }, [reloadApp]);

    const handleDismiss = useCallback(() => {
        setNeedRefresh(false);
    }, []);

    if (!needRefresh) {
        return null;
    }

    return (
        <div
            className={clsx(
                'fixed bottom-20 left-3 right-3 z-[60] mx-auto max-w-md rounded-xl border border-violet-200',
                'bg-white p-4 shadow-lg dark:border-violet-800 dark:bg-gray-900 sm:bottom-6',
            )}
            role="region"
            aria-label="Aggiornamento applicazione"
            aria-live="polite"
        >
            <p className="text-sm font-semibold text-gray-900 dark:text-white">Nuova versione disponibile</p>
            <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">
                Ricarica l&apos;app per usare l&apos;ultimo aggiornamento.
            </p>
            <div className="mt-3 flex gap-2">
                <button
                    type="button"
                    onClick={handleReload}
                    className="flex-1 rounded-lg bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700"
                >
                    Ricarica
                </button>
                <button
                    type="button"
                    onClick={handleDismiss}
                    className="rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Più tardi
                </button>
            </div>
        </div>
    );
}
