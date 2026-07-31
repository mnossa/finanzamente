import Modal from '@/Components/Modal';
import { WIDGET_REGISTRY } from '@/constants/widgetRegistry';
import { KnownWidgetId, WidgetId } from '@/types/dashboard';
import clsx from 'clsx';

interface AddDashboardWidgetModalProps {
    show: boolean;
    onClose: () => void;
    presentWidgetIds: WidgetId[];
    onAdd: (id: KnownWidgetId) => void;
    isModuleLocked?: (moduleId: string) => boolean;
}

/**
 * Catalogo built-in mancanti dal layout corrente (edit mode).
 * I formula widget restano sul marketplace / pin.
 */
export default function AddDashboardWidgetModal({
    show,
    onClose,
    presentWidgetIds,
    onAdd,
    isModuleLocked,
}: AddDashboardWidgetModalProps) {
    const present = new Set(presentWidgetIds);
    const available = WIDGET_REGISTRY.filter((widget) => !present.has(widget.id));

    return (
        <Modal show={show} onClose={onClose} maxWidth="lg">
            <div className="p-4 sm:p-6">
                <div className="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                            Aggiungi widget
                        </h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Scegli un widget built-in da aggiungere a questa dashboard.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        aria-label="Chiudi"
                    >
                        ✕
                    </button>
                </div>

                {available.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-gray-200 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Tutti i widget disponibili sono già in questa dashboard. Usa l&apos;icona occhio per mostrare quelli nascosti.
                    </p>
                ) : (
                    <ul className="max-h-[60vh] space-y-2 overflow-y-auto">
                        {available.map((widget) => {
                            const locked = Boolean(
                                widget.requiresModule && isModuleLocked?.(widget.requiresModule),
                            );

                            return (
                                <li key={widget.id}>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            onAdd(widget.id);
                                            onClose();
                                        }}
                                        className={clsx(
                                            'flex w-full items-start gap-3 rounded-xl border px-3 py-3 text-left transition',
                                            'border-gray-200 bg-white hover:border-emerald-400 hover:bg-emerald-50',
                                            'dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-600 dark:hover:bg-emerald-900/20',
                                        )}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-semibold text-gray-900 dark:text-white">
                                                    {widget.title}
                                                </span>
                                                {locked && (
                                                    <span className="inline-flex items-center rounded bg-accent-100 px-1.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-accent-700 dark:bg-accent-900/40 dark:text-accent-200">
                                                        Pro
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                                                {widget.description}
                                            </p>
                                        </div>
                                        <span className="shrink-0 self-center text-sm font-medium text-emerald-600 dark:text-emerald-400">
                                            Aggiungi
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </Modal>
    );
}
