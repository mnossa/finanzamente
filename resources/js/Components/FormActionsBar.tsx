import clsx from 'clsx';
import { ReactNode } from 'react';

interface FormActionsBarProps {
    children: ReactNode;
    className?: string;
}

export default function FormActionsBar({ children, className = '' }: FormActionsBarProps) {
    return (
        <div className={clsx('flex flex-wrap items-center gap-3 border-t border-gray-200/80 pt-4 dark:border-gray-700/80', className)}>
            {children}
        </div>
    );
}
