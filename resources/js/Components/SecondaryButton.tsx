import clsx from 'clsx';
import { ButtonHTMLAttributes } from 'react';

export default function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            type={type}
            className={clsx(
                'inline-flex items-center justify-center gap-2',
                'bg-slate-50 hover:bg-white text-slate-600',
                'px-4 py-2.5 rounded-xl text-sm font-medium',
                'border border-slate-200 hover:border-slate-300',
                'shadow-sm transition-all duration-200',
                'focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2',
                disabled && 'opacity-50 cursor-not-allowed',
                className
            )}
            disabled={disabled}
        >
            {children}
        </button>
    );
}
