import { useState, useCallback } from 'react';
import { arrayMove } from '@dnd-kit/sortable';
import { router } from '@inertiajs/react';
import { DashboardLayoutConfig, WidgetConfig, WidgetId, WidgetSize } from '@/types/dashboard';

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
export function useDashboardLayout(initialConfig: DashboardLayoutConfig) {
    const [config, setConfig] = useState<DashboardLayoutConfig>(() => ({
        widgets: [...initialConfig.widgets].sort((a, b) => a.position - b.position),
    }));
    const [isEditing, setIsEditing] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);

    /** Ordina i widget per posizione. */
    const sortedWidgets = [...config.widgets].sort((a, b) => a.position - b.position);

    /** Attiva/disattiva la modalità di modifica. */
    const toggleEditing = useCallback(() => {
        setIsEditing((prev) => !prev);
        setSaveError(null);
    }, []);

    /** Annulla le modifiche ripristinando la configurazione iniziale. */
    const cancelEditing = useCallback(() => {
        setConfig({
            widgets: [...initialConfig.widgets].sort((a, b) => a.position - b.position),
        });
        setIsEditing(false);
        setSaveError(null);
    }, [initialConfig]);

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
    const saveLayout = useCallback(async () => {
        setIsSaving(true);
        setSaveError(null);

        // Normalizza le posizioni prima del salvataggio
        const normalizedWidgets: WidgetConfig[] = sortedWidgets.map((w, i) => ({
            ...w,
            position: i,
        }));

        return new Promise<void>((resolve, reject) => {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            router.post(
                route('dashboard.layout.store'),
                { config: { widgets: normalizedWidgets } } as any,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setIsEditing(false);
                        setIsSaving(false);
                        resolve();
                    },
                    onError: (errors) => {
                        const msg =
                            Object.values(errors)[0]?.toString() ??
                            'Errore durante il salvataggio del layout.';
                        setSaveError(msg);
                        setIsSaving(false);
                        reject(new Error(msg));
                    },
                }
            );
        });
    }, [sortedWidgets]);

    /** Ripristina il layout di default. */
    const resetLayout = useCallback(() => {
        router.delete(route('dashboard.layout.reset'), {
            preserveScroll: true,
            onSuccess: () => {
                setIsEditing(false);
            },
        });
    }, []);

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
        moveWidget,
        saveLayout,
        resetLayout,
    };
}
