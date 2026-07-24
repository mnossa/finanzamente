import DashboardWidgetShell, { dashboardWidgetListBodyClass } from '@/Components/Dashboard/DashboardWidgetShell';
import CustomFormulaWidget from '@/Components/FormulaWidgets/CustomFormulaWidget';
import clsx from 'clsx';
import { useState } from 'react';
import type { FormulaWidgetPreviewStatus } from '@/hooks/useFormulaWidgetPreview';
import type { FormulaWidgetPayload } from '@/types/formulaWidget';

interface FormulaWidgetPreviewPanelProps {
    status: FormulaWidgetPreviewStatus;
    payload: FormulaWidgetPayload | null;
    errors: string[];
    title?: string;
    className?: string;
    onParameterChange?: (key: string, value: string) => void;
    isRefreshing?: boolean;
    isFetching?: boolean;
    hasRuntimeParameters?: boolean;
    /** Formula della metrica selezionata (barra Excel). */
    formulaString?: string | null;
}

export default function FormulaWidgetPreviewPanel({
    status,
    payload,
    errors,
    title = 'Anteprima',
    className,
    onParameterChange,
    isRefreshing = false,
    isFetching = false,
    hasRuntimeParameters = false,
    formulaString = null,
}: FormulaWidgetPreviewPanelProps) {
    const [formulaOpen, setFormulaOpen] = useState(false);
    const subtitle =
        isRefreshing
            ? 'Aggiornamento in corso…'
            : status === 'success' && payload
              ? payload.periodLabel
              : undefined;
    const showFormula = Boolean(formulaString?.trim());

    return (
        <div
            className={clsx(
                'sticky top-4 rounded-xl border border-surface-200 bg-surface-50 p-4 dark:border-gray-700 dark:bg-gray-900/40',
                className,
            )}
        >
            <h2 className="mb-3 text-base font-semibold text-gray-900 dark:text-white">{title}</h2>

            {showFormula ? (
                <div className="mb-3">
                    <button
                        type="button"
                        className="flex w-full items-center justify-between gap-2 rounded-lg border border-surface-200 bg-white px-2.5 py-1.5 text-left text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700/60"
                        aria-expanded={formulaOpen}
                        onClick={() => setFormulaOpen((open) => !open)}
                    >
                        <span>Formula</span>
                        <span className="font-normal text-gray-400">{formulaOpen ? 'Nascondi' : 'Mostra'}</span>
                    </button>
                    {formulaOpen ? (
                        <pre className="mt-1.5 overflow-x-auto rounded-lg bg-slate-900 px-2.5 py-2 font-mono text-[11px] leading-relaxed text-emerald-300 dark:bg-black/50">
                            {formulaString}
                        </pre>
                    ) : null}
                </div>
            ) : null}

            {hasRuntimeParameters && status !== 'idle' && (
                <p className="mb-3 text-xs text-gray-500 dark:text-gray-400">
                    Usa i controlli sotto l&apos;anteprima per provare conto e periodo come in dashboard.
                </p>
            )}

            {status === 'idle' && (
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Scegli metrica e periodo: l&apos;anteprima si aggiorna in tempo reale con i tuoi dati.
                </p>
            )}

            {isFetching && !payload && (
                <div className="space-y-3" aria-live="polite" aria-busy="true">
                    <div className="h-8 w-2/3 animate-pulse rounded-lg bg-gray-200 dark:bg-gray-700" />
                    <div className="h-4 w-1/2 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />
                    <div className="h-32 animate-pulse rounded-xl bg-gray-200 dark:bg-gray-700" />
                </div>
            )}

            {status === 'error' && (
                <div
                    className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300"
                    role="alert"
                >
                    <p className="font-medium">Anteprima non disponibile</p>
                    <ul className="mt-1 list-inside list-disc space-y-0.5">
                        {errors.map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                </div>
            )}

            {payload && (status === 'success' || isRefreshing) && (
                <DashboardWidgetShell
                    title={payload.name}
                    subtitle={subtitle}
                    bodyClassName={clsx(dashboardWidgetListBodyClass, isRefreshing && 'opacity-60')}
                >
                    <CustomFormulaWidget
                        payload={payload}
                        embedded
                        onParameterChange={onParameterChange}
                        parameterControlsDisabled={isRefreshing}
                        refreshing={isRefreshing}
                    />
                </DashboardWidgetShell>
            )}
        </div>
    );
}
