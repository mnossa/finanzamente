import clsx from 'clsx';
import { ReactNode } from 'react';

interface IndexInfoBannerProps {
    icon?: ReactNode;
    title: string;
    description: string;
    /** Azioni opzionali (es. bulk confirm/reject in Inbox) */
    actions?: ReactNode;
    /** Nascondi su mobile (default: true) */
    hideOnMobile?: boolean;
    variant?: 'info' | 'warning';
    className?: string;
}

const variantClass = {
    info: 'bg-blue-50 dark:bg-blue-900/20',
    warning: 'border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20',
};

const titleClass = {
    info: 'text-blue-800 dark:text-blue-200',
    warning: 'text-amber-800 dark:text-amber-200',
};

const bodyClass = {
    info: 'text-blue-700 dark:text-blue-300',
    warning: 'text-amber-800 dark:text-amber-200',
};

/**
 * Banner informativo sotto toolbar / intro (tipicamente desktop-only).
 */
export default function IndexInfoBanner({
    icon = '💡',
    title,
    description,
    actions,
    hideOnMobile = true,
    variant = 'info',
    className,
}: IndexInfoBannerProps): ReactNode {
    return (
        <div
            className={clsx(
                'rounded-xl p-3 sm:p-4',
                variantClass[variant],
                hideOnMobile && 'hidden sm:block',
                className,
            )}
        >
            <div className="flex gap-2 sm:gap-3">
                <div className="shrink-0 text-lg sm:text-2xl">{icon}</div>
                <div className="min-w-0 flex-1">
                    <h3 className={clsx('text-sm font-medium', titleClass[variant])}>{title}</h3>
                    <p className={clsx('mt-1 text-sm', bodyClass[variant])}>{description}</p>
                    {actions ? <div className="mt-2 flex gap-2 sm:mt-3">{actions}</div> : null}
                </div>
            </div>
        </div>
    );
}
