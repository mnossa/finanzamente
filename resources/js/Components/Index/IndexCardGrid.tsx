import { mobileKpiGridGapClass } from '@/Components/IndexPageListToolbars';
import { moneyCardGrid3 } from '@/utils/moneyGridClasses';
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
        <div className={clsx(moneyCardGrid3, mobileKpiGridGapClass, className)}>
            {children}
        </div>
    );
}
