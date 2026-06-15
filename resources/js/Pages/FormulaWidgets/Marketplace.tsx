import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import CardBox from '@/Components/CardBox';
import DuplicateFormulaWidgetNotice from '@/Components/FormulaWidgets/DuplicateFormulaWidgetNotice';
import MarketplaceWidgetPreviewModal from '@/Components/FormulaWidgets/MarketplaceWidgetPreviewModal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { IndexPageHeaderActions, IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import { formulaWidgetDisplayLabel } from '@/utils/formulaWidgetDisplayLabels';
import { Head, router, usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';
import { useMemo, useState } from 'react';
import type { FormulaWidgetSummary } from '@/types/formulaWidget';

interface ChartTypeMeta {
    label: string;
    description: string;
}

interface MarketplaceProps {
    officialTemplates: FormulaWidgetSummary[];
    communityWidgets: FormulaWidgetSummary[];
    chartTypes: Record<string, ChartTypeMeta>;
}

function marketplaceSearchHaystack(widget: FormulaWidgetSummary, chartTypes: Record<string, ChartTypeMeta>): string {
    return [
        widget.name,
        widget.description,
        chartTypes[widget.display_type]?.label,
        chartTypes[widget.display_type]?.description,
        widget.financial_variable?.name,
        widget.financial_variable?.formula_string,
        formulaWidgetDisplayLabel(widget.display_type),
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
}

function filterMarketplaceWidgets(
    widgets: FormulaWidgetSummary[],
    chartTypes: Record<string, ChartTypeMeta>,
    search: string,
    displayType: string,
): FormulaWidgetSummary[] {
    const query = search.trim().toLowerCase();

    return widgets.filter((widget) => {
        if (displayType !== '' && widget.display_type !== displayType) {
            return false;
        }

        if (query === '') {
            return true;
        }

        return marketplaceSearchHaystack(widget, chartTypes).includes(query);
    });
}

function MarketplaceCard({
    widget,
    onPreview,
    onUninstall,
    previewLabel,
}: {
    widget: FormulaWidgetSummary;
    onPreview: () => void;
    onUninstall: () => void;
    previewLabel: string;
}) {
    return (
        <CardBox className="flex h-full flex-col gap-3 p-4 shadow-sm">
            <div className="min-w-0 flex-1">
                <div className="flex items-start justify-between gap-2">
                    <h3 className="font-semibold text-gray-900 dark:text-white">{widget.name}</h3>
                    {widget.is_official_template && (
                        <span className="shrink-0 rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">
                            Ufficiale
                        </span>
                    )}
                </div>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {formulaWidgetDisplayLabel(widget.display_type)}
                </p>
                {widget.description ? (
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{widget.description}</p>
                ) : null}
                {widget.financial_variable?.formula_string && (
                    <p className="mt-2 hidden font-mono text-xs text-gray-500 dark:text-gray-400 sm:block">
                        {widget.financial_variable.formula_string}
                    </p>
                )}
            </div>
            {widget.installed ? (
                <button
                    type="button"
                    onClick={onUninstall}
                    className="w-full rounded-lg border border-red-200 bg-white px-3 py-2.5 text-sm font-semibold text-red-600 transition-colors hover:bg-red-50 dark:border-red-900/50 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-900/20 sm:py-2"
                >
                    Rimuovi
                </button>
            ) : (
                <button
                    type="button"
                    onClick={onPreview}
                    className="w-full rounded-lg bg-primary-600 px-3 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-primary-700 sm:py-2"
                >
                    {previewLabel}
                </button>
            )}
        </CardBox>
    );
}

export default function Marketplace({
    officialTemplates,
    communityWidgets,
    chartTypes,
}: MarketplaceProps) {
    const { flash, errors } = usePage<PageProps>().props;
    const [previewWidget, setPreviewWidget] = useState<FormulaWidgetSummary | null>(null);
    const [installLabel, setInstallLabel] = useState('Installa nella dashboard');
    const [installing, setInstalling] = useState(false);
    const [search, setSearch] = useState('');
    const [displayTypeFilter, setDisplayTypeFilter] = useState('');
    const [duplicateDismissed, setDuplicateDismissed] = useState(false);

    const filteredOfficial = useMemo(
        () => filterMarketplaceWidgets(officialTemplates, chartTypes, search, displayTypeFilter),
        [officialTemplates, chartTypes, search, displayTypeFilter],
    );
    const filteredCommunity = useMemo(
        () => filterMarketplaceWidgets(communityWidgets, chartTypes, search, displayTypeFilter),
        [communityWidgets, chartTypes, search, displayTypeFilter],
    );

    const openPreview = (widget: FormulaWidgetSummary, label: string) => {
        setInstallLabel(label);
        setPreviewWidget(widget);
    };

    const closePreview = () => {
        if (installing) {
            return;
        }

        setPreviewWidget(null);
    };

    const installFromPreview = (widget: FormulaWidgetSummary) => {
        setInstalling(true);

        const finishInstall = (page: { props: PageProps }) => {
            setInstalling(false);

            if (page.props.flash?.duplicateWidget || page.props.flash?.success) {
                setPreviewWidget(null);
            }
        };

        if (widget.template_slug) {
            router.post(
                route('formula-marketplace.install-template', widget.template_slug),
                { pin: true },
                {
                    onFinish: () => setInstalling(false),
                    onSuccess: finishInstall,
                },
            );

            return;
        }

        router.post(route('formula-marketplace.install-widget', widget.id), undefined, {
            onFinish: () => setInstalling(false),
            onSuccess: finishInstall,
        });
    };

    const duplicateWidget = flash?.duplicateWidget;
    const duplicateMarketplaceWidget = flash?.duplicateMarketplaceWidget;
    const duplicateErrorMessage = typeof errors?.widget === 'string' ? errors.widget : undefined;
    const showOwnDuplicateNotice = duplicateWidget != null && !duplicateDismissed;
    const showMarketplaceDuplicateNotice = duplicateMarketplaceWidget != null && !duplicateDismissed;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Galleria widget"
                    mobileTitle="Galleria"
                    backLink={route('formula-widgets.index')}
                    actions={
                        <IndexPageHeaderActions>
                            <LinkButton href={route('formula-widgets.index')} variant="secondary">
                                I miei widget
                            </LinkButton>
                        </IndexPageHeaderActions>
                    }
                />
            }
        >
            <Head title="Galleria widget" />

            <PageContent maxWidth="7xl">
                <IndexPageMobileToolbar equalWidth={false}>
                    <LinkButton href={route('formula-widgets.index')} variant="secondary" size="sm">
                        I miei widget
                    </LinkButton>
                </IndexPageMobileToolbar>

                {showOwnDuplicateNotice ? (
                    <div className="mb-6">
                        <DuplicateFormulaWidgetNotice
                            widget={duplicateWidget}
                            message={duplicateErrorMessage}
                            onDismiss={() => setDuplicateDismissed(true)}
                        />
                    </div>
                ) : null}
                {showMarketplaceDuplicateNotice ? (
                    <div className="mb-6">
                        <DuplicateFormulaWidgetNotice
                            widget={duplicateMarketplaceWidget}
                            variant="marketplace"
                            onDismiss={() => setDuplicateDismissed(true)}
                        />
                    </div>
                ) : null}

                <section className="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h2 className="text-sm font-semibold text-gray-900 dark:text-white">Cerca nella galleria</h2>
                    <div className="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem]">
                        <div>
                            <InputLabel htmlFor="marketplace-search" value="Nome o descrizione" />
                            <TextInput
                                id="marketplace-search"
                                className="mt-1 block w-full"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Es. saldo, KPI, andamento…"
                            />
                        </div>
                        <div>
                            <InputLabel htmlFor="marketplace-type" value="Tipologia" />
                            <select
                                id="marketplace-type"
                                className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                                value={displayTypeFilter}
                                onChange={(event) => setDisplayTypeFilter(event.target.value)}
                            >
                                <option value="">Tutte le tipologie</option>
                                {Object.entries(chartTypes).map(([key, meta]) => (
                                    <option key={key} value={key}>
                                        {meta.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </section>

                <section className="mb-8 sm:mb-10">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Template ufficiali</h2>
                    <p className="mt-1 hidden text-sm text-gray-600 dark:text-gray-400 sm:block">
                        Widget curati dal team Finanzamente, pronti per la dashboard.
                    </p>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400 sm:hidden">
                        Template pronti da aggiungere alla dashboard.
                    </p>
                    {filteredOfficial.length === 0 ? (
                        <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
                            Nessun template corrisponde ai filtri selezionati.
                        </p>
                    ) : (
                        <div className="mt-3 grid gap-3 sm:mt-4 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                            {filteredOfficial.map((widget) => (
                                <MarketplaceCard
                                    key={widget.id}
                                    widget={widget}
                                    previewLabel="Anteprima"
                                    onPreview={() => {
                                        if (widget.installed) return;
                                        openPreview(widget, 'Installa template');
                                    }}
                                    onUninstall={() => {
                                        if (!widget.template_slug || !widget.installed) return;
                                        router.delete(route('formula-marketplace.uninstall-template', widget.template_slug));
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </section>

                {communityWidgets.length > 0 && (
                    <section>
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Community</h2>
                        <p className="mt-1 hidden text-sm text-gray-600 dark:text-gray-400 sm:block">
                            Widget condivisi da altri utenti.
                        </p>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400 sm:hidden">
                            Widget condivisi dalla community.
                        </p>
                        {filteredCommunity.length === 0 ? (
                            <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                Nessun widget community corrisponde ai filtri selezionati.
                            </p>
                        ) : (
                            <div className="mt-3 grid gap-3 sm:mt-4 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                                {filteredCommunity.map((widget) => (
                                    <MarketplaceCard
                                        key={widget.id}
                                        widget={widget}
                                        previewLabel="Anteprima"
                                        onPreview={() => {
                                            if (widget.installed) return;
                                            openPreview(widget, 'Installa widget');
                                        }}
                                        onUninstall={() => {
                                            if (!widget.installed) return;
                                            router.delete(route('formula-marketplace.uninstall-widget', widget.id));
                                        }}
                                    />
                                ))}
                            </div>
                        )}
                    </section>
                )}
            </PageContent>

            <MarketplaceWidgetPreviewModal
                widget={previewWidget}
                installLabel={installLabel}
                onClose={closePreview}
                onInstall={installFromPreview}
                installing={installing}
            />
        </AuthenticatedLayout>
    );
}
