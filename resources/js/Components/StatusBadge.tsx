import clsx from 'clsx';

interface StatusBadgeProps {
    status: string;
    statusLabel: string;
    isOverdue?: boolean;
    size?: 'sm' | 'md'; // 'sm' for xs, 'md' for normal
}

export function StatusBadge({ status, statusLabel, isOverdue = false, size = 'md' }: StatusBadgeProps) {
    if (isOverdue) {
        return (
            <span
                className={clsx(
                    'rounded-full',
                    size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm',
                    'font-medium',
                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                )}
            >
                ⚠️ Scaduto
            </span>
        );
    }

    // FinancialGoals
    const fgClasses: Record<string, string> = {
        in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        reached: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        cancelled: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    };
    const fgIcons: Record<string, string> = {
        in_progress: '🎯',
        reached: '✅',
        cancelled: '❌',
    };

    // DebtsCredits
    const dcClasses: Record<string, string> = {
        open: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        closed: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
        overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };

    let classes = fgClasses[status] || dcClasses[status] || fgClasses.in_progress;
    let icon = fgIcons[status] || '';

    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1 rounded-full font-medium',
                size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm',
                classes
            )}
        >
            {icon && <span>{icon}</span>} {statusLabel}
        </span>
    );
}
