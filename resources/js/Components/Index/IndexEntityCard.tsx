import CardBox from '@/Components/CardBox';
import { moneyTabular } from '@/utils/moneyGridClasses';
import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import { CSSProperties, ReactNode } from 'react';

export const indexEntityCardFooterActionClass =
    'inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 sm:min-h-10 sm:min-w-10 dark:hover:bg-gray-700 dark:hover:text-blue-400';

interface IndexEntityCardProps {
    href?: string;
    ariaLabel?: string;
    icon?: ReactNode;
    iconClassName?: string;
    iconStyle?: CSSProperties;
    title: ReactNode;
    subtitle?: ReactNode;
    amount?: ReactNode;
    amountClassName?: string;
    amountDetail?: ReactNode;
    /** Colonna destra in testata (badge, importo secondario…) */
    aside?: ReactNode;
    /** Contenuto ricco sotto testata (progress bar, griglia metriche…) */
    extra?: ReactNode;
    footer?: ReactNode;
    footerClassName?: string;
    dimmed?: boolean;
    className?: string;
}

/**
 * Card entità condivisa per indici (conti, budget, obiettivi, tag…).
 * Layout compatto: icona + titolo/meta/importo; varianti via aside/extra/footer.
 */
export default function IndexEntityCard({
    href,
    ariaLabel,
    icon,
    iconClassName = 'text-xl sm:text-2xl',
    iconStyle,
    title,
    subtitle,
    amount,
    amountClassName,
    amountDetail,
    aside,
    extra,
    footer,
    footerClassName,
    dimmed = false,
    className,
}: IndexEntityCardProps): ReactNode {
    const body = (
        <>
            <div className="flex items-start justify-between gap-2">
                <div className="flex min-w-0 flex-1 items-start gap-2.5">
                    {icon != null ? (
                        <div className={clsx('shrink-0', iconClassName)} style={iconStyle}>
                            {icon}
                        </div>
                    ) : null}
                    <div className="min-w-0 flex-1">
                        <h3 className="break-words text-sm font-semibold leading-snug text-gray-900 dark:text-white sm:text-base">
                            {title}
                        </h3>
                        {subtitle ? (
                            <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{subtitle}</p>
                        ) : null}
                        {amount != null ? (
                            <p
                                className={clsx(
                                    'mt-1.5 text-base font-bold sm:text-lg',
                                    moneyTabular,
                                    amountClassName ?? 'text-gray-900 dark:text-white',
                                )}
                            >
                                {amount}
                            </p>
                        ) : null}
                        {amountDetail ? (
                            <div className="mt-0.5 space-y-0.5">{amountDetail}</div>
                        ) : null}
                    </div>
                </div>
                {aside ? <div className="shrink-0">{aside}</div> : null}
            </div>
            {extra ? <div className="mt-3">{extra}</div> : null}
        </>
    );

    return (
        <CardBox
            className={clsx(
                'min-w-0 !p-0 shadow-sm transition-shadow hover:shadow-md',
                dimmed && 'opacity-60',
                className,
            )}
        >
            {href ? (
                <Link href={href} className="block min-w-0 p-2.5 sm:p-4" aria-label={ariaLabel}>
                    {body}
                </Link>
            ) : (
                <div className="p-2.5 sm:p-4">{body}</div>
            )}
            {footer ? (
                <div
                    className={clsx(
                        'border-t border-gray-100 px-2.5 py-1.5 dark:border-gray-700 sm:px-4 sm:py-2',
                        footerClassName ?? 'flex justify-end gap-0.5',
                    )}
                >
                    {footer}
                </div>
            ) : null}
        </CardBox>
    );
}

export function IndexEntityCardFooterLink({
    href,
    title,
    children,
    className,
}: {
    href: string;
    title: string;
    children: ReactNode;
    className?: string;
}): ReactNode {
    return (
        <Link
            href={href}
            className={clsx(indexEntityCardFooterActionClass, className)}
            title={title}
        >
            {children}
        </Link>
    );
}

export function IndexEntityCardFooterButton({
    onClick,
    title,
    children,
    className,
}: {
    onClick: () => void;
    title: string;
    children: ReactNode;
    className?: string;
}): ReactNode {
    return (
        <button
            type="button"
            onClick={onClick}
            className={clsx(indexEntityCardFooterActionClass, className)}
            title={title}
        >
            {children}
        </button>
    );
}
