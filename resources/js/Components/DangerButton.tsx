import clsx from 'clsx';
import { ButtonHTMLAttributes } from 'react';

export default function DangerButton({
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
                'bg-rose-500 hover:bg-rose-600 text-white',
                'px-4 py-2.5 rounded-xl text-sm font-semibold',
                'shadow-sm hover:shadow-md',
                'transition-all duration-200 active:scale-95',
                'focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2',
                disabled && 'opacity-50 cursor-not-allowed active:scale-100',
                className
            )}
            disabled={disabled}
        >
            {children}
        </button>
    );
}
