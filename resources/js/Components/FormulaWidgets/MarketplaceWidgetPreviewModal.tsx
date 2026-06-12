import Modal from '@/Components/Modal';
import FormulaWidgetPreviewPanel from '@/Components/FormulaWidgets/FormulaWidgetPreviewPanel';
import { useMarketplaceWidgetPreview } from '@/hooks/useMarketplaceWidgetPreview';
import type { FormulaWidgetSummary } from '@/types/formulaWidget';

interface MarketplaceWidgetPreviewModalProps {
    widget: FormulaWidgetSummary | null;
    installLabel: string;
    onClose: () => void;
    onInstall: (widget: FormulaWidgetSummary) => void;
    installing?: boolean;
}

export default function MarketplaceWidgetPreviewModal({
    widget,
    installLabel,
    onClose,
    onInstall,
    installing = false,
}: MarketplaceWidgetPreviewModalProps) {
    const { status, payload, errors } = useMarketplaceWidgetPreview(widget);

    return (
        <Modal show={widget !== null} maxWidth="2xl" onClose={onClose}>
            {widget && (
                <div className="p-4 sm:p-6">
                    <div className="mb-4">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{widget.name}</h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Anteprima con i tuoi dati. Installa solo se ti serve in dashboard.
                        </p>
                    </div>

                    <FormulaWidgetPreviewPanel
                        status={status}
                        payload={payload}
                        errors={errors}
                        title="Anteprima live"
                        className="static top-auto border-0 bg-transparent p-0 dark:bg-transparent"
                    />

                    <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            onClick={onClose}
                            disabled={installing}
                            className="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 sm:w-auto sm:py-2"
                        >
                            Annulla
                        </button>
                        <button
                            type="button"
                            onClick={() => onInstall(widget)}
                            disabled={installing || status === 'loading'}
                            className="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-700 disabled:opacity-60 sm:w-auto sm:py-2"
                        >
                            {installing ? 'Installazione…' : installLabel}
                        </button>
                    </div>
                </div>
            )}
        </Modal>
    );
}
