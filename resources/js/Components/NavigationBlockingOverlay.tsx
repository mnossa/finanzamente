import { consumeHubNavSkipOverlay } from '@/utils/sectionHubNav';
import { router } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';

/**
 * Overlay full-screen durante navigazione Inertia: blocca click accidentali
 * mentre la barra di progresso in alto è attiva.
 */
export default function NavigationBlockingOverlay({ children }: { children: ReactNode }) {
    const [isNavigating, setIsNavigating] = useState(false);

    useEffect(() => {
        const removeStart = router.on('start', () => {
            if (consumeHubNavSkipOverlay()) {
                return;
            }

            setIsNavigating(true);
        });
        const removeFinish = router.on('finish', () => setIsNavigating(false));
        const removeCancel = router.on('cancel', () => setIsNavigating(false));

        return () => {
            removeStart();
            removeFinish();
            removeCancel();
        };
    }, []);

    return (
        <>
            {children}
            {isNavigating && (
                <div
                    className="fixed inset-0 z-[100] cursor-wait bg-white/40 backdrop-blur-[1px] dark:bg-gray-900/50"
                    aria-busy="true"
                    aria-label="Caricamento pagina in corso"
                />
            )}
        </>
    );
}
