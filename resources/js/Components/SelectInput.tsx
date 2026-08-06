import clsx from 'clsx';
import { SelectHTMLAttributes, forwardRef } from 'react';

type SelectInputProps = SelectHTMLAttributes<HTMLSelectElement> & {
    className?: string;
};

const SelectInput = forwardRef<HTMLSelectElement, SelectInputProps>(
    ({ className, children, ...props }, ref) => {
        return (
            <select
                ref={ref}
                {...props}
                className={clsx(
                    'w-full rounded-xl border border-slate-200 bg-slate-50',
                    'px-4 py-2.5 text-slate-800',
                    'shadow-sm',
                    'focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200',
                    'transition-all duration-200',
                    'dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                    'disabled:cursor-not-allowed disabled:opacity-60',
                    className
                )}
            >
                {children}
            </select>
        );
    }
);

SelectInput.displayName = 'SelectInput';

export default SelectInput;
