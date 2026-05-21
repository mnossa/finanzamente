import CardBox from '@/Components/CardBox';
import {
    TAB_LABELS,
    serializeTabState,
    type SimulationTabId,
    type SimulationTabStates,
} from '@/utils/simulationTabState';
import { type FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import clsx from 'clsx';
import { useEffect, useState } from 'react';

export interface SavedScenarioListItem {
    id: number;
    name: string;
    tab: SimulationTabId;
    payload?: Record<string, unknown>;
    updated_at: string | null;
}

interface Props {
    activeTab: SimulationTabId;
    tabStates: SimulationTabStates;
    savedScenarios: SavedScenarioListItem[];
    loadedScenarioId?: number | null;
    onLoadScenario: (tab: SimulationTabId, payload: Record<string, unknown>) => void;
    onLoadedScenarioChange: (id: number | null) => void;
}

export default function SimulationSavePanel({
    activeTab,
    tabStates,
    savedScenarios,
    loadedScenarioId: loadedScenarioIdProp,
    onLoadScenario,
    onLoadedScenarioChange,
}: Props) {
    const [scenarioName, setScenarioName] = useState('');
    const [loadedScenarioId, setLoadedScenarioId] = useState<number | null>(loadedScenarioIdProp ?? null);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (loadedScenarioIdProp != null) {
            setLoadedScenarioId(loadedScenarioIdProp);
        }
    }, [loadedScenarioIdProp]);

    const loadedScenario = savedScenarios.find((s) => s.id === loadedScenarioId) ?? null;
    const scenariosForTab = savedScenarios.filter((s) => s.tab === activeTab);

    const handleSelectScenario = (id: string) => {
        const numericId = Number(id);
        if (!id || Number.isNaN(numericId)) {
            setLoadedScenarioId(null);
            onLoadedScenarioChange(null);
            return;
        }
        const scenario = savedScenarios.find((s) => s.id === numericId);
        if (!scenario?.payload) {
            return;
        }
        setLoadedScenarioId(scenario.id);
        onLoadedScenarioChange(scenario.id);
        setScenarioName(scenario.name);
        if (scenario.tab !== activeTab) {
            return;
        }
        onLoadScenario(scenario.tab, scenario.payload);
    };

    const handleSave = () => {
        const name = scenarioName.trim();
        if (!name) {
            return;
        }
        setProcessing(true);
        router.post(
            route('simulation-scenarios.store'),
            {
                name,
                tab: activeTab,
                payload: serializeTabState(activeTab, tabStates[activeTab]) as FormDataConvertible,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleUpdate = () => {
        if (!loadedScenario) {
            return;
        }
        setProcessing(true);
        router.put(
            route('simulation-scenarios.update', loadedScenario.id),
            {
                name: scenarioName.trim() || loadedScenario.name,
                payload: serializeTabState(activeTab, tabStates[activeTab]) as FormDataConvertible,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleDelete = () => {
        if (!loadedScenario) {
            return;
        }
        if (!window.confirm(`Eliminare lo scenario "${loadedScenario.name}"?`)) {
            return;
        }
        setProcessing(true);
        router.delete(route('simulation-scenarios.destroy', loadedScenario.id), {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setLoadedScenarioId(null);
                onLoadedScenarioChange(null);
                setScenarioName('');
            },
        });
    };

    return (
        <CardBox className="border-emerald-200 bg-emerald-50/40 dark:border-emerald-800 dark:bg-emerald-950/20">
            <div className="space-y-3">
                <div>
                    <h3 className="text-base font-semibold text-gray-900 dark:text-white">I tuoi scenari salvati</h3>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Salva i parametri della scheda <strong>{TAB_LABELS[activeTab]}</strong> per riprenderli in seguito.
                        Gli scenari sono legati alla household attiva.
                    </p>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                    <div className="min-w-0 flex-1 sm:max-w-xs">
                        <label htmlFor="scenario-name" className="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                            Nome scenario
                        </label>
                        <input
                            id="scenario-name"
                            type="text"
                            value={scenarioName}
                            onChange={(e) => setScenarioName(e.target.value)}
                            placeholder="es. Piano pensione 2030"
                            className="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            maxLength={120}
                        />
                    </div>

                    <div className="min-w-0 flex-1 sm:max-w-xs">
                        <label htmlFor="scenario-select" className="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                            Apri scenario ({TAB_LABELS[activeTab]})
                        </label>
                        <select
                            id="scenario-select"
                            value={loadedScenarioId ?? ''}
                            onChange={(e) => handleSelectScenario(e.target.value)}
                            className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-3 pr-8 text-sm text-gray-800 shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        >
                            <option value="">— Seleziona —</option>
                            {scenariosForTab.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={handleSave}
                            disabled={processing || !scenarioName.trim()}
                            className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Salva scenario
                        </button>
                        {loadedScenario && loadedScenario.tab === activeTab && (
                            <button
                                type="button"
                                onClick={handleUpdate}
                                disabled={processing}
                                className="rounded-lg border border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-50 dark:text-emerald-400 dark:hover:bg-emerald-900/30"
                            >
                                Salva modifiche
                            </button>
                        )}
                        {loadedScenario && (
                            <button
                                type="button"
                                onClick={handleDelete}
                                disabled={processing}
                                className={clsx(
                                    'rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700',
                                    'hover:bg-rose-50 disabled:opacity-50 dark:border-rose-700 dark:text-rose-400 dark:hover:bg-rose-900/30',
                                )}
                            >
                                Elimina
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </CardBox>
    );
}
