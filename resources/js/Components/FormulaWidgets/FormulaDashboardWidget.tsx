import DeferredMount from '@/Components/Dashboard/DeferredMount';
import DashboardWidgetShell, { dashboardWidgetListBodyClass } from '@/Components/Dashboard/DashboardWidgetShell';
import CustomFormulaWidget from '@/Components/FormulaWidgets/CustomFormulaWidget';
import FormulaKpiWidget from '@/Components/FormulaWidgets/FormulaKpiWidget';
import FormulaWidgetSkeleton from '@/Components/FormulaWidgets/FormulaWidgetSkeleton';
import FormulaWidgetTypeBadge from '@/Components/FormulaWidgets/FormulaWidgetTypeBadge';
import PencilIcon from '@/Components/Icons/PencilIcon';
import type { WidgetId } from '@/types/dashboard';
import type { FormulaWidgetMeta, FormulaWidgetPayload } from '@/types/formulaWidget';
import { parseFormulaWidgetNumericId } from '@/types/formulaWidget';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

function isFormulaKpiOrProgressPayload(
    payload: FormulaWidgetPayload,
): payload is Extract<FormulaWidgetPayload, { type: 'kpi' }> | Extract<FormulaWidgetPayload, { type: 'progress' }> {
    return payload.type === 'kpi' || payload.type === 'progress';
}

function formulaWidgetTitleBadge(meta?: FormulaWidgetMeta): ReactNode {
    if (!meta?.display_type) {
        return undefined;
    }

    return <FormulaWidgetTypeBadge displayType={meta.display_type} />;
}

function formulaWidgetEditAction(numericId: string, widgetName: string) {
    return (
        <Link
            href={route('formula-widgets.edit', numericId)}
            className="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-primary-600 dark:hover:bg-gray-800 dark:hover:text-primary-400"
            aria-label={`Modifica ${widgetName}`}
            title="Modifica widget"
        >
            <PencilIcon className="h-4 w-4" size={16} />
        </Link>
    );
}

function formulaWidgetHeaderActions(
    numericId: string,
    widgetName: string,
    editing: boolean,
    meta?: FormulaWidgetMeta,
): ReactNode {
    if (editing || meta?.can_edit === false) {
        return undefined;
    }

    return formulaWidgetEditAction(numericId, widgetName);
}

function formulaWidgetSkeleton(title: string, meta?: FormulaWidgetMeta): ReactNode {
    return (
        <FormulaWidgetSkeleton
            title={title}
            displayType={meta?.display_type}
            variant={meta?.variant}
        />
    );
}

interface FormulaDashboardWidgetProps {
    widgetId: WidgetId;
    editing: boolean;
    payload?: FormulaWidgetPayload;
    meta?: FormulaWidgetMeta;
    isRefreshing: boolean;
    parameterControlsDisabled: boolean;
    onParameterChange: (key: string, value: string) => void;
}

/**
 * Widget formula in dashboard — componente stabile per preservare lo stato di DeferredMount
 * quando altri widget completano il fetch dei payload.
 */
export default function FormulaDashboardWidget({
    widgetId,
    editing,
    payload,
    meta,
    isRefreshing,
    parameterControlsDisabled,
    onParameterChange,
}: FormulaDashboardWidgetProps) {
    const numericId = parseFormulaWidgetNumericId(widgetId);
    if (!numericId) {
        return null;
    }

    if (!payload) {
        const label = meta?.name ?? 'Widget a formula';

        if (!editing) {
            return (
                <FormulaWidgetSkeleton
                    title={label}
                    displayType={meta?.display_type}
                    variant={meta?.variant}
                />
            );
        }

        return (
            <DashboardWidgetShell title={label} titleBadge={formulaWidgetTitleBadge(meta)} bodyClassName={dashboardWidgetListBodyClass}>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Anteprima non disponibile. Salva il layout o ricarica la pagina se il widget è stato rimosso.
                </p>
            </DashboardWidgetShell>
        );
    }

    if (isFormulaKpiOrProgressPayload(payload)) {
        if (payload.type === 'kpi' && payload.variant === 'balance_summary') {
            return <FormulaKpiWidget payload={payload} embedded />;
        }

        const headerActions = formulaWidgetHeaderActions(numericId, payload.name, editing, meta);

        return (
            <DashboardWidgetShell
                title={payload.name}
                subtitle={payload.periodLabel}
                titleBadge={formulaWidgetTitleBadge(meta)}
                bodyClassName={dashboardWidgetListBodyClass}
                headerActions={headerActions}
            >
                <CustomFormulaWidget
                    payload={payload}
                    embedded
                    onParameterChange={onParameterChange}
                    parameterControlsDisabled={parameterControlsDisabled}
                    refreshing={isRefreshing}
                />
            </DashboardWidgetShell>
        );
    }

    const headerActions = formulaWidgetHeaderActions(numericId, payload.name, editing, meta);

    return (
        <DeferredMount fallback={formulaWidgetSkeleton(payload.name, meta)} scheduleIdle>
            <DashboardWidgetShell
                title={payload.name}
                subtitle={payload.periodLabel}
                titleBadge={formulaWidgetTitleBadge(meta)}
                bodyClassName={dashboardWidgetListBodyClass}
                headerActions={headerActions}
            >
                <CustomFormulaWidget
                    payload={payload}
                    embedded
                    onParameterChange={onParameterChange}
                    parameterControlsDisabled={parameterControlsDisabled}
                    refreshing={isRefreshing}
                />
            </DashboardWidgetShell>
        </DeferredMount>
    );
}
