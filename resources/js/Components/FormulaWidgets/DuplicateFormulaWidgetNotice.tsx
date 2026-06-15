import LinkButton from '@/Components/LinkButton';
import type { FormulaWidgetSummary } from '@/types/formulaWidget';
import { formulaWidgetDisplayLabel } from '@/utils/formulaWidgetDisplayLabels';
import { router } from '@inertiajs/react';

interface DuplicateFormulaWidgetNoticeProps {
    widget: FormulaWidgetSummary;
    message?: string;
    variant?: 'own' | 'marketplace';
    onDismiss?: () => void;
}

export default function DuplicateFormulaWidgetNotice({
    widget,
    message,
    variant = 'own',
    onDismiss,
}: DuplicateFormulaWidgetNoticeProps) {
    const displayLabel = formulaWidgetDisplayLabel(widget.display_type);
    const isMarketplace = variant === 'marketplace';

    function useExistingWidget(): void {
        if (isMarketplace) {
            if (widget.template_slug) {
                router.post(route('formula-marketplace.install-template', widget.template_slug), { pin: true }, {
                    onFinish: () => onDismiss?.(),
                });

                return;
            }

            router.post(route('formula-marketplace.install-widget', widget.id), undefined, {
                onFinish: () => onDismiss?.(),
            });

            return;
        }

        router.post(route('formula-widgets.pin', widget.id), undefined, {
            onFinish: () => onDismiss?.(),
        });
    }

    const title = isMarketplace ? 'Widget già in galleria' : 'Widget già presente';
    const defaultMessage = isMarketplace
        ? 'Esiste già un widget condiviso con la stessa formula e configurazione grafica.'
        : 'Hai già un widget con la stessa formula e la stessa configurazione grafica.';
    const primaryLabel = isMarketplace
        ? widget.installed
            ? 'Vai alla galleria'
            : widget.template_slug
                ? 'Installa template'
                : 'Installa widget condiviso'
        : 'Usa quello esistente in dashboard';

    return (
        <div
            className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-100"
            role="alert"
        >
            <p className="font-medium">{title}</p>
            <p className="mt-1">{message ?? defaultMessage}</p>
            <p className="mt-2 text-amber-900/90 dark:text-amber-100/90">
                {isMarketplace ? 'In galleria' : 'Esistente'}:{' '}
                <span className="font-semibold">{widget.name}</span> · {displayLabel}
                {widget.is_official_template ? ' · Ufficiale' : null}
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
                {isMarketplace && widget.installed ? (
                    <LinkButton href={route('formula-marketplace.index')} size="sm">
                        Vai alla galleria
                    </LinkButton>
                ) : (
                    <button
                        type="button"
                        onClick={useExistingWidget}
                        className="rounded-lg bg-amber-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-800 dark:bg-amber-600 dark:hover:bg-amber-500"
                    >
                        {primaryLabel}
                    </button>
                )}
                <LinkButton
                    href={isMarketplace ? route('formula-marketplace.index') : route('formula-widgets.index')}
                    variant="secondary"
                    size="sm"
                >
                    {isMarketplace ? 'Apri galleria' : 'Vai ai miei widget'}
                </LinkButton>
                {onDismiss ? (
                    <button
                        type="button"
                        onClick={onDismiss}
                        className="rounded-lg px-3 py-1.5 text-xs font-medium text-amber-900 hover:bg-amber-100 dark:text-amber-100 dark:hover:bg-amber-900/40"
                    >
                        Chiudi
                    </button>
                ) : null}
            </div>
        </div>
    );
}
