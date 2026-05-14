import { ReactNode } from 'react';
import clsx from 'clsx';

/**
 * Raggruppa le azioni dell’header su desktop; `PageHeader` nasconde già tutto il blocco `actions` sotto `lg`.
 */
export function IndexPageHeaderActions({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}): ReactNode {
    return <div className={clsx('flex flex-wrap items-center gap-2', className)}>{children}</div>;
}

/**
 * CTA secondarie (es. Importa, link a strumenti) solo su viewport &lt; lg, sopra il contenuto principale.
 */
export function IndexPageMobileToolbar({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}): ReactNode {
    return (
        <div className={clsx('mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap lg:hidden', className)}>
            {children}
        </div>
    );
}
