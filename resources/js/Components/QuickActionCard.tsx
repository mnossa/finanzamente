import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import { ReactNode } from 'react';

interface QuickActionCardProps {
    href: string;
    icon: ReactNode;
    label: string;
    compact?: boolean;
    className?: string;
}

export default function QuickActionCard({
    href,
    icon,
    label,
    compact = false,
    className = '',
}: QuickActionCardProps) {
    return (
        <Link
            href={href}
            title={compact ? label : undefined}
            className={clsx(
                'flex flex-col items-center rounded-xl p-3 sm:p-4',
                'bg-emerald-50 text-emerald-600',
                'hover:bg-emerald-100',
                'dark:bg-emerald-900/20 dark:text-emerald-400',
                'dark:hover:bg-emerald-900/30',
                'transition-colors duration-200',
                className
            )}
        >
            <span className={clsx('text-2xl', !compact && 'mb-2')}>{icon}</span>
            {!compact && <span className="mt-1.5 text-center text-xs font-medium leading-tight sm:text-sm">{label}</span>}
        </Link>
    );
}
