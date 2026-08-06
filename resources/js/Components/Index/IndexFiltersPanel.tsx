import ContentPanelShell from '@/Components/ContentPanelShell';
import { mobileFilterBodyClass, mobileFilterSummaryClass } from '@/Components/IndexPageListToolbars';
import clsx from 'clsx';
import { ReactNode } from 'react';

interface IndexFiltersPanelProps {
    /** Apri il pannello di default (es. filtri attivi) */
    defaultOpen?: boolean;
    /** Badge accanto a "Filtri" */
    activeBadge?: ReactNode;
    children: ReactNode;
    className?: string;
}

/**
 * Pannello filtri collapsible standard per pagine indice.
 */
export default function IndexFiltersPanel({
    defaultOpen = false,
    activeBadge,
    children,
    className,
}: IndexFiltersPanelProps): ReactNode {
    return (
        <ContentPanelShell variant="index" className={clsx('mb-2 shadow-sm sm:mb-4', className)} wrapBody={false}>
            <details className="group" {...(defaultOpen ? { open: true } : {})}>
                <summary
                    className={clsx(
                        'flex cursor-pointer select-none list-none items-center justify-between text-sm font-medium text-gray-700 dark:text-gray-200',
                        mobileFilterSummaryClass,
                    )}
                >
                    <span className="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                        </svg>
                        Filtri
                        {activeBadge}
                    </span>
                    <svg className="h-4 w-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                </summary>
                <div className={clsx('border-t border-gray-100 dark:border-gray-700', mobileFilterBodyClass)}>
                    {children}
                </div>
            </details>
        </ContentPanelShell>
    );
}
