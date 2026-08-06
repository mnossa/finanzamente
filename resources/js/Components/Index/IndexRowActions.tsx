import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import { ReactNode } from 'react';

export function IndexRowActions({ children }: { children: ReactNode }): ReactNode {
    return <div className="hidden items-center gap-1 sm:flex">{children}</div>;
}

const actionClass =
    'rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 dark:hover:bg-gray-700';

export function IndexRowActionLink({
    href,
    title,
    children,
    hoverClassName = 'hover:text-emerald-600 dark:hover:text-emerald-400',
}: {
    href: string;
    title: string;
    children: ReactNode;
    hoverClassName?: string;
}): ReactNode {
    return (
        <Link href={href} className={clsx(actionClass, hoverClassName)} title={title}>
            {children}
        </Link>
    );
}

export function IndexRowActionButton({
    onClick,
    title,
    children,
    hoverClassName = 'hover:text-red-600 dark:hover:text-red-400',
}: {
    onClick: () => void;
    title: string;
    children: ReactNode;
    hoverClassName?: string;
}): ReactNode {
    return (
        <button type="button" onClick={onClick} className={clsx(actionClass, hoverClassName)} title={title}>
            {children}
        </button>
    );
}
