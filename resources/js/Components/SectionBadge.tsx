import clsx from 'clsx';
import { ReactNode } from 'react';

interface SectionBadgeProps {
    label: string;
    icon?: ReactNode;
    tone?: 'neutral' | 'danger';
    className?: string;
}

export default function SectionBadge({
    label,
    icon,
    tone = 'neutral',
    className = '',
}: SectionBadgeProps) {
    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium',
                tone === 'danger'
                    ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300'
                    : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                className
            )}
        >
            {icon}
            {label}
        </span>
    );
}
