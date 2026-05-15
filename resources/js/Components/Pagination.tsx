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

const renderLabel = (label: string) => {
    if (label.includes('&laquo;') || label.toLowerCase().includes('previous')) {
        return '← Precedente';
    }
    if (label.includes('&raquo;') || label.toLowerCase().includes('next')) {
        return 'Successiva →';
    }
    return label;
};

export const Pagination: React.FC<PaginationProps> = ({ data, className }) => {
    if (!data || data.last_page <= 1) return null;

    const handlePageChange = (url: string | null) => {
        if (!url) return;
        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const prevLink = data.links[0];
    const nextLink = data.links[data.links.length - 1];

    const pageButtonClass = (link: { url: string | null; active: boolean }, isNav = false) =>
        clsx(
            'inline-flex min-h-11 items-center justify-center rounded-lg border px-3 py-2.5 text-sm font-medium transition-all duration-200',
            'focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1',
            {
                'bg-emerald-600 text-white border-emerald-600 shadow-sm hover:bg-emerald-700 dark:bg-emerald-500 dark:hover:bg-emerald-600': link.active,
                'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 hover:border-gray-400 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700': !link.active && link.url,
                'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed dark:bg-gray-900 dark:text-gray-600 dark:border-gray-800': !link.url,
                'flex-1': isNav,
                'min-w-[40px] shrink-0': !isNav,
            },
        );

    return (
        <div className={clsx('border-t border-gray-100 bg-gray-50 px-4 py-4 dark:border-gray-700 dark:bg-gray-800/50', className)}>
            <div className="flex flex-col gap-4">
                <div className="text-center text-sm text-gray-600 dark:text-gray-400 sm:text-left">
                    Mostrando <span className="font-medium text-gray-900 dark:text-white">{data.from}</span> -{' '}
                    <span className="font-medium text-gray-900 dark:text-white">{data.to}</span> di{' '}
                    <span className="font-medium text-gray-900 dark:text-white">{data.total}</span> risultati
                </div>

                <nav className="grid w-full grid-cols-2 gap-2 sm:hidden" aria-label="Paginazione">
                    <button
                        type="button"
                        onClick={() => handlePageChange(prevLink.url)}
                        disabled={!prevLink.url}
                        className={pageButtonClass(prevLink, true)}
                        aria-label="Pagina precedente"
                    >
                        {renderLabel(prevLink.label)}
                    </button>
                    <button
                        type="button"
                        onClick={() => handlePageChange(nextLink.url)}
                        disabled={!nextLink.url}
                        className={pageButtonClass(nextLink, true)}
                        aria-label="Pagina successiva"
                    >
                        {renderLabel(nextLink.label)}
                    </button>
                </nav>
                <p className="text-center text-xs text-gray-500 dark:text-gray-400 sm:hidden">
                    Pagina {data.current_page} di {data.last_page}
                </p>

                <div className="hidden sm:flex sm:flex-row sm:items-center sm:justify-end">
                    <nav
                        className="flex max-w-full items-center gap-1 overflow-x-auto pb-1"
                        aria-label="Navigazione pagine"
                    >
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
                                    className={clsx(pageButtonClass(link), {
                                        'min-w-[100px] sm:min-w-[120px]': isFirst || isLast,
                                        'min-w-[40px]': isNumber,
                                    })}
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
        </div>
    );
};
