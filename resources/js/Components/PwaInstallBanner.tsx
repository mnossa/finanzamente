import { useEffect, useState } from 'react';
import clsx from 'clsx';

interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const DISMISS_KEY = 'pwa_install_dismissed';

export default function PwaInstallBanner() {
    const [deferredPrompt, setDeferredPrompt] = useState<BeforeInstallPromptEvent | null>(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (localStorage.getItem(DISMISS_KEY) === '1') {
            return;
        }

        const handler = (e: Event) => {
            e.preventDefault();
            setDeferredPrompt(e as BeforeInstallPromptEvent);
            setVisible(true);
        };

        window.addEventListener('beforeinstallprompt', handler);

        return () => window.removeEventListener('beforeinstallprompt', handler);
    }, []);

    const handleInstall = async () => {
        if (!deferredPrompt) {
            return;
        }
        await deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        setVisible(false);
        setDeferredPrompt(null);
    };

    const handleDismiss = () => {
        localStorage.setItem(DISMISS_KEY, '1');
        setVisible(false);
    };

    if (!visible) {
        return null;
    }

    return (
        <div
            className={clsx(
                'fixed bottom-20 left-3 right-3 z-50 mx-auto max-w-md rounded-xl border border-emerald-200',
                'bg-white p-4 shadow-lg dark:border-emerald-800 dark:bg-gray-900 sm:bottom-6'
            )}
            role="region"
            aria-label="Installa applicazione"
        >
            <p className="text-sm font-semibold text-gray-900 dark:text-white">Installa Finanzamente</p>
            <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">
                Aggiungi l&apos;app alla schermata Home per un accesso rapido.
            </p>
            <div className="mt-3 flex gap-2">
                <button
                    type="button"
                    onClick={handleInstall}
                    className="flex-1 rounded-lg bg-emerald-700 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-800"
                >
                    Installa
                </button>
                <button
                    type="button"
                    onClick={handleDismiss}
                    className="rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Non ora
                </button>
            </div>
        </div>
    );
}
