import { useState, useCallback } from 'react';
import axios from 'axios';
import { DashboardLayoutConfig, WidgetConfig, WidgetId, WidgetSize } from '@/types/dashboard';

/** Reorder array item without @dnd-kit (keeps vendor-dnd off the dashboard critical path). */
function arrayMove<T>(items: T[], fromIndex: number, toIndex: number): T[] {
    const next = [...items];
    const [removed] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, removed);

    return next;
}

interface UseDashboardLayoutReturn {
    config: DashboardLayoutConfig;
    sortedWidgets: WidgetConfig[];
    isEditing: boolean;
    isSaving: boolean;
    saveError: string | null;
    toggleEditing: () => void;
    cancelEditing: () => void;
    toggleWidgetVisibility: (id: WidgetId) => void;
    setWidgetSize: (id: WidgetId, size: WidgetSize) => void;
    setWidgetRuntimeParam: (id: WidgetId, key: string, value: string) => void;
    persistWidgetRuntimeParam: (id: WidgetId, key: string, value: string) => Promise<void>;
    moveWidget: (oldIndex: number, newIndex: number) => void;
    saveLayout: () => Promise<void>;
    hideWidgetsAndSave: (widgetIds: WidgetId[]) => Promise<void>;
    resetLayout: () => void;
}

/**
 * Hook per gestire il layout personalizzabile della dashboard.
 *
 * Gestisce:
 * - la modalità di modifica (editing mode)
 * - la visibilità dei widget
 * - il riordinamento tramite drag & drop
 * - il resize dei widget
 * - il salvataggio e il ripristino del layout
 */
export function useDashboardLayout(initialConfig: DashboardLayoutConfig): UseDashboardLayoutReturn {
    const [config, setConfig] = useState<DashboardLayoutConfig>(() => ({
        widgets: [...initialConfig.widgets].sort((a, b) => a.position - b.position),
    }));
    const [snapshot, setSnapshot] = useState<DashboardLayoutConfig | null>(null);
    const [isEditing, setIsEditing] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);

    /** Ordina i widget per posizione. */
    const sortedWidgets = [...config.widgets].sort((a, b) => a.position - b.position);

    /** Attiva/disattiva la modalità di modifica. */
    const toggleEditing = useCallback(() => {
        setIsEditing((prev) => {
            if (!prev) {
                // Salva snapshot prima di entrare in modifica
                setSnapshot((s) => s ?? null);
                setConfig((current) => {
                    setSnapshot({ widgets: [...current.widgets] });
                    return current;
                });
            }
            return !prev;
        });
        setSaveError(null);
    }, []);

    /** Annulla le modifiche ripristinando la configurazione al momento dell'apertura. */
    const cancelEditing = useCallback(() => {
        if (snapshot) {
            setConfig({
                widgets: [...snapshot.widgets].sort((a, b) => a.position - b.position),
            });
        }
        setSnapshot(null);
        setIsEditing(false);
        setSaveError(null);
    }, [snapshot]);

    /** Aggiorna la visibilità di un widget. */
    const toggleWidgetVisibility = useCallback((id: WidgetId) => {
        setConfig((prev) => ({
            widgets: prev.widgets.map((w) =>
                w.id === id ? { ...w, visible: !w.visible } : w
            ),
        }));
    }, []);

    /** Aggiorna la dimensione di un widget. */
    const setWidgetSize = useCallback((id: WidgetId, size: WidgetSize) => {
        setConfig((prev) => ({
            widgets: prev.widgets.map((w) => (w.id === id ? { ...w, size } : w)),
        }));
    }, []);

    /** Aggiorna un parametro runtime del widget (es. conto selezionato). */
    const setWidgetRuntimeParam = useCallback((id: WidgetId, key: string, value: string) => {
        setConfig((prev) => ({
            widgets: prev.widgets.map((widget) => {
                if (widget.id !== id) {
                    return widget;
                }

                return {
                    ...widget,
                    runtime_params: {
                        ...(widget.runtime_params ?? {}),
                        [key]: value,
                    },
                };
            }),
        }));
    }, []);

    /** Aggiorna e salva subito un parametro runtime del widget. */
    const persistWidgetRuntimeParam = useCallback(async (id: WidgetId, key: string, value: string): Promise<void> => {
        const nextWidgets = sortedWidgets.map((widget) => {
            if (widget.id !== id) {
                return widget;
            }

            return {
                ...widget,
                runtime_params: {
                    ...(widget.runtime_params ?? {}),
                    [key]: value,
                },
            };
        }).map((widget, index) => ({
            ...widget,
            position: index,
        }));

        setConfig({ widgets: nextWidgets });
        setSaveError(null);

        try {
            await axios.post(route('dashboard.layout.store'), { config: { widgets: nextWidgets } });
        } catch (error) {
            const errors = (error as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data?.errors;
            const msg = errors
                ? Object.values(errors)[0]?.[0] ?? 'Errore durante il salvataggio del layout.'
                : 'Errore durante il salvataggio del layout.';
            setSaveError(msg);
            throw new Error(msg);
        }
    }, [sortedWidgets]);

    /**
     * Sposta un widget dalla posizione `oldIndex` alla posizione `newIndex`
     * e aggiorna i campi `position` di tutti i widget.
     */
    const moveWidget = useCallback((oldIndex: number, newIndex: number) => {
        setConfig((prev) => {
            const sorted = [...prev.widgets].sort((a, b) => a.position - b.position);
            const reordered = arrayMove(sorted, oldIndex, newIndex).map(
                (w, i) => ({ ...w, position: i })
            );
            return { widgets: reordered };
        });
    }, []);

    /** Salva la configurazione corrente sul server. */
    const saveLayout = useCallback(async (): Promise<void> => {
        setIsSaving(true);
        setSaveError(null);

        // Normalizza le posizioni prima del salvataggio
        const normalizedWidgets: WidgetConfig[] = sortedWidgets.map((w, i) => ({
            ...w,
            position: i,
        }));

        return axios
            .post(route('dashboard.layout.store'), { config: { widgets: normalizedWidgets } })
            .then(() => {
                setSnapshot(null);
                setIsEditing(false);
                setIsSaving(false);
            })
            .catch((error) => {
                const errors = error?.response?.data?.errors;
                const msg = errors
                    ? (Object.values(errors)[0] as string[])?.[0] ?? 'Errore durante il salvataggio del layout.'
                    : 'Errore durante il salvataggio del layout.';
                setSaveError(msg);
                setIsSaving(false);
                throw new Error(msg);
            });
    }, [sortedWidgets]);

    /** Ripristina il layout di default. */
    const resetLayout = useCallback(() => {
        axios
            .delete(route('dashboard.layout.reset'))
            .then((response) => {
                const widgets: WidgetConfig[] = response.data?.config?.widgets ?? [];
                if (widgets.length > 0) {
                    setConfig({ widgets: [...widgets].sort((a, b) => a.position - b.position) });
                }
                setIsEditing(false);
            })
            .catch(() => {
                setSaveError('Errore durante il ripristino del layout.');
            });
    }, []);

    /**
     * Nasconde uno o più widget e salva subito il layout.
     * Esegue rollback locale in caso di errore.
     */
    const hideWidgetsAndSave = useCallback(async (widgetIds: WidgetId[]): Promise<void> => {
        const ids = new Set(widgetIds);
        const currentWidgets = [...config.widgets].sort((a, b) => a.position - b.position);
        const nextWidgets = currentWidgets.map((w) =>
            ids.has(w.id) ? { ...w, visible: false } : w
        );
        const hasChanges = nextWidgets.some((widget, index) => widget.visible !== currentWidgets[index].visible);

        if (!hasChanges) {
            return;
        }

        setIsSaving(true);
        setSaveError(null);
        setConfig({ widgets: nextWidgets });

        const normalizedWidgets: WidgetConfig[] = nextWidgets.map((w, i) => ({
            ...w,
            position: i,
        }));

        return axios
            .post(route('dashboard.layout.store'), { config: { widgets: normalizedWidgets } })
            .then(() => {
                setSnapshot(null);
                setIsEditing(false);
                setIsSaving(false);
            })
            .catch((error) => {
                const errors = error?.response?.data?.errors;
                const msg = errors
                    ? (Object.values(errors)[0] as string[])?.[0] ?? 'Errore durante il salvataggio del layout.'
                    : 'Errore durante il salvataggio del layout.';
                setConfig({ widgets: currentWidgets });
                setSaveError(msg);
                setIsSaving(false);
                throw new Error(msg);
            });
    }, [config.widgets]);

    return {
        config,
        sortedWidgets,
        isEditing,
        isSaving,
        saveError,
        toggleEditing,
        cancelEditing,
        toggleWidgetVisibility,
        setWidgetSize,
        setWidgetRuntimeParam,
        persistWidgetRuntimeParam,
        moveWidget,
        saveLayout,
        hideWidgetsAndSave,
        resetLayout,
    };
}
