import clsx from 'clsx';
import { ReactNode } from 'react';

interface FormActionsBarProps {
    children: ReactNode;
    className?: string;
    /** Se false, resta nel flusso del form (in fondo al contenuto). Default: sticky viewport. */
    sticky?: boolean;
    /**
     * Padding bottom che libera nav mobile + safe-area (e spazio FAB).
     * Usa al posto di `pb-*` nel className: evita conflitto con `pb-3` interno (niente twMerge).
     */
    clearMobileChrome?: boolean;
}

/** Allineato a AuthenticatedLayout: nav h-12 + safe-area ≈ 4.5rem. */
const MOBILE_CHROME_CLEARANCE =
    'pb-[calc(5.5rem+env(safe-area-inset-bottom,0px))] sm:pb-3 lg:pb-0';

export default function FormActionsBar({
    children,
    className = '',
    sticky = true,
    clearMobileChrome = false,
}: FormActionsBarProps) {
    return (
        <div
            className={clsx(
                'flex flex-wrap items-center gap-3 border-t border-gray-200/80 dark:border-gray-700/80',
                sticky
                    ? 'sticky bottom-0 z-10 bg-white/95 dark:bg-slate-900/95 backdrop-blur-sm'
                    : null,
                '-mx-5 sm:mx-0 px-5 sm:px-0 pt-3 sm:pt-4',
                clearMobileChrome ? MOBILE_CHROME_CLEARANCE : 'pb-3 sm:pb-0',
                className,
            )}
        >
            {children}
        </div>
    );
}
