import clsx from 'clsx';
import { ReactNode } from 'react';

interface RecurringFrequencyBadgeProps {
    frequency: string;
    frequencyLabel: string;
    className?: string;
}

const colors: Record<string, string> = {
    daily: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    weekly: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    monthly: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    yearly: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
};

const icons: Record<string, string> = {
    daily: '📅',
    weekly: '📆',
    monthly: '🗓️',
    yearly: '📋',
};

export default function RecurringFrequencyBadge({
    frequency,
    frequencyLabel,
    className,
}: RecurringFrequencyBadgeProps): ReactNode {
    return (
        <span
            className={clsx(
                'inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium sm:text-xs',
                colors[frequency] || colors.monthly,
                className,
            )}
        >
            {icons[frequency]} {frequencyLabel}
        </span>
    );
}
