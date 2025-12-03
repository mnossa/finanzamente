import clsx from 'clsx';
import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active?: boolean }) {
    return (
        <Link
            {...props}
            className={clsx(
                'flex w-full items-start border-l-4 py-3 pe-4 ps-4',
                'text-base font-medium transition-all duration-200',
                'focus:outline-none',
                active
                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                    : 'border-transparent text-slate-600 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800',
                className
            )}
        >
            {children}
        </Link>
    );
}
