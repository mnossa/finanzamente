import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import SectionCard from '@/Components/SectionCard';
import TextInput from '@/Components/TextInput';
import CreateFinancialVariableModal from '@/Components/FormulaWidgets/CreateFinancialVariableModal';
import DuplicateFormulaWidgetNotice from '@/Components/FormulaWidgets/DuplicateFormulaWidgetNotice';
import FormulaWidgetCreateGuide from '@/Components/FormulaWidgets/FormulaWidgetCreateGuide';
import FormulaWidgetPreviewPanel from '@/Components/FormulaWidgets/FormulaWidgetPreviewPanel';
import { useFormulaWidgetPreview } from '@/hooks/useFormulaWidgetPreview';
import {
    formulaWidgetRequiresPeriod,
    formulaWidgetUsesSeries,
} from '@/utils/formulaWidgetForm';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import { FormEventHandler, useMemo, useState } from 'react';
import type { FinancialVariableSummary, FormulaWidgetSummary, SystemVariableMeta } from '@/types/formulaWidget';
import type { PageProps } from '@/types';

interface ChartTypeMeta {
    label: string;
    description: string;
    guide?: string;
}

interface CreateProps {
    variables: FinancialVariableSummary[];
    systemVariables: SystemVariableMeta[];
    chartTypes: Record<string, ChartTypeMeta>;
    periodPresets: Record<string, { label: string }>;
    editingWidget?: FormulaWidgetSummary | null;
}

interface CreateWidgetForm {
    name: string;
    financial_variable_id: number | '';
    display_type: string;
    period_preset: string;
    default_size: string;
    is_public: boolean;
    pin_to_dashboard: boolean;
    chart_config: {
        show_delta: boolean;
        format: string;
        value_code: string;
        threshold_code: string;
        series: Array<{ code: string; label?: string }>;
    };
}

const DEFAULT_CHART_CONFIG: CreateWidgetForm['chart_config'] = {
    show_delta: false,
    format: 'currency',
    value_code: 'annual_revenue',
    threshold_code: 'revenue_threshold',
    series: [
        { code: 'household_balance', label: 'Liquidità' },
        { code: 'total_investments', label: 'Investimenti' },
    ],
};

function buildInitialForm(
    variables: FinancialVariableSummary[],
    editingWidget?: FormulaWidgetSummary | null,
): CreateWidgetForm {
    const chartConfig = editingWidget?.chart_config ?? {};

    return {
        name: editingWidget?.name ?? '',
        financial_variable_id: editingWidget?.financial_variable?.id ?? variables[0]?.id ?? '',
        display_type: editingWidget?.display_type ?? 'kpi',
        period_preset: editingWidget?.period_preset ?? '',
        default_size: editingWidget?.default_size ?? 'md',
        is_public: editingWidget?.is_public ?? false,
        pin_to_dashboard: false,
        chart_config: {
            ...DEFAULT_CHART_CONFIG,
            show_delta: Boolean(chartConfig.show_delta),
            format: String(chartConfig.format ?? DEFAULT_CHART_CONFIG.format),
            value_code: String(chartConfig.value_code ?? DEFAULT_CHART_CONFIG.value_code),
            threshold_code: String(chartConfig.threshold_code ?? DEFAULT_CHART_CONFIG.threshold_code),
            series: Array.isArray(chartConfig.series) && chartConfig.series.length > 0
                ? chartConfig.series.map((entry) => ({
                    code: String((entry as { code?: string }).code ?? ''),
                    label: (entry as { label?: string }).label,
                }))
                : DEFAULT_CHART_CONFIG.series,
        },
    };
}

export default function Create({
    variables,
    systemVariables,
    chartTypes,
    periodPresets,
    editingWidget = null,
}: CreateProps) {
    const isEditing = editingWidget !== null;
    const { flash } = usePage<PageProps>().props;
    const [localVariables, setLocalVariables] = useState(variables);
    const [variableModalOpen, setVariableModalOpen] = useState(false);
    const [duplicateDismissed, setDuplicateDismissed] = useState(false);

    const duplicateWidget = flash?.duplicateWidget;
    const duplicateMarketplaceWidget = flash?.duplicateMarketplaceWidget;
    const showOwnDuplicateNotice = duplicateWidget !== undefined && !duplicateDismissed;
    const showMarketplaceDuplicateNotice = duplicateMarketplaceWidget !== undefined && !duplicateDismissed;

    const { data, setData, post, put, processing, errors } = useForm<CreateWidgetForm>(
        () => buildInitialForm(variables, editingWidget),
    );

    const requiresPeriod = formulaWidgetRequiresPeriod(data.display_type, data.chart_config);
    const usesSeries = formulaWidgetUsesSeries(data.display_type);
    const selectedVariable = localVariables.find((variable) => variable.id === data.financial_variable_id);

    const previewInput = useMemo(
        () => ({
            name: data.name,
            financial_variable_id: data.financial_variable_id,
            display_type: data.display_type,
            period_preset: data.period_preset,
            chart_config: data.chart_config,
        }),
        [data.name, data.financial_variable_id, data.display_type, data.period_preset, data.chart_config],
    );

    const { status: previewStatus, payload: previewPayload, errors: previewErrors } =
        useFormulaWidgetPreview(previewInput);

    const handleVariableCreated = (variable: FinancialVariableSummary) => {
        setLocalVariables((current) => [...current, variable].sort((a, b) => a.name.localeCompare(b.name, 'it')));
        setData('financial_variable_id', variable.id);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (isEditing && editingWidget) {
            put(route('formula-widgets.update', editingWidget.id));

            return;
        }

        post(route('formula-widgets.store'));
    };

    const pageTitle = isEditing ? 'Modifica widget a formula' : 'Nuovo widget a formula';

    return (
        <AuthenticatedLayout
            header={
                <PageHeader title={pageTitle} backLink={route('formula-widgets.index')} />
            }
        >
            <Head title={pageTitle} />

            <PageContent maxWidth="7xl">
                {showOwnDuplicateNotice ? (
                    <div className="mb-6">
                        <DuplicateFormulaWidgetNotice
                            widget={duplicateWidget}
                            onDismiss={() => setDuplicateDismissed(true)}
                        />
                    </div>
                ) : null}
                {showMarketplaceDuplicateNotice ? (
                    <div className="mb-6">
                        <DuplicateFormulaWidgetNotice
                            widget={duplicateMarketplaceWidget}
                            variant="marketplace"
                            onDismiss={() => setDuplicateDismissed(true)}
                        />
                    </div>
                ) : null}

                <div className={clsx('grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,22rem)] xl:grid-cols-[minmax(0,1fr)_24rem]')}>
                    <form onSubmit={submit} className="space-y-6">
                        <FormulaWidgetCreateGuide
                            displayType={data.display_type}
                            chartTypes={chartTypes}
                            systemVariables={systemVariables}
                        />

                        <SectionCard>
                            <h2 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">Informazioni base</h2>
                            <div className="space-y-4">
                                <div>
                                    <InputLabel htmlFor="name" value="Nome widget" />
                                    <TextInput
                                        id="name"
                                        className="mt-1 block w-full"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.name} className="mt-1" />
                                </div>

                                <div>
                                    <div className="flex items-center justify-between gap-2">
                                        <InputLabel htmlFor="financial_variable_id" value="Variabile collegata" />
                                        <button
                                            type="button"
                                            onClick={() => setVariableModalOpen(true)}
                                            className="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        >
                                            + Crea variabile personalizzata
                                        </button>
                                    </div>
                                    {localVariables.length === 0 ? (
                                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            Nessuna variabile ancora.{' '}
                                            <button
                                                type="button"
                                                onClick={() => setVariableModalOpen(true)}
                                                className="font-medium text-primary-600 hover:underline"
                                            >
                                                Creane una ora
                                            </button>{' '}
                                            oppure installa un template dalla{' '}
                                            <Link href={route('formula-marketplace.index')} className="font-medium text-primary-600 hover:underline">
                                                galleria
                                            </Link>
                                            .
                                        </p>
                                    ) : (
                                        <select
                                            id="financial_variable_id"
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                            value={data.financial_variable_id}
                                            onChange={(e) => setData('financial_variable_id', Number(e.target.value))}
                                        >
                                            {localVariables.map((variable) => (
                                                <option key={variable.id} value={variable.id}>
                                                    {variable.name} ({variable.code})
                                                </option>
                                            ))}
                                        </select>
                                    )}
                                    {selectedVariable?.formula_string && (
                                        <p className="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">
                                            {selectedVariable.formula_string}
                                        </p>
                                    )}
                                    <InputError message={errors.financial_variable_id} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="display_type" value="Tipo visualizzazione" />
                                    <select
                                        id="display_type"
                                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                        value={data.display_type}
                                        onChange={(e) => setData('display_type', e.target.value)}
                                    >
                                        {Object.entries(chartTypes).map(([key, meta]) => (
                                            <option key={key} value={key}>
                                                {meta.label}
                                            </option>
                                        ))}
                                    </select>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {chartTypes[data.display_type]?.description}
                                    </p>
                                    <InputError message={errors.display_type} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="period_preset" value={requiresPeriod ? 'Periodo *' : 'Periodo (opzionale)'} />
                                    <select
                                        id="period_preset"
                                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                        value={data.period_preset}
                                        onChange={(e) => setData('period_preset', e.target.value)}
                                    >
                                        <option value="">Ad oggi / istantaneo</option>
                                        {Object.entries(periodPresets).map(([key, meta]) => (
                                            <option key={key} value={key}>
                                                {meta.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.period_preset} className="mt-1" />
                                </div>
                            </div>
                        </SectionCard>

                        {data.display_type === 'kpi' && (
                            <SectionCard>
                                <h2 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">Opzioni KPI</h2>
                                <div className="space-y-3">
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={Boolean(data.chart_config.show_delta)}
                                            onChange={(e) =>
                                                setData('chart_config', { ...data.chart_config, show_delta: e.target.checked })
                                            }
                                        />
                                        Mostra variazione vs periodo precedente
                                    </label>
                                    <div>
                                        <InputLabel value="Formato valore" />
                                        <select
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                                            value={String(data.chart_config.format ?? 'currency')}
                                            onChange={(e) =>
                                                setData('chart_config', { ...data.chart_config, format: e.target.value })
                                            }
                                        >
                                            <option value="currency">Valuta (€)</option>
                                            <option value="percent">Percentuale</option>
                                        </select>
                                    </div>
                                </div>
                            </SectionCard>
                        )}

                        {data.display_type === 'progress' && (
                            <SectionCard>
                                <h2 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">Soglia avanzamento</h2>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel value="Valore attuale" />
                                        <select
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                                            value={String(data.chart_config.value_code ?? '')}
                                            onChange={(e) =>
                                                setData('chart_config', { ...data.chart_config, value_code: e.target.value })
                                            }
                                        >
                                            {systemVariables.map((variable) => (
                                                <option key={variable.code} value={variable.code}>
                                                    {variable.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel value="Soglia" />
                                        <select
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                                            value={String(data.chart_config.threshold_code ?? '')}
                                            onChange={(e) =>
                                                setData('chart_config', { ...data.chart_config, threshold_code: e.target.value })
                                            }
                                        >
                                            {systemVariables.map((variable) => (
                                                <option key={variable.code} value={variable.code}>
                                                    {variable.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                </div>
                            </SectionCard>
                        )}

                        {usesSeries && (
                            <SectionCard>
                                <h2 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">Serie del grafico</h2>
                                <p className="mb-3 text-sm text-gray-600 dark:text-gray-400">
                                    Scegli almeno due variabili di sistema da confrontare nel periodo selezionato.
                                </p>
                                <div className="space-y-3">
                                    {[0, 1].map((index) => (
                                        <div key={index}>
                                            <InputLabel value={`Serie ${index + 1}`} />
                                            <select
                                                className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                                                value={String((data.chart_config.series as Array<{ code: string }>)?.[index]?.code ?? '')}
                                                onChange={(e) => {
                                                    const series = [...((data.chart_config.series as Array<{ code: string; label?: string }>) ?? [])];
                                                    const selected = systemVariables.find((v) => v.code === e.target.value);
                                                    series[index] = {
                                                        code: e.target.value,
                                                        label: selected?.label ?? e.target.value,
                                                    };
                                                    setData('chart_config', { ...data.chart_config, series });
                                                }}
                                            >
                                                {systemVariables.map((variable) => (
                                                    <option key={variable.code} value={variable.code}>
                                                        {variable.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    ))}
                                </div>
                                <InputError message={errors.chart_config} className="mt-2" />
                            </SectionCard>
                        )}

                        <SectionCard>
                            <h2 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">Pubblicazione</h2>
                            <div className="space-y-3">
                                {!isEditing ? (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={data.pin_to_dashboard}
                                            onChange={(e) => setData('pin_to_dashboard', e.target.checked)}
                                        />
                                        Aggiungi subito alla dashboard
                                    </label>
                                ) : null}
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.is_public}
                                        onChange={(e) => setData('is_public', e.target.checked)}
                                    />
                                    Rendi pubblico (link condivisibile)
                                </label>
                            </div>
                        </SectionCard>

                        <FormActionsBar>
                            <Link
                                href={route('formula-widgets.index')}
                                className="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400"
                            >
                                Annulla
                            </Link>
                            <InputError message={(errors as { widget?: string }).widget} className="sm:mr-auto" />
                            <PrimaryButton disabled={processing || localVariables.length === 0}>
                                {isEditing ? 'Salva modifiche' : 'Crea widget'}
                            </PrimaryButton>
                        </FormActionsBar>
                    </form>

                    <FormulaWidgetPreviewPanel
                        status={previewStatus}
                        payload={previewPayload}
                        errors={previewErrors}
                        className="lg:self-start"
                    />
                </div>
            </PageContent>

            <CreateFinancialVariableModal
                open={variableModalOpen}
                systemVariables={systemVariables}
                onClose={() => setVariableModalOpen(false)}
                onCreated={handleVariableCreated}
            />
        </AuthenticatedLayout>
    );
}
