import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import CardBox from '@/Components/CardBox';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';
import type { FormulaWidgetSummary } from '@/types/formulaWidget';

interface MarketplaceProps {
    officialTemplates: FormulaWidgetSummary[];
    communityWidgets: FormulaWidgetSummary[];
}

const DISPLAY_LABELS: Record<string, string> = {
    kpi: 'KPI',
    line: 'Linea',
    area: 'Area',
    bar: 'Barre verticali',
    horizontal_bar: 'Barre orizzontali',
    stacked_bar: 'Barre impilate',
    pie: 'Torta',
    treemap: 'Treemap',
    progress: 'Avanzamento',
};

function MarketplaceCard({
    widget,
    onInstall,
    onUninstall,
    installLabel,
}: {
    widget: FormulaWidgetSummary;
    onInstall: () => void;
    onUninstall: () => void;
    installLabel: string;
}) {
    return (
        <CardBox className="flex h-full flex-col gap-3 p-4 shadow-sm">
            <div className="flex-1">
                <div className="flex items-start justify-between gap-2">
                    <h3 className="font-semibold text-gray-900 dark:text-white">{widget.name}</h3>
                    {widget.is_official_template && (
                        <span className="shrink-0 rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">
                            Ufficiale
                        </span>
                    )}
                </div>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {DISPLAY_LABELS[widget.display_type] ?? widget.display_type}
                </p>
                {widget.financial_variable?.formula_string && (
                    <p className="mt-2 font-mono text-xs text-gray-500 dark:text-gray-400">
                        {widget.financial_variable.formula_string}
                    </p>
                )}
            </div>
            {widget.installed ? (
                <button
                    type="button"
                    onClick={onUninstall}
                    className="w-full rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-600 transition-colors hover:bg-red-50 dark:border-red-900/50 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20"
                >
                    Rimuovi
                </button>
            ) : (
                <button
                    type="button"
                    onClick={onInstall}
                    className="w-full rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-700"
                >
                    {installLabel}
                </button>
            )}
        </CardBox>
    );
}

export default function Marketplace({ officialTemplates, communityWidgets }: MarketplaceProps) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Galleria widget"
                    backLink={route('formula-widgets.index')}
                    actions={
                        <LinkButton href={route('formula-widgets.index')} variant="secondary">
                            I miei widget
                        </LinkButton>
                    }
                />
            }
        >
            <Head title="Galleria widget" />

            <PageContent maxWidth="7xl">
                <section className="mb-10">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Template ufficiali</h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Widget curati dal team Finanzamente, pronti per la dashboard.
                    </p>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {officialTemplates.map((widget) => (
                            <MarketplaceCard
                                key={widget.id}
                                widget={widget}
                                installLabel="Installa template"
                                onInstall={() => {
                                    if (!widget.template_slug || widget.installed) return;
                                    router.post(route('formula-marketplace.install-template', widget.template_slug), {
                                        pin: true,
                                    });
                                }}
                                onUninstall={() => {
                                    if (!widget.template_slug || !widget.installed) return;
                                    router.delete(route('formula-marketplace.uninstall-template', widget.template_slug));
                                }}
                            />
                        ))}
                    </div>
                </section>

                {communityWidgets.length > 0 && (
                    <section>
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Community</h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Widget condivisi da altri utenti.
                        </p>
                        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {communityWidgets.map((widget) => (
                                <MarketplaceCard
                                    key={widget.id}
                                    widget={widget}
                                    installLabel="Installa widget"
                                    onInstall={() => {
                                        if (widget.installed) return;
                                        router.post(route('formula-marketplace.install-widget', widget.id));
                                    }}
                                    onUninstall={() => {
                                        if (!widget.installed) return;
                                        router.delete(route('formula-marketplace.uninstall-widget', widget.id));
                                    }}
                                />
                            ))}
                        </div>
                    </section>
                )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
