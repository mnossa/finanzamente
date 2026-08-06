import { mobileKpiGridGapClass } from '@/Components/IndexPageListToolbars';
import { moneyKpiGrid2, moneyKpiGrid3, moneyKpiGrid4 } from '@/utils/moneyGridClasses';
import clsx from 'clsx';
import { ReactNode } from 'react';

type IndexKpiStripColumns = 2 | 3 | 4;

const gridByColumns: Record<IndexKpiStripColumns, string> = {
    2: moneyKpiGrid2,
    3: moneyKpiGrid3,
    4: moneyKpiGrid4,
};

interface IndexKpiStripProps {
    columns?: IndexKpiStripColumns;
    children: ReactNode;
    className?: string;
}

/**
 * Griglia KPI esterna (CardBox separati) con gap condiviso.
 */
export default function IndexKpiStrip({
    columns = 4,
    children,
    className,
}: IndexKpiStripProps): ReactNode {
    return (
        <div className={clsx(gridByColumns[columns], mobileKpiGridGapClass, className)}>
            {children}
        </div>
    );
}
