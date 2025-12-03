import clsx from 'clsx';
import { ButtonHTMLAttributes } from 'react';

export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={clsx(
                'inline-flex items-center justify-center gap-2',
                'bg-emerald-500 hover:bg-emerald-600 text-white',
                'px-4 py-2.5 rounded-xl text-sm font-semibold',
                'shadow-[0_4px_14px_-3px_rgba(16,185,129,0.35)]',
                'hover:shadow-[0_8px_20px_-4px_rgba(16,185,129,0.4)]',
                'transition-all duration-200 active:scale-95',
                'focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2',
                disabled && 'opacity-50 cursor-not-allowed active:scale-100',
                className
            )}
            disabled={disabled}
        >
            {children}
        </button>
    );
}
