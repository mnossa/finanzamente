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
import FormulaWidgetPreviewPanel from '@/Components/FormulaWidgets/FormulaWidgetPreviewPanel';
import FormulaWidgetCreateGuide from '@/Components/FormulaWidgets/FormulaWidgetCreateGuide';
import MetricQueryBuilder from '@/Components/FormulaWidgets/MetricQueryBuilder';
import RuntimeParameterPicker from '@/Components/FormulaWidgets/RuntimeParameterPicker';
import { useFormulaWidgetPreview } from '@/hooks/useFormulaWidgetPreview';
import {
    formulaWidgetRequiresPeriod,
    formulaWidgetUsesSeries,
} from '@/utils/formulaWidgetForm';
import type { AccountOption } from '@/utils/formulaWidgetPresets';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import { FormEventHandler, useMemo, useState } from 'react';
import type { FinancialVariableSummary, FormulaWidgetChartConfig, FormulaWidgetParameterType, FormulaWidgetSummary, MetricQueryDefinition, SystemVariableMeta } from '@/types/formulaWidget';
import type { MetricQueryConfig } from '@/utils/metricQueryForm';
import { METRIC_QUERY_PRESETS, syncRuntimeParametersFromMetricQuery } from '@/utils/metricQueryForm';
import type { PageProps } from '@/types';

interface ChartTypeMeta {
    label: string;
    description: string;
    guide?: string;
}

interface TagOption {
    id: number;
    name: string;
}

interface CategoryOption {
    id: number;
    name: string;
    type: string;
}

interface CreateProps {
    variables: FinancialVariableSummary[];
    systemVariables: SystemVariableMeta[];
    chartTypes: Record<string, ChartTypeMeta>;
    periodPresets: Record<string, { label: string }>;
    accounts?: AccountOption[];
    tags?: TagOption[];
    categories?: CategoryOption[];
    currencies?: Array<{ code: string; name: string; symbol: string }>;
    debtsCredits?: Array<{ id: number; counterparty: string; type: string }>;
    metricQueryConfig?: MetricQueryConfig;
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
    chart_config: FormulaWidgetChartConfig;
}

type RuntimeParameter = NonNullable<CreateWidgetForm['chart_config']['parameters']>[number];
type WidgetRecipeId = 'single_value' | 'trend' | 'comparison' | 'goal';

const DEFAULT_CHART_CONFIG: CreateWidgetForm['chart_config'] = {
    show_delta: false,
    format: 'currency',
    value_code: 'household_balance',
    threshold_code: 'total_investments',
    series: [
        { code: 'household_balance', label: 'Liquidità' },
        { code: 'total_investments', label: 'Investimenti' },
    ],
};

const ACCOUNT_PARAMETER: RuntimeParameter = {
    key: 'account_id',
    type: 'account',
    label: 'Conto',
    default: 'all',
};

const PERIOD_NAV_PARAMETER: RuntimeParameter = {
    key: 'period_offset',
    type: 'period_nav',
    label: 'Mese',
    default: '0',
};

const ACCOUNT_PERIOD_SERIES: CreateWidgetForm['chart_config']['series'] = [
    { code: 'period_income', label: 'Incassato', color: '#10b981' },
    { code: 'period_expenses', label: 'Speso', color: '#f97316' },
    { code: 'period_net', label: 'Risparmiato', color: '#3b82f6' },
];

const WIDGET_RECIPES: Array<{
    id: WidgetRecipeId;
    title: string;
    description: string;
    suggestedName: string;
    recommendedDisplayType: string;
}> = [
    {
        id: 'single_value',
        title: 'Valore chiave',
        description: 'Un numero importante: saldo, bilancio conto, risparmio, patrimonio.',
        suggestedName: 'Valore chiave',
        recommendedDisplayType: 'kpi',
    },
    {
        id: 'trend',
        title: 'Andamento nel tempo',
        description: 'Capire se una metrica cresce, cala o resta stabile mese per mese.',
        suggestedName: 'Andamento',
        recommendedDisplayType: 'area',
    },
    {
        id: 'comparison',
        title: 'Confronto metriche',
        description: 'Confrontare incassato, speso e risparmiato o altre serie affiancate.',
        suggestedName: 'Confronto metriche',
        recommendedDisplayType: 'bar',
    },
    {
        id: 'goal',
        title: 'Obiettivo / soglia',
        description: 'Monitorare quanto sei vicino a una soglia o a un target.',
        suggestedName: 'Avanzamento obiettivo',
        recommendedDisplayType: 'progress',
    },
];

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
                    color: (entry as { color?: string }).color,
                }))
                : DEFAULT_CHART_CONFIG.series,
            parameters: Array.isArray(chartConfig.parameters)
                ? chartConfig.parameters.map((entry) => ({
                    key: String((entry as { key?: string }).key ?? ''),
                    type: String((entry as { type?: string }).type ?? 'account') as FormulaWidgetParameterType,
                    label: String((entry as { label?: string }).label ?? 'Conto'),
                    default: (entry as { default?: string }).default,
                }))
                : undefined,
            metric_query: (chartConfig.metric_query as MetricQueryDefinition | undefined) ?? undefined,
        },
    };
}

function hasRuntimeParameter(parameters: CreateWidgetForm['chart_config']['parameters'], key: string): boolean {
    return (parameters ?? []).some((parameter) => parameter.key === key);
}

function upsertRuntimeParameter(
    parameters: CreateWidgetForm['chart_config']['parameters'],
    nextParameter: RuntimeParameter,
): RuntimeParameter[] {
    const current = parameters ?? [];
    const index = current.findIndex((parameter) => parameter.key === nextParameter.key);

    if (index === -1) {
        return [...current, nextParameter];
    }

    return current.map((parameter, parameterIndex) =>
        parameterIndex === index ? { ...parameter, ...nextParameter } : parameter,
    );
}

function removeRuntimeParameter(
    parameters: CreateWidgetForm['chart_config']['parameters'],
    key: string,
): RuntimeParameter[] | undefined {
    const filtered = (parameters ?? []).filter((parameter) => parameter.key !== key);

    return filtered.length > 0 ? filtered : undefined;
}

function defaultAccountFromParameters(parameters?: CreateWidgetForm['chart_config']['parameters']): string {
    const accountParameter = (parameters ?? []).find((parameter) => parameter.key === 'account_id');

    return String(accountParameter?.default ?? 'all');
}

export default function Create({
    variables,
    systemVariables,
    chartTypes,
    periodPresets,
    accounts = [],
    metricQueryConfig,
    editingWidget = null,
}: CreateProps) {
    const isEditing = editingWidget !== null;
    const { flash } = usePage<PageProps>().props;
    const [localVariables, setLocalVariables] = useState(variables);
    const [variableModalOpen, setVariableModalOpen] = useState(false);
    const [duplicateDismissed, setDuplicateDismissed] = useState(false);
    const [advancedOpen, setAdvancedOpen] = useState(isEditing);
    const [activeRecipe, setActiveRecipe] = useState<WidgetRecipeId>('single_value');
    const [defaultAccountId, setDefaultAccountId] = useState(() =>
        defaultAccountFromParameters(editingWidget?.chart_config?.parameters as CreateWidgetForm['chart_config']['parameters']),
    );

    const duplicateWidget = flash?.duplicateWidget;
    const duplicateMarketplaceWidget = flash?.duplicateMarketplaceWidget;
    const showOwnDuplicateNotice = duplicateWidget != null && !duplicateDismissed;
    const showMarketplaceDuplicateNotice = duplicateMarketplaceWidget != null && !duplicateDismissed;

    const { data, setData, post, put, processing, errors } = useForm<CreateWidgetForm>(() =>
        buildInitialForm(variables, editingWidget),
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
            chart_config: data.chart_config as Record<string, unknown>,
        }),
        [data.name, data.financial_variable_id, data.display_type, data.period_preset, data.chart_config],
    );

    const { status: previewStatus, payload: previewPayload, errors: previewErrors, onParameterChange: previewParameterChange, isRefreshing: previewRefreshing, isFetching: previewFetching, hasRuntimeParameters: previewHasRuntimeParameters } =
        useFormulaWidgetPreview(previewInput);

    const handleVariableCreated = (variable: FinancialVariableSummary) => {
        setLocalVariables((current) => [...current, variable].sort((a, b) => a.name.localeCompare(b.name, 'it')));
        setData('financial_variable_id', variable.id);
    };

    const setRuntimeParameterEnabled = (parameter: RuntimeParameter, enabled: boolean) => {
        setData((current) => ({
            ...current,
            chart_config: {
                ...current.chart_config,
                parameters: enabled
                    ? upsertRuntimeParameter(current.chart_config.parameters, parameter)
                    : removeRuntimeParameter(current.chart_config.parameters, parameter.key),
            },
        }));
    };

    const setAccountDefault = (accountId: string) => {
        setDefaultAccountId(accountId);
        setData((current) => ({
            ...current,
            chart_config: {
                ...current.chart_config,
                parameters: upsertRuntimeParameter(current.chart_config.parameters, {
                    ...ACCOUNT_PARAMETER,
                    default: accountId,
                }),
            },
        }));
    };

    const accountSelectorEnabled = hasRuntimeParameter(data.chart_config.parameters, 'account_id');
    const periodNavigationEnabled = hasRuntimeParameter(data.chart_config.parameters, 'period_offset');

    const applyMetricPreset = (presetId: string) => {
        const preset = METRIC_QUERY_PRESETS.find((entry) => entry.id === presetId);

        if (!preset) {
            return;
        }

        setData((current) => ({
            ...current,
            name: current.name || preset.suggestedName,
            display_type: preset.displayType,
            period_preset: current.period_preset || 'current_month',
            chart_config: {
                ...current.chart_config,
                format: preset.format,
                metric_query: preset.metricQuery,
                parameters: syncRuntimeParametersFromMetricQuery(
                    preset.metricQuery,
                    current.chart_config.parameters,
                ),
            },
        }));
        setAdvancedOpen(true);
    };

    const setMetricQuery = (metricQuery: MetricQueryDefinition | undefined) => {
        setData((current) => ({
            ...current,
            chart_config: {
                ...current.chart_config,
                metric_query: metricQuery,
                parameters: syncRuntimeParametersFromMetricQuery(metricQuery, current.chart_config.parameters),
            },
        }));
    };

    const applyRecipe = (recipeId: WidgetRecipeId) => {
        setActiveRecipe(recipeId);

        const selectedRecipe = WIDGET_RECIPES.find((recipe) => recipe.id === recipeId);
        if (!selectedRecipe) {
            return;
        }

        setData((current) => {
            const next: CreateWidgetForm = {
                ...current,
                name: current.name || selectedRecipe.suggestedName,
                display_type: selectedRecipe.recommendedDisplayType,
                period_preset: current.period_preset || 'current_month',
                chart_config: {
                    ...current.chart_config,
                    show_delta: recipeId === 'single_value',
                    format: 'currency',
                },
            };

            if (recipeId === 'trend') {
                next.period_preset = current.period_preset || 'calendar_ytd';
            }

            if (recipeId === 'comparison') {
                next.chart_config = {
                    ...next.chart_config,
                    series: ACCOUNT_PERIOD_SERIES,
                    parameters: upsertRuntimeParameter(
                        upsertRuntimeParameter(next.chart_config.parameters, ACCOUNT_PARAMETER),
                        PERIOD_NAV_PARAMETER,
                    ),
                };
            }

            if (recipeId === 'goal') {
                next.chart_config = {
                    ...next.chart_config,
                    value_code: 'period_net',
                    threshold_code: 'period_income',
                };
            }

            return next;
        });
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
                        <SectionCard>
                            <div className="mb-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">
                                    1. Obiettivo
                                </p>
                                <h2 className="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                                    Cosa vuoi vedere in dashboard?
                                </h2>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Parti dal risultato desiderato. Finanzamente imposta grafico e opzioni consigliate.
                                </p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {WIDGET_RECIPES.map((recipe) => (
                                    <button
                                        key={recipe.id}
                                        type="button"
                                        onClick={() => applyRecipe(recipe.id)}
                                        className={clsx(
                                            'rounded-xl border-2 p-4 text-left transition-colors',
                                            activeRecipe === recipe.id
                                                ? 'border-primary-500 bg-primary-50 dark:border-primary-400 dark:bg-primary-900/20'
                                                : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600',
                                        )}
                                    >
                                        <span className="block font-medium text-gray-900 dark:text-white">{recipe.title}</span>
                                        <span className="mt-1 block text-sm text-gray-600 dark:text-gray-400">
                                            {recipe.description}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </SectionCard>

                        <SectionCard>
                            <div className="mb-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">
                                    2. Dati
                                </p>
                                <h2 className="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                                    Scegli metrica e periodo
                                </h2>
                            </div>
                            <div className="space-y-4">
                                <div>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <InputLabel htmlFor="financial_variable_id" value="Metrica da mostrare" />
                                        <div className="flex items-center gap-3 text-sm">
                                            <Link
                                                href={route('formula-variables.index')}
                                                className="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                Gestisci metriche
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => setVariableModalOpen(true)}
                                                className="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                + Nuova metrica
                                            </button>
                                        </div>
                                    </div>
                                    {localVariables.length === 0 ? (
                                        <div className="mt-2 rounded-xl border border-dashed border-primary-300 bg-primary-50/70 p-4 text-sm dark:border-primary-800 dark:bg-primary-950/20">
                                            <p className="font-medium text-primary-900 dark:text-primary-100">
                                                Prima crea una metrica.
                                            </p>
                                            <p className="mt-1 text-gray-600 dark:text-gray-400">
                                                Puoi scegliere scenari pronti come «Bilancio conto» oppure comporre una formula.
                                            </p>
                                            <button
                                                type="button"
                                                onClick={() => setVariableModalOpen(true)}
                                                className="mt-3 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700"
                                            >
                                                Crea metrica guidata
                                            </button>
                                        </div>
                                    ) : (
                                        <select
                                            id="financial_variable_id"
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                            value={data.financial_variable_id}
                                            onChange={(e) => setData('financial_variable_id', Number(e.target.value))}
                                        >
                                            {localVariables.map((variable) => (
                                                <option key={variable.id} value={variable.id}>
                                                    {variable.name}
                                                </option>
                                            ))}
                                        </select>
                                    )}
                                    {selectedVariable?.formula_string && (
                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Formula: <span className="font-mono">{selectedVariable.formula_string}</span>
                                        </p>
                                    )}
                                    <InputError message={errors.financial_variable_id} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="period_preset" value={requiresPeriod ? 'Periodo *' : 'Periodo'} />
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
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Usa “Mese corrente” per spese/incassi, “Anno corrente” o “Storico completo” per trend.
                                    </p>
                                    <InputError message={errors.period_preset} className="mt-1" />
                                </div>
                            </div>
                        </SectionCard>

                        <SectionCard>
                            <div className="mb-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">
                                    2b. Query dinamica (opzionale)
                                </p>
                                <h2 className="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                                    Filtra transazioni, tag e categorie
                                </h2>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Crea metriche avanzate senza scrivere SQL. I filtri segnati come runtime sono modificabili in dashboard.
                                </p>
                            </div>

                            <div className="mb-4 flex flex-wrap gap-2">
                                {METRIC_QUERY_PRESETS.map((preset) => (
                                    <button
                                        key={preset.id}
                                        type="button"
                                        className="rounded-full border border-primary-200 px-3 py-1 text-xs font-medium text-primary-800 hover:bg-primary-50 dark:border-primary-800 dark:text-primary-100 dark:hover:bg-primary-950/40"
                                        onClick={() => applyMetricPreset(preset.id)}
                                    >
                                        {preset.title}
                                    </button>
                                ))}
                            </div>

                            {metricQueryConfig ? (
                                <MetricQueryBuilder
                                    value={data.chart_config.metric_query}
                                    config={metricQueryConfig}
                                    onChange={setMetricQuery}
                                />
                            ) : null}
                        </SectionCard>

                        <SectionCard>
                            <div className="mb-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">
                                    3. Aspetto
                                </p>
                                <h2 className="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                                    Dai un nome e scegli visualizzazione
                                </h2>
                            </div>
                            <div className="space-y-4">
                                <div>
                                    <InputLabel htmlFor="name" value="Nome widget" />
                                    <TextInput
                                        id="name"
                                        className="mt-1 block w-full"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder={selectedVariable?.name ?? 'Es. Bilancio conto'}
                                        required
                                    />
                                    <InputError message={errors.name} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="display_type" value="Vista" />
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
                            </div>
                        </SectionCard>

                        <SectionCard>
                            <button
                                type="button"
                                className="flex w-full items-start justify-between gap-4 text-left"
                                onClick={() => setAdvancedOpen((open) => !open)}
                                aria-expanded={advancedOpen}
                            >
                                <span>
                                    <span className="block text-base font-semibold text-gray-900 dark:text-white">
                                        Opzioni avanzate
                                    </span>
                                    <span className="mt-1 block text-sm text-gray-600 dark:text-gray-400">
                                        Serie multiple, filtri conto/mese in dashboard, soglie KPI e codici tecnici.
                                    </span>
                                </span>
                                <span className="rounded-full border border-gray-200 px-2 py-1 text-xs font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">
                                    {advancedOpen ? 'Nascondi' : 'Apri'}
                                </span>
                            </button>
                        </SectionCard>

                        {advancedOpen && (
                            <SectionCard>
                                <h2 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">Controlli in dashboard</h2>
                                <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                                    Permetti di cambiare filtri direttamente dal widget, senza duplicarlo.
                                </p>
                                <RuntimeParameterPicker
                                    parameters={data.chart_config.parameters}
                                    onToggle={(parameter, enabled) => setRuntimeParameterEnabled(parameter, enabled)}
                                />
                                {accountSelectorEnabled && (
                                    <div className="mt-4">
                                        <InputLabel value="Conto predefinito" />
                                        <select
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                                            value={defaultAccountId}
                                            onChange={(e) => setAccountDefault(e.target.value)}
                                        >
                                            <option value="all">Tutti i conti</option>
                                            {accounts.map((account) => (
                                                <option key={account.id} value={String(account.id)}>
                                                    {account.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                            </SectionCard>
                        )}

                        {advancedOpen && data.display_type === 'kpi' && (
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
                                            <option value="number">Numero</option>
                                        </select>
                                    </div>
                                </div>
                            </SectionCard>
                        )}

                        {advancedOpen && data.display_type === 'progress' && (
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

                        {advancedOpen && usesSeries && (
                            <SectionCard>
                                <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                                    <h2 className="text-base font-semibold text-gray-900 dark:text-white">Serie del grafico</h2>
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        onClick={() =>
                                            setData('chart_config', {
                                                ...data.chart_config,
                                                series: ACCOUNT_PERIOD_SERIES,
                                            })
                                        }
                                    >
                                        Usa serie bilancio conto
                                    </button>
                                </div>
                                <p className="mb-3 text-sm text-gray-600 dark:text-gray-400">
                                    Per un riepilogo incassato/speso/risparmiato: grafico a barre, periodo «Mese corrente», serie sotto e filtri conto/mese in avanzato.
                                </p>
                                <div className="space-y-3">
                                    {((data.chart_config.series ?? []).length > 0 ? data.chart_config.series ?? [] : [{ code: '' }]).map(
                                        (entry, index) => (
                                            <div key={index} className="flex items-end gap-2">
                                                <div className="flex-1">
                                                    <InputLabel value={`Serie ${index + 1}`} />
                                                    <select
                                                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                                                        value={entry.code}
                                                        onChange={(e) => {
                                                            const series = [...(data.chart_config.series ?? [])];
                                                            const selected = systemVariables.find((v) => v.code === e.target.value);
                                                            series[index] = {
                                                                code: e.target.value,
                                                                label: selected?.label ?? e.target.value,
                                                                color: series[index]?.color,
                                                            };
                                                            setData('chart_config', { ...data.chart_config, series });
                                                        }}
                                                    >
                                                        <option value="">—</option>
                                                        {systemVariables.map((variable) => (
                                                            <option key={variable.code} value={variable.code}>
                                                                {variable.label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                                {(data.chart_config.series ?? []).length > 2 && (
                                                    <button
                                                        type="button"
                                                        className="mb-1 rounded-lg px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                                                        onClick={() => {
                                                            const series = (data.chart_config.series ?? []).filter((_, i) => i !== index);
                                                            setData('chart_config', { ...data.chart_config, series });
                                                        }}
                                                    >
                                                        Rimuovi
                                                    </button>
                                                )}
                                            </div>
                                        ),
                                    )}
                                </div>
                                {(data.chart_config.series ?? []).length < 6 && (
                                    <button
                                        type="button"
                                        className="mt-3 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        onClick={() =>
                                            setData('chart_config', {
                                                ...data.chart_config,
                                                series: [...(data.chart_config.series ?? []), { code: '', label: '' }],
                                            })
                                        }
                                    >
                                        + Aggiungi serie
                                    </button>
                                )}
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

                    <FormulaWidgetCreateGuide
                        displayType={data.display_type}
                        chartTypes={chartTypes}
                        systemVariables={systemVariables}
                        metricQueryConfig={metricQueryConfig}
                        hasMetricQuery={Boolean(data.chart_config.metric_query)}
                        className="mb-4"
                    />
                    <FormulaWidgetPreviewPanel
                        status={previewStatus}
                        payload={previewPayload}
                        errors={previewErrors}
                        onParameterChange={previewParameterChange}
                        isRefreshing={previewRefreshing}
                        isFetching={previewFetching}
                        hasRuntimeParameters={previewHasRuntimeParameters}
                        className="lg:self-start"
                    />
                </div>
            </PageContent>

            <CreateFinancialVariableModal
                open={variableModalOpen}
                systemVariables={systemVariables}
                userVariables={localVariables}
                onClose={() => setVariableModalOpen(false)}
                onCreated={handleVariableCreated}
            />
        </AuthenticatedLayout>
    );
}
