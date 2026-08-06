import EmptyState from '@/Components/EmptyState';
import clsx from 'clsx';
import { ComponentProps, ReactNode } from 'react';

type IndexEmptyListProps = ComponentProps<typeof EmptyState>;

/**
 * Empty state compatto per l’interno di IndexListCard (meno padding verticale del default).
 */
export default function IndexEmptyList({ className, ...props }: IndexEmptyListProps): ReactNode {
    return (
        <EmptyState
            {...props}
            className={clsx('py-8 sm:py-12', className)}
        />
    );
}
