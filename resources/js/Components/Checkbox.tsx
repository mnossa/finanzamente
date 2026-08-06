import clsx from 'clsx';
import { InputHTMLAttributes } from 'react';

export default function Checkbox({
    className = '',
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            {...props}
            type="checkbox"
            className={clsx(
                'rounded border-slate-300 text-emerald-500',
                'shadow-sm focus:ring-emerald-500 focus:ring-offset-0',
                'transition-colors duration-200',
                className
            )}
        />
    );
}
