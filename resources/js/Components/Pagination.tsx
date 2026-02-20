import React from 'react';
import { router } from '@inertiajs/react';
import clsx from 'clsx';

interface PaginationProps {
    data: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    className?: string;
}

export const Pagination: React.FC<PaginationProps> = ({ data, className }) => {
    if (!data || data.last_page <= 1) return null;

    const handlePageChange = (url: string | null) => {
        if (!url) return;
        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const renderLabel = (label: string) => {
        // Converti i simboli HTML di Laravel in icone più moderne
        if (label.includes('&laquo;') || label.toLowerCase().includes('previous')) {
            return '← Precedente';
        }
        if (label.includes('&raquo;') || label.toLowerCase().includes('next')) {
            return 'Successiva →';
        }
        return label;
    };

    return (
        <div className={clsx('border-t border-gray-100 bg-gray-50 px-4 py-4 dark:border-gray-700 dark:bg-gray-800/50', className)}>
            <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                {/* Info risultati */}
                <div className="text-sm text-gray-600 dark:text-gray-400">
                    Mostrando <span className="font-medium text-gray-900 dark:text-white">{data.from}</span> - 
                    <span className="font-medium text-gray-900 dark:text-white">{data.to}</span> di{' '}
                    <span className="font-medium text-gray-900 dark:text-white">{data.total}</span> risultati
                </div>

                {/* Navigazione pagine */}
                <nav className="flex items-center gap-1" aria-label="Navigazione pagine">
                    {data.links.map((link, idx) => {
                        const isFirst = idx === 0;
                        const isLast = idx === data.links.length - 1;
                        const isNumber = !isFirst && !isLast;

                        return (
                            <button
                                key={idx}
                                type="button"
                                onClick={() => handlePageChange(link.url)}
                                disabled={!link.url}
                                className={clsx(
                                    'inline-flex items-center justify-center px-3 py-2 text-sm font-medium transition-all duration-200',
                                    'rounded-lg border focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1',
                                    {
                                        // Stile per pulsante attivo
                                        'bg-emerald-600 text-white border-emerald-600 shadow-sm hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600': link.active,
                                        // Stile per pulsanti cliccabili
                                        'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 hover:border-gray-400 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700': !link.active && link.url,
                                        // Stile per pulsanti disabilitati
                                        'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed dark:bg-gray-900 dark:text-gray-600 dark:border-gray-800': !link.url,
                                        // Dimensioni diverse per prev/next
                                        'min-w-[100px] sm:min-w-[120px]': isFirst || isLast,
                                        'min-w-[40px]': isNumber,
                                    }
                                )}
                                aria-label={isFirst ? 'Pagina precedente' : isLast ? 'Pagina successiva' : `Pagina ${link.label}`}
                                aria-current={link.active ? 'page' : undefined}
                            >
                                {renderLabel(link.label)}
                            </button>
                        );
                    })}
                </nav>
            </div>
        </div>
    );
};
