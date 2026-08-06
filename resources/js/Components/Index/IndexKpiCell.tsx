import CardBox from '@/Components/CardBox';
import { moneyTabular } from '@/utils/moneyGridClasses';
import clsx from 'clsx';
import { ReactNode } from 'react';

interface IndexKpiCellProps {
    label: ReactNode;
    value: ReactNode;
    detail?: ReactNode;
    valueClassName?: string;
    className?: string;
}

/**
 * Cella KPI standard in CardBox (label + valore + dettaglio opzionale).
 */
export default function IndexKpiCell({
    label,
    value,
    detail,
    valueClassName,
    className,
}: IndexKpiCellProps): ReactNode {
    return (
        <CardBox className={clsx('p-3 shadow-sm sm:p-4', className)}>
            <p className="text-sm text-gray-500 dark:text-gray-400">{label}</p>
            <div
                className={clsx(
                    'mt-1 text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl',
                    moneyTabular,
                    valueClassName,
                )}
            >
                {value}
            </div>
            {detail ? (
                <div className="mt-1 text-xs text-gray-400 dark:text-gray-500">{detail}</div>
            ) : null}
        </CardBox>
    );
}
