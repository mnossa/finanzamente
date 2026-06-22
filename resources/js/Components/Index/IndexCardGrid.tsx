import { mobileKpiGridGapClass } from '@/Components/IndexPageListToolbars';
import { moneyListCardGrid } from '@/utils/moneyGridClasses';
import clsx from 'clsx';
import { ReactNode } from 'react';

interface IndexCardGridProps {
    children: ReactNode;
    className?: string;
}

/**
 * Griglia card per indici (conti, obiettivi, debiti/crediti…).
 */
export default function IndexCardGrid({ children, className }: IndexCardGridProps): ReactNode {
    return (
        <div className={clsx(moneyListCardGrid, mobileKpiGridGapClass, className)}>
            {children}
        </div>
    );
}
