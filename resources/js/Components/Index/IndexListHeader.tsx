import { mobileListHeaderClass } from '@/Components/IndexPageListToolbars';
import clsx from 'clsx';
import { ReactNode } from 'react';

interface IndexListHeaderProps {
    title: ReactNode;
    className?: string;
    titleClassName?: string;
}

export default function IndexListHeader({
    title,
    className,
    titleClassName,
}: IndexListHeaderProps): ReactNode {
    return (
        <div className={clsx(mobileListHeaderClass, className)}>
            <h3 className={clsx('font-medium text-gray-900 dark:text-white', titleClassName)}>
                {title}
            </h3>
        </div>
    );
}
