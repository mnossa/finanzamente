import React from 'react';
import clsx from 'clsx';

interface PaginationProps {
    data: {
        current_page: number;
        last_page: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    onPageChange?: (page: number) => void;
    className?: string;
}

export const Pagination: React.FC<PaginationProps> = ({ data, onPageChange, className }) => {
    if (!data || data.last_page <= 1) return null;
    return (
        <nav className={clsx('flex justify-center mt-4', className)} aria-label="Navigazione pagine">
            <ul className="inline-flex -space-x-px">
                {data.links.map((link, idx) => {
                    const page = Number(link.label) || null;
                    const isClickable = !!link.url && page;
                    return (
                        <li key={idx}>
                            <button
                                type="button"
                                className={clsx(
                                    'px-3 py-1 rounded border text-sm',
                                    link.active ? 'bg-primary-500 text-white' : 'bg-white text-gray-700',
                                    !isClickable && 'cursor-not-allowed opacity-50'
                                )}
                                disabled={!isClickable}
                                onClick={() => isClickable && onPageChange && onPageChange(page!)}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
};
