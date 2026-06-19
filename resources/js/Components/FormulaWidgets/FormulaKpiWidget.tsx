import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import { ProgressBar } from '@/Components/ProgressBar';
import { formatCurrency } from '@/utils/format';
import { moneyTabular } from '@/utils/moneyGridClasses';
import type { FormulaDeltaPolarity, FormulaWidgetPayload } from '@/types/formulaWidget';

interface FormulaKpiWidgetProps {
    payload: Extract<FormulaWidgetPayload, { type: 'kpi' }> | Extract<FormulaWidgetPayload, { type: 'progress' }>;
    embedded?: boolean;
    className?: string;
}

function formatValue(value: number, format: 'currency' | 'percent'): string {
    if (format === 'percent') {
        return `${value.toLocaleString('it-IT', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;
    }

    return formatCurrency(value);
}

function BalanceSummaryView({
    payload,
}: {
    payload: Extract<FormulaWidgetPayload, { type: 'kpi' }> & { variant: 'balance_summary' };
}) {
    const patrimonio =
        payload.patrimonioTotal
        ?? payload.value + (payload.investedLinked ?? 0);

    return (
        <Link href={route('patrimonio.index')} className="block h-full">
            <div className="overflow-hidden rounded-2xl bg-linear-to-br from-slate-800 to-slate-900 p-4 text-white shadow-lg transition-shadow hover:shadow-xl sm:p-5">
                <h3 className="text-sm font-medium text-slate-300">Saldo conti</h3>
                <p className={clsx('mt-1.5 text-3xl font-bold sm:mt-2 sm:text-4xl', moneyTabular)}>
                    {formatCurrency(payload.value)}
                </p>
                <p className="mt-1 text-xs text-slate-300">Somma saldi conti attivi (liquidità)</p>
                <p className="mt-2 text-sm text-slate-300">
                    Investimenti aperti{' '}
                    <span className={moneyTabular}>{formatCurrency(payload.invested ?? 0)}</span>
                </p>
                <p className="mt-0.5 text-xs text-slate-300">
                    Costo di carico · non incluso nel saldo conti
                </p>
                <p className="mt-2 border-t border-slate-700/60 pt-2 text-sm text-slate-300">
                    Patrimonio netto{' '}
                    <span className={moneyTabular}>{formatCurrency(patrimonio)}</span>
                </p>
                <p className="mt-0.5 text-xs text-slate-300">
                    Saldo conti + investimenti collegati al ledger (costo di carico)
                </p>
                <p className="mt-1 text-xs text-slate-300">
                    {payload.accountsCount ?? 0}{' '}
                    {(payload.accountsCount ?? 0) === 1 ? 'conto attivo' : 'conti attivi'} · Dettaglio patrimonio
                </p>
            </div>
        </Link>
    );
}

function resolveKpiDeltaDisplay(
    delta: number,
    polarity: FormulaDeltaPolarity,
): { colorClass: string; arrow: string } {
    const isPositiveOutcome = polarity === 'lower_is_better' ? delta <= 0 : delta >= 0;

    return {
        colorClass: isPositiveOutcome ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400',
        arrow: isPositiveOutcome ? '↑' : '↓',
    };
}

function KpiDeltaLine({
    delta,
    polarity = 'higher_is_better',
    comparisonLabel = 'periodo precedente',
}: {
    delta: number;
    polarity?: FormulaDeltaPolarity;
    comparisonLabel?: string;
}) {
    const { colorClass, arrow } = resolveKpiDeltaDisplay(delta, polarity);
    const percentLabel = `${delta >= 0 ? '+' : ''}${delta.toFixed(1)}% vs ${comparisonLabel}`;

    return (
        <p className={clsx('mt-1 flex items-center text-sm font-medium', colorClass)}>
            <span className="mr-1" aria-hidden="true">
                {arrow}
            </span>
            <span>{percentLabel}</span>
        </p>
    );
}

function KpiView({
    payload,
    embedded,
}: {
    payload: Extract<FormulaWidgetPayload, { type: 'kpi' }>;
    embedded: boolean;
}) {
    if (payload.variant === 'balance_summary') {
        return <BalanceSummaryView payload={{ ...payload, variant: 'balance_summary' }} />;
    }

    if (embedded) {
        return (
            <div className="flex h-full min-h-[6.5rem] flex-col justify-center">
                <p className={clsx('text-3xl font-bold text-gray-900 dark:text-white sm:text-4xl', moneyTabular)}>
                    {formatValue(payload.value, payload.format)}
                </p>
                {payload.delta !== null && (
                    <KpiDeltaLine
                        delta={payload.delta}
                        polarity={payload.deltaPolarity}
                        comparisonLabel={payload.deltaComparisonLabel ?? 'periodo precedente'}
                    />
                )}
            </div>
        );
    }

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800">
            <h3 className="font-semibold text-gray-900 dark:text-white">{payload.name}</h3>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{payload.periodLabel}</p>
            <p className={clsx('mt-2 text-3xl font-bold text-gray-900 dark:text-white sm:text-4xl', moneyTabular)}>
                {formatValue(payload.value, payload.format)}
            </p>
            {payload.delta !== null && (
                <KpiDeltaLine
                    delta={payload.delta}
                    polarity={payload.deltaPolarity}
                    comparisonLabel={payload.deltaComparisonLabel ?? 'periodo precedente'}
                />
            )}
        </div>
    );
}

function ProgressView({
    payload,
    embedded,
}: {
    payload: Extract<FormulaWidgetPayload, { type: 'progress' }>;
    embedded: boolean;
}) {
    if (embedded) {
        return (
            <div className="flex h-full min-h-[6.5rem] flex-col justify-center">
                <p className={clsx('text-2xl font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                    {formatCurrency(payload.value)}
                    <span className="ml-2 text-base font-normal text-gray-500 dark:text-gray-400">
                        / {formatCurrency(payload.threshold)}
                    </span>
                </p>
                <div className="mt-4">
                    <ProgressBar percentage={Math.min(100, payload.percentage)} />
                </div>
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {payload.percentage.toLocaleString('it-IT', { maximumFractionDigits: 1 })}% della soglia
                </p>
            </div>
        );
    }

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800">
            <h3 className="font-semibold text-gray-900 dark:text-white">{payload.name}</h3>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{payload.periodLabel}</p>
            <p className={clsx('mt-2 text-2xl font-semibold text-gray-900 dark:text-white', moneyTabular)}>
                {formatCurrency(payload.value)}
                <span className="ml-2 text-base font-normal text-gray-500 dark:text-gray-400">
                    / {formatCurrency(payload.threshold)}
                </span>
            </p>
            <div className="mt-4">
                <ProgressBar percentage={Math.min(100, payload.percentage)} />
            </div>
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {payload.percentage.toLocaleString('it-IT', { maximumFractionDigits: 1 })}% della soglia
            </p>
        </div>
    );
}

export default function FormulaKpiWidget({ payload, embedded = true, className }: FormulaKpiWidgetProps) {
    return (
        <div className={clsx('h-full w-full', className)}>
            {payload.type === 'kpi' && <KpiView payload={payload} embedded={embedded} />}
            {payload.type === 'progress' && <ProgressView payload={payload} embedded={embedded} />}
        </div>
    );
}
