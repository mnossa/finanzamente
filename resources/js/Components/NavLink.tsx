import clsx from 'clsx';
import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active: boolean }) {
    return (
        <Link
            {...props}
            className={clsx(
                'inline-flex items-center px-1 pt-1 text-sm font-medium',
                'border-b-2 leading-5 transition-all duration-200',
                'focus:outline-none',
                active
                    ? 'border-emerald-500 text-slate-900'
                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700',
                className
            )}
        >
            {children}
        </Link>
    );
}
