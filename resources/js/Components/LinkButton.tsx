import clsx from 'clsx';
import { InertiaLinkProps, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

type LinkButtonVariant = 'primary' | 'secondary' | 'danger';
type LinkButtonSize = 'sm' | 'md' | 'lg';

interface LinkButtonProps extends Omit<InertiaLinkProps, 'size'> {
    variant?: LinkButtonVariant;
    size?: LinkButtonSize;
    icon?: ReactNode;
    children: ReactNode;
}

const variantClasses: Record<LinkButtonVariant, string> = {
    primary: clsx(
        'bg-emerald-500 hover:bg-emerald-600 text-white',
        'shadow-[0_4px_14px_-3px_rgba(16,185,129,0.35)]',
        'hover:shadow-[0_8px_20px_-4px_rgba(16,185,129,0.4)]'
    ),
    secondary: clsx(
        'bg-slate-50 hover:bg-white text-slate-600',
        'border border-slate-200 hover:border-slate-300',
        'shadow-sm'
    ),
    danger: clsx(
        'bg-rose-500 hover:bg-rose-600 text-white',
        'shadow-sm hover:shadow-md'
    ),
};

const sizeClasses: Record<LinkButtonSize, string> = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2.5 text-sm',
    lg: 'px-6 py-3 text-sm',
};

export default function LinkButton({
    variant = 'primary',
    size = 'md',
    icon,
    className = '',
    children,
    ...props
}: LinkButtonProps) {
    return (
        <Link
            {...props}
            className={clsx(
                'inline-flex items-center justify-center gap-2',
                'rounded-xl font-semibold',
                'transition-all duration-200 active:scale-95',
                'focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2',
                variantClasses[variant],
                sizeClasses[size],
                className
            )}
        >
            {icon && <span className="flex-shrink-0">{icon}</span>}
            {children}
        </Link>
    );
}
