import clsx from 'clsx';
import { ReactNode } from 'react';

interface SectionCardProps {
    children: ReactNode;
    className?: string;
}

export default function SectionCard({ children, className = '' }: SectionCardProps) {
    return (
        <div
            className={clsx(
                'rounded-2xl border border-gray-200/80 bg-white/95 p-4 shadow-sm backdrop-blur-sm sm:p-5 dark:border-gray-700 dark:bg-gray-800/95',
                className
            )}
        >
            {children}
        </div>
    );
}
