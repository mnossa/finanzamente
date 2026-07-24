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
import MetricScenarioPicker from '@/Components/FormulaWidgets/MetricScenarioPicker';
import RuntimeParameterPicker from '@/Components/FormulaWidgets/RuntimeParameterPicker';
import { useFormulaWidgetPreview } from '@/hooks/useFormulaWidgetPreview';
import {
    formulaWidgetRequiresPeriod,
    formulaWidgetUsesSeries,
} from '@/utils/formulaWidgetForm';
import type { AccountOption } from '@/utils/formulaWidgetPresets';
import {
    findScenarioByFormula,
    type FinancialVariableScenario,
} from '@/utils/financialVariableScenarios';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import clsx from 'clsx';
import { FormEventHandler, useEffect, useMemo, useState } from 'react';
import type { FinancialVariableSummary, FormulaWidgetChartConfig, FormulaWidgetParameterType, FormulaWidgetSummary, MetricQueryDefinition, SystemVariableMeta } from '@/types/formulaWidget';
import type { MetricQueryConfig } from '@/utils/metricQueryForm';
import {
    GROUP_BY_LABELS,
    METRIC_QUERY_PRESETS,
    QUICK_FILTER_PRESETS,
    createEmptyMetricQuery,
    metricQueryHasFilter,
    removeMetricFilter,
    syncRuntimeParametersFromMetricQuery,
    upsertMetricFilter,
} from '@/utils/metricQueryForm';
import {
    availableRuntimeControls,
    pruneRuntimeParameters,
    type WidgetRecipeId,
} from '@/utils/formulaWidgetRuntimeControls';
import type { PageProps } from '@/types';

const PRIMARY_DISPLAY_TYPES = ['kpi', 'line', 'area', 'bar', 'progress', 'table'] as const;

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
        recommendedDisplayType: 'line',
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
    {
        id: 'tabular',
        title: 'Tabella / lista',
        description: 'Ultime transazioni, PAC o totali per categoria con gli stessi filtri degli altri widget.',
        suggestedName: 'Elenco movimenti',
        recommendedDisplayType: 'table',
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
        pin_to_dashboard: editingWidget == null,
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
            table: (chartConfig.table as FormulaWidgetChartConfig['table'] | undefined) ?? undefined,
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
    const [ensuringScenarioId, setEnsuringScenarioId] = useState<string | null>(null);
    const [metricNotice, setMetricNotice] = useState<string | null>(null);
    const [metricError, setMetricError] = useState<string | null>(null);
    const [duplicateDismissed, setDuplicateDismissed] = useState(false);
    const [advancedOpen, setAdvancedOpen] = useState(isEditing);
    const [activeRecipe, setActiveRecipe] = useState<WidgetRecipeId>('single_value');
    const useWizard = !isEditing;
    const [wizardStep, setWizardStep] = useState<1 | 2 | 3>(isEditing ? 3 : 1);
    const [filtersOpen, setFiltersOpen] = useState(
        Boolean((editingWidget?.chart_config as FormulaWidgetChartConfig | undefined)?.metric_query),
    );
    const [showAllChartTypes, setShowAllChartTypes] = useState(false);
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

    const usesSeries = formulaWidgetUsesSeries(data.display_type);
    const selectedVariable = localVariables.find((variable) => variable.id === data.financial_variable_id);
    const selectedScenario = findScenarioByFormula(selectedVariable?.formula_string);
    const requiresPeriod =
        formulaWidgetRequiresPeriod(data.display_type, data.chart_config)
        || Boolean(selectedScenario?.requiresPeriod);
    const availableRuntimeParameters = useMemo(
        () => availableRuntimeControls({
            periodPreset: data.period_preset,
            requiresPeriod,
            formulaString: selectedVariable?.formula_string,
            metricQuery: data.chart_config.metric_query,
            displayType: data.display_type,
            chartVariant: data.chart_config.variant ?? null,
        }),
        [
            data.period_preset,
            requiresPeriod,
            selectedVariable?.formula_string,
            data.chart_config.metric_query,
            data.display_type,
            data.chart_config.variant,
        ],
    );

    useEffect(() => {
        const current = data.chart_config.parameters ?? [];
        const pruned = pruneRuntimeParameters(current, availableRuntimeParameters);
        const currentKeys = current.map((parameter) => parameter.key).join('|');
        const prunedKeys = pruned.map((parameter) => parameter.key).join('|');
        if (currentKeys === prunedKeys) {
            return;
        }

        setData((form) => ({
            ...form,
            chart_config: {
                ...form.chart_config,
                parameters: pruned.length > 0 ? pruned : undefined,
            },
        }));
    }, [availableRuntimeParameters, data.chart_config.parameters, setData]);


    const previewInput = useMemo(
        () => ({
            name: data.name,
            financial_variable_id: data.financial_variable_id,
            display_type: data.display_type,
            period_preset: data.period_preset,
            chart_config: data.chart_config as Record<string, unknown>,
            requirePeriod: requiresPeriod,
        }),
        [data.name, data.financial_variable_id, data.display_type, data.period_preset, data.chart_config, requiresPeriod],
    );

    const { status: previewStatus, payload: previewPayload, errors: previewErrors, onParameterChange: previewParameterChange, isRefreshing: previewRefreshing, isFetching: previewFetching, hasRuntimeParameters: previewHasRuntimeParameters } =
        useFormulaWidgetPreview(previewInput);

    const handleVariableCreated = (variable: FinancialVariableSummary, notice?: string) => {
        setLocalVariables((current) => {
            if (current.some((entry) => entry.id === variable.id)) {
                return current;
            }

            return [...current, variable].sort((a, b) => a.name.localeCompare(b.name, 'it'));
        });
        setData((current) => {
            const scenario = findScenarioByFormula(variable.formula_string);
            // Metriche di periodo: forza un periodo se manca, così anteprima e label restano allineate.
            const nextPeriod = scenario?.requiresPeriod
                ? current.period_preset || 'current_month'
                : current.period_preset;
            const previousVariable = localVariables.find((entry) => entry.id === current.financial_variable_id);
            // Aggiorna nome solo se ancora “auto” (vuoto o uguale alla metrica precedente).
            const nameStillAuto = !current.name || (previousVariable != null && current.name === previousVariable.name);

            let next: CreateWidgetForm = {
                ...current,
                financial_variable_id: variable.id,
                period_preset: nextPeriod,
                name: nameStillAuto ? variable.name : current.name,
            };

            if (scenario?.widgetDefaults) {
                const defaults = scenario.widgetDefaults;
                next = {
                    ...next,
                    display_type: defaults.displayType,
                    chart_config: {
                        ...next.chart_config,
                        ...defaults.chartConfig,
                        parameters: defaults.chartConfig.parameters ?? next.chart_config.parameters,
                    },
                };
            } else if (next.chart_config.variant === 'traffic_light') {
                // Uscita dall’alert: togli semaforo e soglia numerica.
                const { variant: _variant, threshold_amount: _amount, bands: _bands, ...restConfig } = next.chart_config;
                next = {
                    ...next,
                    chart_config: {
                        ...restConfig,
                        parameters: (next.chart_config.parameters ?? []).filter((parameter) => parameter.key !== 'threshold'),
                    },
                };
            }

            return next;
        });
        setMetricNotice(notice ?? `Metrica «${variable.name}» selezionata`);
        setMetricError(null);
    };

    const metricSelectionNotice = (
        cardLabel: string,
        variable: FinancialVariableSummary,
        created = false,
    ): string => {
        const base = created
            ? `Metrica «${cardLabel}» creata e selezionata`
            : `Metrica «${cardLabel}» selezionata`;

        if (variable.name !== cardLabel) {
            return `${base} · in libreria come «${variable.name}»`;
        }

        return base;
    };

    const selectExistingVariable = (variable: FinancialVariableSummary) => {
        handleVariableCreated(variable, `Metrica «${variable.name}» selezionata`);
    };

    const selectScenarioMetric = async (scenario: FinancialVariableScenario) => {
        if (scenario.id === 'custom' || scenario.formula === '') {
            setVariableModalOpen(true);
            return;
        }

        // Già selezionata → no-op (evita ensure/preview thrash).
        if (selectedScenario?.id === scenario.id) {
            return;
        }

        // Riuso locale se formula già in libreria caricata (preferisci non ufficiali).
        const localMatches = localVariables.filter(
            (variable) => findScenarioByFormula(variable.formula_string)?.id === scenario.id,
        );
        const localMatch =
            localMatches.find((variable) => !variable.is_official_origin)
            ?? localMatches[0];
        if (localMatch) {
            handleVariableCreated(localMatch, metricSelectionNotice(scenario.name, localMatch));
            return;
        }

        setEnsuringScenarioId(scenario.id);
        setMetricError(null);

        try {
            const response = await axios.post(
                route('formula-variables.ensure'),
                {
                    name: scenario.name,
                    code: scenario.suggestedCode,
                    type: 'formula',
                    formula_string: scenario.formula,
                },
                { headers: { Accept: 'application/json' } },
            );

            const variable = response.data.variable as FinancialVariableSummary;
            const created = Boolean(response.data.created);
            handleVariableCreated(
                variable,
                metricSelectionNotice(scenario.name, variable, created),
            );
        } catch {
            setMetricError('Non sono riuscito a preparare la metrica. Riprova.');
        } finally {
            setEnsuringScenarioId(null);
        }
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
        setFiltersOpen(true);
    };

    const toggleQuickFilter = (presetId: string) => {
        const preset = QUICK_FILTER_PRESETS.find((entry) => entry.id === presetId);
        if (!preset) {
            return;
        }

        setData((current) => {
            const base = current.chart_config.metric_query ?? createEmptyMetricQuery();
            const active = metricQueryHasFilter(base, preset.filter.field, preset.filter.operator);
            const filters = active
                ? removeMetricFilter(base.filters ?? [], preset.filter.field, preset.filter.operator)
                : upsertMetricFilter(base.filters ?? [], preset.filter);
            const metricQuery = { ...base, filters };

            return {
                ...current,
                chart_config: {
                    ...current.chart_config,
                    metric_query: metricQuery,
                    parameters: syncRuntimeParametersFromMetricQuery(
                        metricQuery,
                        current.chart_config.parameters,
                    ),
                },
            };
        });
        setFiltersOpen(true);
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
                next.period_preset = 'current_month';
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
                    variant: undefined,
                    threshold_amount: undefined,
                    bands: undefined,
                    parameters: (next.chart_config.parameters ?? []).filter((parameter) => parameter.key !== 'threshold'),
                };
            }

            if (recipeId === 'tabular') {
                next.period_preset = current.period_preset || 'current_month';
                next.chart_config = {
                    ...next.chart_config,
                    metric_query: createEmptyMetricQuery('transactions'),
                    table: {
                        mode: 'rows',
                        row_limit: 10,
                        sort: { field: 'date', direction: 'desc' },
                    },
                    parameters: upsertRuntimeParameter(next.chart_config.parameters, ACCOUNT_PARAMETER),
                };
                setFiltersOpen(true);
            }

            if (recipeId !== 'tabular' && next.chart_config.table) {
                next.chart_config = {
                    ...next.chart_config,
                    table: undefined,
                };
            }

            if (recipeId !== 'goal' && next.chart_config.variant === 'traffic_light') {
                next.chart_config = {
                    ...next.chart_config,
                    variant: undefined,
                    threshold_amount: undefined,
                    bands: undefined,
                    parameters: (next.chart_config.parameters ?? []).filter((parameter) => parameter.key !== 'threshold'),
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

    const pageTitle = isEditing ? 'Modifica widget' : 'Nuovo widget';
    const showStep1 = !useWizard || wizardStep === 1;
    const showStep2 = !useWizard || wizardStep === 2;
    const showStep3 = !useWizard || wizardStep === 3;
    const canContinueFromStep2 =
        data.financial_variable_id !== ''
        && (!requiresPeriod || data.period_preset !== '');

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
                    <aside className="order-first space-y-4 lg:order-last lg:self-start">
                        <FormulaWidgetCreateGuide
                            displayType={data.display_type}
                            chartTypes={chartTypes}
                            systemVariables={systemVariables}
                            metricQueryConfig={metricQueryConfig}
                            hasMetricQuery={Boolean(data.chart_config.metric_query)}
                            className="mb-0 hidden lg:block"
                        />
                        {(showStep2 || showStep3 || isEditing) ? (
                            <FormulaWidgetPreviewPanel
                                status={previewStatus}
                                payload={previewPayload}
                                errors={previewErrors}
                                onParameterChange={previewParameterChange}
                                isRefreshing={previewRefreshing}
                                isFetching={previewFetching}
                                hasRuntimeParameters={previewHasRuntimeParameters}
                                formulaString={selectedVariable?.formula_string}
                                className="z-10 sticky top-16 lg:top-4"
                            />
                        ) : null}
                    </aside>

                    <form onSubmit={submit} className="order-last space-y-6 lg:order-first">
                        {useWizard ? (
                            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <span className={clsx(wizardStep === 1 && 'text-primary-700 dark:text-primary-300')}>1. Obiettivo</span>
                                <span aria-hidden>·</span>
                                <span className={clsx(wizardStep === 2 && 'text-primary-700 dark:text-primary-300')}>2. Metrica</span>
                                <span aria-hidden>·</span>
                                <span className={clsx(wizardStep === 3 && 'text-primary-700 dark:text-primary-300')}>3. Aspetto</span>
                            </div>
                        ) : null}

                        {showStep1 ? (
                        <SectionCard>
                            <div className="mb-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">
                                    1. Obiettivo
                                </p>
                                <h2 className="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                                    Cosa vuoi vedere in dashboard?
                                </h2>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Scegli il risultato. Impostiamo grafico e opzioni consigliate.
                                </p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {WIDGET_RECIPES.map((recipe) => (
                                    <button
                                        key={recipe.id}
                                        type="button"
                                        aria-labelledby={`widget-recipe-title-${recipe.id}`}
                                        aria-describedby={`widget-recipe-desc-${recipe.id}`}
                                        aria-pressed={activeRecipe === recipe.id}
                                        onClick={() => applyRecipe(recipe.id)}
                                        className={clsx(
                                            'rounded-xl border-2 p-4 text-left transition-colors',
                                            activeRecipe === recipe.id
                                                ? 'border-primary-500 bg-primary-50 dark:border-primary-400 dark:bg-primary-900/20'
                                                : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600',
                                        )}
                                    >
                                        <span id={`widget-recipe-title-${recipe.id}`} className="block font-medium text-gray-900 dark:text-white">{recipe.title}</span>
                                        <span id={`widget-recipe-desc-${recipe.id}`} className="mt-1 block text-sm text-gray-600 dark:text-gray-400">
                                            {recipe.description}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </SectionCard>
                        ) : null}

                        {showStep2 ? (
                        <SectionCard>
                            <div className="mb-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">
                                    2. Metrica
                                </p>
                                <h2 className="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                                    Scegli metrica e periodo
                                </h2>
                            </div>
                            <div className="space-y-4">
                                <div>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <InputLabel value="Metrica da mostrare" />
                                        <Link
                                            href={route('formula-variables.index')}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        >
                                            Gestisci metriche
                                        </Link>
                                    </div>

                                    <div className="mt-2">
                                        <MetricScenarioPicker
                                            key={activeRecipe}
                                            variables={localVariables}
                                            selectedVariableId={data.financial_variable_id}
                                            ensuringScenarioId={ensuringScenarioId}
                                            recipeId={activeRecipe}
                                            onSelectScenario={selectScenarioMetric}
                                            onSelectVariable={selectExistingVariable}
                                            onOpenCustomFormula={() => setVariableModalOpen(true)}
                                        />
                                    </div>

                                    {metricNotice && data.financial_variable_id !== '' ? (
                                        <p className="mt-2 text-sm font-medium text-primary-700 dark:text-primary-300" role="status">
                                            {metricNotice}
                                            {selectedVariable ? ' · anteprima aggiornata' : ''}
                                        </p>
                                    ) : null}
                                    {metricError ? (
                                        <p className="mt-2 text-sm text-red-600 dark:text-red-400">{metricError}</p>
                                    ) : null}
                                    <InputError message={errors.financial_variable_id} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel value={requiresPeriod ? 'Periodo *' : 'Periodo'} />
                                    <div
                                        className="mt-2 flex flex-wrap gap-2"
                                        role="group"
                                        aria-label="Periodo del widget"
                                    >
                                        {!requiresPeriod ? (
                                            <button
                                                type="button"
                                                aria-pressed={data.period_preset === ''}
                                                onClick={() => setData('period_preset', '')}
                                                className={clsx(
                                                    'rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                                                    data.period_preset === ''
                                                        ? 'border-primary-500 bg-primary-50 text-primary-800 ring-1 ring-primary-500 dark:border-primary-400 dark:bg-primary-950/40 dark:text-primary-100'
                                                        : 'border-surface-200 bg-white text-gray-700 hover:border-primary-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200',
                                                )}
                                            >
                                                Ad oggi
                                            </button>
                                        ) : null}
                                        {Object.entries(periodPresets).map(([key, meta]) => (
                                            <button
                                                key={key}
                                                type="button"
                                                aria-pressed={data.period_preset === key}
                                                onClick={() => setData('period_preset', key)}
                                                className={clsx(
                                                    'rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                                                    data.period_preset === key
                                                        ? 'border-primary-500 bg-primary-50 text-primary-800 ring-1 ring-primary-500 dark:border-primary-400 dark:bg-primary-950/40 dark:text-primary-100'
                                                        : 'border-surface-200 bg-white text-gray-700 hover:border-primary-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200',
                                                )}
                                            >
                                                {meta.label}
                                            </button>
                                        ))}
                                    </div>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Come l&apos;intervallo del grafico Excel: mese, anno o ad oggi.
                                    </p>
                                    <InputError message={errors.period_preset} className="mt-1" />
                                </div>

                                <div className="rounded-xl border border-surface-200 dark:border-gray-700">
                                    <button
                                        type="button"
                                        className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
                                        onClick={() => setFiltersOpen((open) => !open)}
                                        aria-expanded={filtersOpen}
                                    >
                                        <span>
                                            <span className="block text-sm font-semibold text-gray-900 dark:text-white">
                                                Filtra movimenti (opzionale)
                                            </span>
                                            <span className="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                                Tag, categorie e filtri modificabili in dashboard.
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-xs font-medium text-primary-700 dark:text-primary-300">
                                            {filtersOpen ? 'Nascondi' : 'Apri'}
                                        </span>
                                    </button>
                                    <div className="flex flex-wrap gap-2 border-t border-surface-200 px-4 py-3 dark:border-gray-700">
                                        {QUICK_FILTER_PRESETS.map((preset) => {
                                            const active = metricQueryHasFilter(
                                                data.chart_config.metric_query,
                                                preset.filter.field,
                                                preset.filter.operator,
                                            );

                                            return (
                                                <button
                                                    key={preset.id}
                                                    type="button"
                                                    aria-pressed={active}
                                                    onClick={() => toggleQuickFilter(preset.id)}
                                                    className={clsx(
                                                        'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                                                        active
                                                            ? 'border-primary-500 bg-primary-50 text-primary-800 ring-1 ring-primary-500 dark:border-primary-400 dark:bg-primary-950/40 dark:text-primary-100'
                                                            : 'border-primary-200 text-primary-800 hover:bg-primary-50 dark:border-primary-800 dark:text-primary-100 dark:hover:bg-primary-950/40',
                                                    )}
                                                >
                                                    {preset.title}
                                                </button>
                                            );
                                        })}
                                    </div>
                                    {filtersOpen ? (
                                        <div className="space-y-4 border-t border-surface-200 px-4 py-4 dark:border-gray-700">
                                            <div className="flex flex-wrap gap-2">
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
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        </SectionCard>
                        ) : null}

                        {showStep3 ? (
                        <>
                        <SectionCard>
                            <div className="mb-4">
                                <p className="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">
                                    3. Aspetto
                                </p>
                                <h2 className="mt-1 text-base font-semibold text-gray-900 dark:text-white">
                                    Nome e visualizzazione
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
                                        required={showStep3}
                                    />
                                    <InputError message={errors.name} className="mt-1" />
                                </div>

                                <div>
                                    <InputLabel value="Vista" />
                                    <div
                                        className="mt-2 flex flex-wrap gap-2"
                                        role="group"
                                        aria-label="Tipo di visualizzazione"
                                    >
                                        {(showAllChartTypes
                                            ? Object.keys(chartTypes)
                                            : (() => {
                                                const keys = PRIMARY_DISPLAY_TYPES.filter((key) => chartTypes[key] != null) as string[];
                                                if (
                                                    data.display_type
                                                    && chartTypes[data.display_type]
                                                    && !keys.includes(data.display_type)
                                                ) {
                                                    keys.unshift(data.display_type);
                                                }

                                                return keys;
                                            })()
                                        ).map((key) => (
                                            <button
                                                key={key}
                                                type="button"
                                                aria-pressed={data.display_type === key}
                                                onClick={() => {
                                                    setData((current) => {
                                                        if (key !== 'table') {
                                                            return {
                                                                ...current,
                                                                display_type: key,
                                                                chart_config: {
                                                                    ...current.chart_config,
                                                                    table: undefined,
                                                                },
                                                            };
                                                        }

                                                        return {
                                                            ...current,
                                                            display_type: 'table',
                                                            chart_config: {
                                                                ...current.chart_config,
                                                                metric_query: current.chart_config.metric_query
                                                                    ?? createEmptyMetricQuery('transactions'),
                                                                table: current.chart_config.table ?? {
                                                                    mode: 'rows',
                                                                    row_limit: 10,
                                                                    sort: { field: 'date', direction: 'desc' },
                                                                },
                                                            },
                                                        };
                                                    });
                                                    if (key === 'table') {
                                                        setFiltersOpen(true);
                                                    }
                                                }}
                                                className={clsx(
                                                    'rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                                                    data.display_type === key
                                                        ? 'border-primary-500 bg-primary-50 text-primary-800 ring-1 ring-primary-500 dark:border-primary-400 dark:bg-primary-950/40 dark:text-primary-100'
                                                        : 'border-surface-200 bg-white text-gray-700 hover:border-primary-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200',
                                                )}
                                            >
                                                {chartTypes[key]?.label ?? key}
                                            </button>
                                        ))}
                                    </div>
                                    {!showAllChartTypes && Object.keys(chartTypes).length > PRIMARY_DISPLAY_TYPES.length ? (
                                        <button
                                            type="button"
                                            className="mt-2 text-xs font-medium text-primary-700 hover:underline dark:text-primary-300"
                                            onClick={() => setShowAllChartTypes(true)}
                                        >
                                            Mostra tutti ({Object.keys(chartTypes).length})
                                        </button>
                                    ) : showAllChartTypes ? (
                                        <button
                                            type="button"
                                            className="mt-2 text-xs font-medium text-gray-500 hover:underline dark:text-gray-400"
                                            onClick={() => setShowAllChartTypes(false)}
                                        >
                                            Mostra meno
                                        </button>
                                    ) : null}
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {chartTypes[data.display_type]?.description}
                                    </p>
                                    <InputError message={errors.display_type} className="mt-1" />
                                </div>

                                {data.display_type === 'table' ? (
                                    <div className="space-y-4 rounded-xl border border-surface-200 p-4 dark:border-gray-700">
                                        <div>
                                            <InputLabel value="Modalità tabella" />
                                            <div className="mt-2 flex flex-wrap gap-2" role="group" aria-label="Modalità tabella">
                                                {(['rows', 'aggregate'] as const).map((mode) => (
                                                    <button
                                                        key={mode}
                                                        type="button"
                                                        aria-pressed={(data.chart_config.table?.mode ?? 'rows') === mode}
                                                        onClick={() => {
                                                            setData((current) => ({
                                                                ...current,
                                                                chart_config: {
                                                                    ...current.chart_config,
                                                                    metric_query: current.chart_config.metric_query
                                                                        ?? createEmptyMetricQuery('transactions'),
                                                                    table: {
                                                                        mode,
                                                                        row_limit: current.chart_config.table?.row_limit ?? 10,
                                                                        group_by: mode === 'aggregate'
                                                                            ? (current.chart_config.table?.group_by
                                                                                ?? metricQueryConfig?.datasources[
                                                                                    current.chart_config.metric_query?.datasource
                                                                                        ?? 'transactions'
                                                                                ]?.group_by_fields?.[0])
                                                                            : undefined,
                                                                        sort: current.chart_config.table?.sort
                                                                            ?? { field: 'date', direction: 'desc' },
                                                                    },
                                                                },
                                                            }));
                                                            setFiltersOpen(true);
                                                        }}
                                                        className={clsx(
                                                            'rounded-full border px-3 py-1.5 text-sm font-medium transition-colors',
                                                            (data.chart_config.table?.mode ?? 'rows') === mode
                                                                ? 'border-primary-500 bg-primary-50 text-primary-800 ring-1 ring-primary-500 dark:border-primary-400 dark:bg-primary-950/40 dark:text-primary-100'
                                                                : 'border-surface-200 bg-white text-gray-700 hover:border-primary-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200',
                                                        )}
                                                    >
                                                        {mode === 'rows' ? 'Lista righe' : 'Aggregata'}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>

                                        {(data.chart_config.table?.mode ?? 'rows') === 'aggregate' ? (
                                            <div>
                                                <InputLabel value="Raggruppa per" />
                                                <select
                                                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                                                    value={data.chart_config.table?.group_by ?? ''}
                                                    onChange={(e) => {
                                                        const groupBy = e.target.value;
                                                        setData((current) => ({
                                                            ...current,
                                                            chart_config: {
                                                                ...current.chart_config,
                                                                table: {
                                                                    mode: 'aggregate',
                                                                    row_limit: current.chart_config.table?.row_limit ?? 10,
                                                                    group_by: groupBy,
                                                                },
                                                            },
                                                        }));
                                                    }}
                                                >
                                                    {(metricQueryConfig?.datasources[
                                                        data.chart_config.metric_query?.datasource ?? 'transactions'
                                                    ]?.group_by_fields ?? []).map((field) => (
                                                        <option key={field} value={field}>
                                                            {GROUP_BY_LABELS[field] ?? field}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        ) : null}

                                        <div>
                                            <InputLabel htmlFor="table_row_limit" value="Numero righe" />
                                            <TextInput
                                                id="table_row_limit"
                                                type="number"
                                                min={1}
                                                max={metricQueryConfig?.max_row_limit ?? 50}
                                                className="mt-1 block w-full"
                                                value={data.chart_config.table?.row_limit ?? metricQueryConfig?.default_row_limit ?? 10}
                                                onChange={(e) => {
                                                    const rowLimit = Number(e.target.value) || 10;
                                                    setData((current) => ({
                                                        ...current,
                                                        chart_config: {
                                                            ...current.chart_config,
                                                            table: {
                                                                mode: current.chart_config.table?.mode ?? 'rows',
                                                                row_limit: rowLimit,
                                                                group_by: current.chart_config.table?.group_by,
                                                                sort: current.chart_config.table?.sort,
                                                                columns: current.chart_config.table?.columns,
                                                            },
                                                        },
                                                    }));
                                                }}
                                            />
                                        </div>
                                    </div>
                                ) : null}
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
                                        Controlli in dashboard, KPI, soglie e serie del grafico.
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
                                    availableParameters={availableRuntimeParameters}
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
                                {data.chart_config.variant === 'traffic_light' ? (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel value="Valore monitorato" />
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
                                            <InputLabel value="Soglia (€)" />
                                            <TextInput
                                                type="number"
                                                min={0}
                                                step={50}
                                                className="mt-1 block w-full"
                                                value={String(
                                                    data.chart_config.parameters?.find((parameter) => parameter.key === 'threshold')?.default
                                                        ?? data.chart_config.threshold_amount
                                                        ?? 1000,
                                                )}
                                                onChange={(e) => {
                                                    const amount = e.target.value;
                                                    setData('chart_config', {
                                                        ...data.chart_config,
                                                        threshold_amount: Number(amount) || 0,
                                                        parameters: upsertRuntimeParameter(
                                                            data.chart_config.parameters,
                                                            {
                                                                key: 'threshold',
                                                                type: 'number',
                                                                label: 'Soglia (€)',
                                                                default: amount || '0',
                                                            },
                                                        ),
                                                    });
                                                }}
                                            />
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Verde sotto il 70%, arancione fino al 100%, rosso oltre la soglia.
                                            </p>
                                        </div>
                                    </div>
                                ) : (
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
                                )}
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
                        </>
                        ) : null}

                        <FormActionsBar sticky={false} clearMobileChrome>
                            {useWizard && wizardStep > 1 ? (
                                <button
                                    type="button"
                                    className="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400"
                                    onClick={() => setWizardStep((step) => (step === 3 ? 2 : 1))}
                                >
                                    Indietro
                                </button>
                            ) : (
                                <Link
                                    href={route('formula-widgets.index')}
                                    className="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400"
                                >
                                    Annulla
                                </Link>
                            )}
                            <InputError
                                message={
                                    showOwnDuplicateNotice || showMarketplaceDuplicateNotice
                                        ? undefined
                                        : (errors as { widget?: string }).widget
                                }
                                className="sm:mr-auto"
                            />
                            {useWizard && wizardStep < 3 ? (
                                <PrimaryButton
                                    type="button"
                                    disabled={wizardStep === 2 && (!canContinueFromStep2 || ensuringScenarioId !== null)}
                                    onClick={() => {
                                        // Evita che il click “passi” al bottone submit sostituito nello stesso slot.
                                        window.setTimeout(() => {
                                            setWizardStep((step) => (step === 1 ? 2 : 3));
                                        }, 0);
                                    }}
                                >
                                    Continua
                                </PrimaryButton>
                            ) : (
                                <PrimaryButton
                                    type="submit"
                                    data-testid="create-widget-submit"
                                    disabled={
                                        processing
                                        || ensuringScenarioId !== null
                                        || data.financial_variable_id === ''
                                        || (requiresPeriod && data.period_preset === '')
                                    }
                                >
                                    {isEditing ? 'Salva modifiche' : 'Crea widget'}
                                </PrimaryButton>
                            )}
                        </FormActionsBar>
                    </form>
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
