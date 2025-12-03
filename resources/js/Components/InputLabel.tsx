import clsx from 'clsx';
import { LabelHTMLAttributes } from 'react';

export default function InputLabel({
    value,
    className = '',
    children,
    ...props
}: LabelHTMLAttributes<HTMLLabelElement> & { value?: string }) {
    return (
        <label
            {...props}
            className={clsx(
                'block text-sm font-medium text-slate-700 mb-1.5',
                className
            )}
        >
            {value ? value : children}
        </label>
    );
}
