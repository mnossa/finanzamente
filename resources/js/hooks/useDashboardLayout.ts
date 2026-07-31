import { useState, useCallback } from 'react';
import axios from 'axios';
import { WIDGET_MAP } from '@/constants/widgetRegistry';
import { DashboardLayoutConfig, KnownWidgetId, WidgetConfig, WidgetId, WidgetSize } from '@/types/dashboard';

/** Reorder array item without @dnd-kit (keeps vendor-dnd off the dashboard critical path). */
function arrayMove<T>(items: T[], fromIndex: number, toIndex: number): T[] {
    const next = [...items];
    const [removed] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, removed);

    return next;
}

interface UseDashboardLayoutOptions {
    boardId?: number | null;
    canEdit?: boolean;
    startEditing?: boolean;
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
    addWidget: (id: KnownWidgetId) => void;
    setWidgetSize: (id: WidgetId, size: WidgetSize) => void;
    setWidgetRuntimeParam: (id: WidgetId, key: string, value: string) => void;
    persistWidgetRuntimeParam: (id: WidgetId, key: string, value: string) => Promise<void>;
    moveWidget: (oldIndex: number, newIndex: number) => void;
    saveLayout: () => Promise<void>;
    hideWidgetsAndSave: (widgetIds: WidgetId[]) => Promise<void>;
    resetLayout: () => void;
}

function boardParams(boardId?: number | null): Record<string, number> {
    return boardId ? { board: boardId } : {};
}

/**
 * Hook per gestire il layout personalizzabile della dashboard.
 */
export function useDashboardLayout(
    initialConfig: DashboardLayoutConfig,
    options: UseDashboardLayoutOptions = {},
): UseDashboardLayoutReturn {
    const { boardId = null, canEdit = true, startEditing = false } = options;
    const [config, setConfig] = useState<DashboardLayoutConfig>(() => ({
        widgets: [...initialConfig.widgets].sort((a, b) => a.position - b.position),
    }));
    const [snapshot, setSnapshot] = useState<DashboardLayoutConfig | null>(() =>
        canEdit && startEditing
            ? { widgets: [...initialConfig.widgets].sort((a, b) => a.position - b.position) }
            : null,
    );
    const [isEditing, setIsEditing] = useState(() => canEdit && startEditing);
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);

    const sortedWidgets = [...config.widgets].sort((a, b) => a.position - b.position);

    const toggleEditing = useCallback(() => {
        if (!canEdit) {
            return;
        }
        setIsEditing((prev) => {
            if (!prev) {
                setConfig((current) => {
                    setSnapshot({ widgets: [...current.widgets] });
                    return current;
                });
            }
            return !prev;
        });
        setSaveError(null);
    }, [canEdit]);

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

    const toggleWidgetVisibility = useCallback((id: WidgetId) => {
        setConfig((prev) => ({
            widgets: prev.widgets.map((w) =>
                w.id === id ? { ...w, visible: !w.visible } : w
            ),
        }));
    }, []);

    const addWidget = useCallback((id: KnownWidgetId) => {
        const definition = WIDGET_MAP[id];
        if (!definition) {
            return;
        }

        setConfig((prev) => {
            const existing = prev.widgets.find((widget) => widget.id === id);
            if (existing) {
                return {
                    widgets: prev.widgets.map((widget) =>
                        widget.id === id ? { ...widget, visible: true } : widget
                    ),
                };
            }

            const nextWidgets = [
                ...prev.widgets,
                {
                    id,
                    visible: true,
                    position: prev.widgets.length,
                    size: definition.defaultSize,
                } satisfies WidgetConfig,
            ].map((widget, index) => ({ ...widget, position: index }));

            return { widgets: nextWidgets };
        });
    }, []);

    const setWidgetSize = useCallback((id: WidgetId, size: WidgetSize) => {
        setConfig((prev) => ({
            widgets: prev.widgets.map((w) => (w.id === id ? { ...w, size } : w)),
        }));
    }, []);

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

    const persistWidgetRuntimeParam = useCallback(async (id: WidgetId, key: string, value: string): Promise<void> => {
        if (!canEdit) {
            return;
        }

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
            await axios.post(route('dashboard.layout.store'), {
                config: { widgets: nextWidgets },
                ...boardParams(boardId),
            });
        } catch (error) {
            const errors = (error as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data?.errors;
            const msg = errors
                ? Object.values(errors)[0]?.[0] ?? 'Errore durante il salvataggio del layout.'
                : 'Errore durante il salvataggio del layout.';
            setSaveError(msg);
            throw new Error(msg);
        }
    }, [sortedWidgets, boardId, canEdit]);

    const moveWidget = useCallback((oldIndex: number, newIndex: number) => {
        setConfig((prev) => {
            const sorted = [...prev.widgets].sort((a, b) => a.position - b.position);
            const reordered = arrayMove(sorted, oldIndex, newIndex).map(
                (w, i) => ({ ...w, position: i })
            );
            return { widgets: reordered };
        });
    }, []);

    const saveLayout = useCallback(async (): Promise<void> => {
        if (!canEdit) {
            return;
        }

        setIsSaving(true);
        setSaveError(null);

        const normalizedWidgets: WidgetConfig[] = sortedWidgets.map((w, i) => ({
            ...w,
            position: i,
        }));

        return axios
            .post(route('dashboard.layout.store'), {
                config: { widgets: normalizedWidgets },
                ...boardParams(boardId),
            })
            .then(() => {
                setSnapshot(null);
                setIsEditing(false);
                setIsSaving(false);
            })
            .catch((error) => {
                const message = error?.response?.data?.message;
                const errors = error?.response?.data?.errors;
                const msg = message
                    ?? (errors ? (Object.values(errors)[0] as string[])?.[0] : null)
                    ?? 'Errore durante il salvataggio del layout.';
                setSaveError(msg);
                setIsSaving(false);
                throw new Error(msg);
            });
    }, [sortedWidgets, boardId, canEdit]);

    const resetLayout = useCallback(() => {
        if (!canEdit) {
            return;
        }

        axios
            .delete(route('dashboard.layout.reset', boardParams(boardId)))
            .then((response) => {
                const widgets: WidgetConfig[] = response.data?.config?.widgets ?? [];
                setConfig({
                    widgets: [...widgets].sort((a, b) => a.position - b.position),
                });
                setSnapshot(null);
                setIsEditing(false);
                setSaveError(null);
            })
            .catch(() => {
                setSaveError('Errore durante il ripristino del layout.');
            });
    }, [boardId, canEdit]);

    const hideWidgetsAndSave = useCallback(async (widgetIds: WidgetId[]): Promise<void> => {
        if (!canEdit || widgetIds.length === 0) {
            return;
        }

        const idSet = new Set(widgetIds);
        const nextWidgets = sortedWidgets
            .map((widget) => (idSet.has(widget.id) ? { ...widget, visible: false } : widget))
            .map((widget, index) => ({ ...widget, position: index }));

        setConfig({ widgets: nextWidgets });
        setSaveError(null);

        try {
            await axios.post(route('dashboard.layout.store'), {
                config: { widgets: nextWidgets },
                ...boardParams(boardId),
            });
        } catch {
            setSaveError('Errore durante il salvataggio del layout.');
            throw new Error('Errore durante il salvataggio del layout.');
        }
    }, [sortedWidgets, boardId, canEdit]);

    return {
        config,
        sortedWidgets,
        isEditing: canEdit && isEditing,
        isSaving,
        saveError,
        toggleEditing,
        cancelEditing,
        toggleWidgetVisibility,
        addWidget,
        setWidgetSize,
        setWidgetRuntimeParam,
        persistWidgetRuntimeParam,
        moveWidget,
        saveLayout,
        hideWidgetsAndSave,
        resetLayout,
    };
}
