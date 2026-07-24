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

    const title = isMarketplace ? 'Già in galleria' : 'Già pronto';
    const defaultMessage = isMarketplace
        ? 'Esiste già un widget condiviso con la stessa formula e lo stesso aspetto. Puoi riusarlo subito.'
        : 'Hai già questo widget (stessa formula e stesso aspetto). Puoi usarlo subito in dashboard.';
    const primaryLabel = isMarketplace
        ? widget.installed
            ? 'Apri galleria'
            : widget.template_slug
                ? 'Installa template'
                : 'Installa widget condiviso'
        : 'Apri in dashboard';

    return (
        <div
            className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950 dark:border-emerald-800/60 dark:bg-emerald-950/30 dark:text-emerald-100"
            role="status"
        >
            <p className="font-medium">{title}</p>
            <p className="mt-1">{message ?? defaultMessage}</p>
            <p className="mt-2 text-emerald-900/90 dark:text-emerald-100/90">
                {isMarketplace ? 'In galleria' : 'Esistente'}:{' '}
                <span className="font-semibold">{widget.name}</span> · {displayLabel}
                {widget.is_official_template ? ' · Ufficiale' : null}
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
                {isMarketplace && widget.installed ? (
                    <LinkButton href={route('formula-marketplace.index')} size="sm">
                        Apri galleria
                    </LinkButton>
                ) : (
                    <button
                        type="button"
                        onClick={useExistingWidget}
                        className="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                    >
                        {primaryLabel}
                    </button>
                )}
                <LinkButton
                    href={isMarketplace ? route('formula-marketplace.index') : route('formula-widgets.index')}
                    variant="secondary"
                    size="sm"
                >
                    {isMarketplace ? 'Vai alla galleria' : 'Vai ai miei widget'}
                </LinkButton>
                {onDismiss ? (
                    <button
                        type="button"
                        onClick={onDismiss}
                        className="rounded-lg px-3 py-1.5 text-xs font-medium text-emerald-900 hover:bg-emerald-100 dark:text-emerald-100 dark:hover:bg-emerald-900/40"
                    >
                        Chiudi
                    </button>
                ) : null}
            </div>
        </div>
    );
}
