import SystemVariableReferenceList from '@/Components/FormulaWidgets/SystemVariableReferenceList';
import clsx from 'clsx';
import type { SystemVariableMeta } from '@/types/formulaWidget';
import { formulaWidgetUsesLinkedVariableSeries, formulaWidgetUsesSeries } from '@/utils/formulaWidgetForm';

interface ChartTypeMeta {
    label: string;
    description: string;
    guide?: string;
}

interface FormulaWidgetCreateGuideProps {
    displayType: string;
    chartTypes: Record<string, ChartTypeMeta>;
    systemVariables: SystemVariableMeta[];
    className?: string;
}

export default function FormulaWidgetCreateGuide({
    displayType,
    chartTypes,
    systemVariables,
    className,
}: FormulaWidgetCreateGuideProps) {
    const chartGuide = chartTypes[displayType]?.guide;

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
                    Collega una <strong>variabile personalizzata</strong> (formula o valore fisso) oppure creane una al volo.
                </li>
                <li>
                    Scegli il <strong>tipo di grafico</strong>: KPI, linea, barre, torta, treemap, avanzamento e altri formati Recharts.
                </li>
                {formulaWidgetUsesLinkedVariableSeries(displayType) && (
                    <li>
                        Per linea e area il grafico usa la <strong>variabile collegata</strong> aggregata per mese nel periodo scelto.
                    </li>
                )}
                {formulaWidgetUsesSeries(displayType) && (
                    <li>
                        Per questo grafico configura <strong>almeno due serie</strong> con variabili di sistema (liquidità, entrate, uscite…).
                    </li>
                )}
                {chartGuide && <li>{chartGuide}</li>}
                <li>
                    Controlla l&apos;<strong>anteprima</strong> a destra: se qualcosa non va, vedrai un messaggio di errore prima del salvataggio.
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
        </div>
    );
}
