import React from 'react';
import clsx from 'clsx';

type MaxWidth = 'xl' | '2xl' | '3xl' | '4xl' | '5xl' | '7xl';

interface PageContentProps {
    children: React.ReactNode;
    maxWidth?: MaxWidth;
    className?: string;
}

const maxWidthClasses: Record<MaxWidth, string> = {
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
    '4xl': 'max-w-4xl',
    '5xl': 'max-w-5xl',
    '7xl': 'max-w-7xl',
};

export default function PageContent({ children, maxWidth, className }: PageContentProps) {
    return (
        <div className="py-1 sm:py-6">
            <div className={clsx('mx-auto space-y-2 sm:space-y-6 px-0 sm:px-4 lg:px-8', maxWidth && maxWidthClasses[maxWidth], className)}>
                {children}
            </div>
        </div>
    );
}
