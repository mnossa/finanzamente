import { mobileListRowInsetClass } from '@/Components/IndexPageListToolbars';
import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import { CSSProperties, ReactNode } from 'react';

export function IndexListRowChevron(): ReactNode {
    return (
        <span className="shrink-0 text-gray-300 dark:text-gray-600" aria-hidden>
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="m9 18 6-6-6-6" />
            </svg>
        </span>
    );
}

interface IndexListRowProps {
    href: string;
    ariaLabel?: string;
    avatar: ReactNode;
    avatarClassName?: string;
    avatarStyle?: CSSProperties;
    title: ReactNode;
    subtitle?: ReactNode;
    amount: ReactNode;
    /** Righe aggiuntive sotto l’importo (desktop) */
    amountDetail?: ReactNode;
    /** Azioni desktop (icon buttons) */
    actions?: ReactNode;
    className?: string;
    borderClassName?: string;
}

const defaultAvatarWrapClass =
    'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-base sm:h-9 sm:w-9 sm:text-lg';

/**
 * Riga lista compatta: griglia mobile (titolo+importo / meta+chevron), flex desktop.
 */
export default function IndexListRow({
    href,
    ariaLabel,
    avatar,
    avatarClassName,
    avatarStyle,
    title,
    subtitle,
    amount,
    amountDetail,
    actions,
    className,
    borderClassName,
}: IndexListRowProps): ReactNode {
    const avatarWrapClass = clsx(defaultAvatarWrapClass, avatarClassName);

    return (
        <div
            className={clsx(
                'border-b border-gray-100 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50',
                mobileListRowInsetClass,
                borderClassName,
                className,
            )}
        >
            <div className="grid min-h-[3.25rem] grid-cols-[auto_minmax(0,1fr)] grid-rows-[auto_auto] gap-x-2 gap-y-1 py-2.5 sm:hidden">
                <div className="row-span-2 flex items-center self-center">
                    <div className={avatarWrapClass} style={avatarStyle}>{avatar}</div>
                </div>
                <Link href={href} className="contents active:opacity-80" aria-label={ariaLabel}>
                    <div className="col-start-2 flex min-h-4 min-w-0 items-center justify-between gap-2 overflow-hidden">
                        <div className="min-w-0 truncate text-xs font-medium leading-none text-gray-900 dark:text-white">
                            {title}
                        </div>
                        <div className="shrink-0 text-xs font-semibold tabular-nums text-gray-900 dark:text-white">
                            {amount}
                        </div>
                    </div>
                    <div className="col-start-2 flex min-h-4 min-w-0 items-center justify-between gap-2 overflow-hidden">
                        {subtitle ? (
                            <div className="min-w-0 truncate text-[11px] leading-none text-gray-500 dark:text-gray-400">
                                {subtitle}
                            </div>
                        ) : (
                            <span />
                        )}
                        <IndexListRowChevron />
                    </div>
                </Link>
            </div>

            <div className="hidden min-h-[3.5rem] items-center gap-3 sm:flex">
                <div className={avatarWrapClass} style={avatarStyle}>{avatar}</div>
                <Link href={href} className="min-w-0 flex-1" aria-label={ariaLabel}>
                    <div className="truncate text-sm font-medium text-gray-900 dark:text-white">{title}</div>
                    {subtitle ? (
                        <div className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{subtitle}</div>
                    ) : null}
                </Link>
                <div className="ml-2 flex shrink-0 items-center gap-1 sm:gap-2">
                    <div className="text-right">
                        <div className="text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{amount}</div>
                        {amountDetail}
                    </div>
                    {actions}
                </div>
            </div>
        </div>
    );
}
