import clsx from 'clsx';
import { ReactNode } from 'react';

interface FormActionsBarProps {
    children: ReactNode;
    className?: string;
}

export default function FormActionsBar({ children, className = '' }: FormActionsBarProps) {
    return (
        <div className={clsx(
            'flex flex-wrap items-center gap-3 border-t border-gray-200/80 dark:border-gray-700/80',
            'sticky bottom-0 z-10 bg-white/95 dark:bg-slate-900/95 backdrop-blur-sm',
            '-mx-5 sm:mx-0 px-5 sm:px-0 pt-3 pb-3 sm:pb-0 sm:pt-4',
            className
        )}>
            {children}
        </div>
    );
}
