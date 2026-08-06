import React from 'react';
import clsx from 'clsx';

interface ProgressBarProps {
    percentage: number;
    isExceeded?: boolean;
    color?: string | null;
    className?: string;
    height?: string;
}

export const ProgressBar: React.FC<ProgressBarProps> = ({ percentage, isExceeded, color, className, height }) => {
    const barColor = color || (isExceeded ? 'bg-red-500' : percentage >= 80 ? 'bg-amber-500' : 'bg-emerald-500');
    return (
        <div className={clsx('w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700', className)} style={{ height: height || '1rem' }}>
            <div
                className={clsx('h-full rounded-full transition-all', barColor)}
                style={{ width: `${Math.min(100, percentage)}%` }}
            />
        </div>
    );
};
