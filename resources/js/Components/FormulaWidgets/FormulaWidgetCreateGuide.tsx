import SystemVariableReferenceList from '@/Components/FormulaWidgets/SystemVariableReferenceList';
import clsx from 'clsx';
import type { SystemVariableMeta } from '@/types/formulaWidget';
import { formulaWidgetUsesLinkedVariableSeries, formulaWidgetUsesSeries } from '@/utils/formulaWidgetForm';
import type { WidgetRecipeId } from '@/utils/formulaWidgetRuntimeControls';
import type { MetricQueryConfig } from '@/utils/metricQueryForm';

interface ChartTypeMeta {
    label: string;
    description: string;
    guide?: string;
}

interface FormulaWidgetCreateGuideProps {
    displayType: string;
    chartTypes: Record<string, ChartTypeMeta>;
    systemVariables: SystemVariableMeta[];
    metricQueryConfig?: MetricQueryConfig;
    hasMetricQuery?: boolean;
    recipeId?: WidgetRecipeId;
    className?: string;
}

export default function FormulaWidgetCreateGuide({
    displayType,
    chartTypes,
    systemVariables,
    metricQueryConfig,
    hasMetricQuery = false,
    recipeId = 'single_value',
    className,
}: FormulaWidgetCreateGuideProps) {
    const chartGuide = chartTypes[displayType]?.guide;
    const isTable = displayType === 'table' || recipeId === 'tabular';
    const isComparison = recipeId === 'comparison' || formulaWidgetUsesSeries(displayType);

    return (
        <div
            className={clsx(
                'rounded-xl border border-primary-200 bg-primary-50/60 p-4 text-sm dark:border-primary-900/40 dark:bg-primary-950/30',
                className,
            )}
        >
            <h3 className="font-semibold text-primary-900 dark:text-primary-100">Guida rapida</h3>
            <ol className="mt-2 list-decimal space-y-2 pl-4 text-gray-700 dark:text-gray-300">
                <li>
                    Scegli un <strong>obiettivo</strong>, poi una <strong>metrica</strong> compatibile (solo quelle usabili per quel widget).
                </li>
                {hasMetricQuery || isTable ? (
                    <li>
                        Per le tabelle regola <strong>filtri e sorgente dati</strong>: puoi cambiare i filtri anche in dashboard.
                    </li>
                ) : null}
                {isTable ? (
                    <li>
                        La visualizzazione è una <strong>tabella / lista</strong>: non serve scegliere grafici a barre o linee.
                    </li>
                ) : (
                    <li>
                        In aspetto vedi solo le <strong>viste compatibili</strong> con l’obiettivo (KPI, linea, barre, avanzamento…).
                    </li>
                )}
                {formulaWidgetUsesLinkedVariableSeries(displayType) && (
                    <li>
                        Linea e area usano la <strong>metrica collegata</strong> (o la query dinamica aggregata per mese).
                    </li>
                )}
                {isComparison && !isTable && (
                    <li>
                        Le barre confrontano le <strong>serie di periodo</strong> (incassato, speso, risparmiato), allineate alla metrica scelta.
                    </li>
                )}
                {chartGuide && <li>{chartGuide}</li>}
                <li>
                    Controlla l&apos;<strong>anteprima</strong> a destra: si aggiorna mentre cambi metrica, periodo e vista.
                </li>
            </ol>

            <details className="mt-3">
                <summary className="cursor-pointer font-medium text-primary-800 dark:text-primary-200">
                    Variabili disponibili nelle formule
                </summary>
                <div className="mt-2 max-h-64 space-y-3 overflow-y-auto">
                    <div>
                        <p className="text-xs font-medium text-gray-700 dark:text-gray-300">Finanziarie</p>
                        <SystemVariableReferenceList
                            variables={systemVariables}
                            category="financial"
                            className="mt-1"
                        />
                    </div>
                    <div>
                        <p className="text-xs font-medium text-gray-700 dark:text-gray-300">Contesto (data e calendario)</p>
                        <SystemVariableReferenceList
                            variables={systemVariables}
                            category="context"
                            className="mt-1"
                        />
                    </div>
                </div>
            </details>

            {metricQueryConfig ? (
                <details className="mt-3">
                    <summary className="cursor-pointer font-medium text-primary-800 dark:text-primary-200">
                        Query dinamica (avanzate)
                    </summary>
                    <p className="mt-2 text-xs text-gray-600 dark:text-gray-400">
                        Per importi cross-conto usa EUR normalizzato (<code className="font-mono">amount_base</code>).
                        Formule condizionali: <code className="font-mono">IF([period_expenses] &gt; 1000, 1, 0)</code>.
                    </p>
                </details>
            ) : null}
        </div>
    );
}
