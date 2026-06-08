import clsx from 'clsx';
import { ReactNode } from 'react';

export type ContentPanelVariant = 'index' | 'dashboard';

const variantShellClass: Record<ContentPanelVariant, string> = {
    index: 'overflow-hidden rounded-2xl border border-gray-200/80 bg-white/95 shadow-sm backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800/95',
    dashboard: 'overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800',
};

interface ContentPanelShellProps {
    variant?: ContentPanelVariant;
    header?: ReactNode;
    bodyClassName?: string;
    className?: string;
    /** false = children non wrappati in corpo (slot multipli, es. IndexListCard) */
    wrapBody?: boolean;
    children: ReactNode;
}

/**
 * Shell condiviso per pannelli dashboard e sezioni pagina indice (header + corpo).
 */
export default function ContentPanelShell({
    variant = 'index',
    header,
    bodyClassName,
    className,
    wrapBody = true,
    children,
}: ContentPanelShellProps): ReactNode {
    return (
        <div className={clsx(variantShellClass[variant], className)}>
            {header}
            {wrapBody ? <div className={bodyClassName}>{children}</div> : children}
        </div>
    );
}
