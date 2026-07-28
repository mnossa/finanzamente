import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';
import { useMemo, useState } from 'react';

type CountRow = { feature_key: string; event_count: number };
type KindRow = { event_kind: string; event_count: number };
type EventRow = { event_name: string; feature_key: string; event_count: number };
type ErrorDetailRow = {
    event_name: string;
    feature_key: string;
    event_count: number;
    dimensions: Record<string, string>;
};
type TrendRow = { day: string; event_kind: string; event_count: number };
type HintRow = {
    feature_key: string;
    used: number;
    friction: number;
    errors: number;
    score: number;
};

interface AnalyticsPayload {
    from: string;
    to: string;
    top_features: CountRow[];
    by_kind: KindRow[];
    friction: EventRow[];
    errors: EventRow[];
    error_details: ErrorDetailRow[];
    bottlenecks: EventRow[];
    daily_trend: TrendRow[];
    backlog_hints: HintRow[];
}

interface Props {
    analytics: AnalyticsPayload;
    days: number;
    tools: {
        pulse_url: string;
        pulse_enabled: boolean;
        telescope_enabled: boolean;
        telescope_url: string;
    };
}

const DAY_OPTIONS = [7, 14, 30, 90] as const;

const KIND_LABELS: Record<string, string> = {
    used: 'Utilizzo',
    friction: 'Frizione',
    error: 'Errori',
    performance: 'Performance',
};

const DIMENSION_LABELS: Record<string, string> = {
    exception: 'Eccezione',
    route: 'Rotta',
    status: 'Status',
    form: 'Form',
    type: 'Tipo',
};

function EmptyState({ message }: { message: string }) {
    return <p className="text-sm text-slate-500 dark:text-slate-400">{message}</p>;
}

function formatDimensionSummary(dimensions: Record<string, string>): string {
    const parts = Object.entries(dimensions)
        .filter(([, v]) => v !== '')
        .map(([k, v]) => `${DIMENSION_LABELS[k] ?? k}: ${v}`);
    return parts.length > 0 ? parts.join(' · ') : 'Senza dimensioni';
}

function MetricCards({
    rows,
    empty,
    onRowClick,
    activeKey,
}: {
    rows: Array<{ key: string; title: string; subtitle?: string; value: string | number; clickable?: boolean }>;
    empty: string;
    onRowClick?: (key: string) => void;
    activeKey?: string | null;
}) {
    if (rows.length === 0) {
        return <EmptyState message={empty} />;
    }

    return (
        <ul className="space-y-2 md:hidden">
            {rows.map((row) => {
                const isActive = activeKey === row.key;
                const interactive = Boolean(row.clickable && onRowClick);
                const Tag = interactive ? 'button' : 'div';
                return (
                    <li key={row.key}>
                        <Tag
                            type={interactive ? 'button' : undefined}
                            onClick={interactive ? () => onRowClick?.(row.key) : undefined}
                            className={clsx(
                                'flex w-full items-start justify-between gap-3 rounded-lg border px-3 py-2.5 text-left',
                                isActive
                                    ? 'border-emerald-400 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950/40'
                                    : 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/40',
                                interactive && 'hover:border-emerald-300 dark:hover:border-emerald-600',
                            )}
                        >
                            <div className="min-w-0 flex-1">
                                <p className="break-words text-sm font-medium text-slate-900 dark:text-white">
                                    {row.title}
                                </p>
                                {row.subtitle && (
                                    <p className="mt-0.5 break-words text-xs text-slate-500 dark:text-slate-400">
                                        {row.subtitle}
                                    </p>
                                )}
                            </div>
                            <span className="shrink-0 tabular-nums text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {row.value}
                            </span>
                        </Tag>
                    </li>
                );
            })}
        </ul>
    );
}

function MetricTable({
    headers,
    rows,
    empty,
    onRowClick,
    activeKey,
    rowKeys,
}: {
    headers: string[];
    rows: Array<Array<string | number>>;
    empty: string;
    onRowClick?: (key: string) => void;
    activeKey?: string | null;
    rowKeys?: string[];
}) {
    if (rows.length === 0) {
        return <EmptyState message={empty} />;
    }

    return (
        <div className="hidden overflow-x-auto md:block">
            <table className="min-w-full text-left text-sm">
                <thead>
                    <tr className="border-b border-slate-200 text-slate-500 dark:border-slate-700">
                        {headers.map((h) => (
                            <th key={h} className="px-2 py-2 font-medium">
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, idx) => {
                        const key = rowKeys?.[idx] ?? `${row[0]}-${idx}`;
                        const isActive = activeKey === key;
                        const interactive = Boolean(onRowClick);
                        return (
                            <tr
                                key={key}
                                onClick={interactive ? () => onRowClick?.(key) : undefined}
                                onKeyDown={
                                    interactive
                                        ? (e) => {
                                              if (e.key === 'Enter' || e.key === ' ') {
                                                  e.preventDefault();
                                                  onRowClick?.(key);
                                              }
                                          }
                                        : undefined
                                }
                                tabIndex={interactive ? 0 : undefined}
                                role={interactive ? 'button' : undefined}
                                className={clsx(
                                    'border-b border-slate-100 dark:border-slate-800',
                                    interactive && 'cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-900/50',
                                    isActive && 'bg-emerald-50 dark:bg-emerald-950/30',
                                )}
                            >
                                {row.map((cell, cellIdx) => (
                                    <td
                                        key={cellIdx}
                                        className="max-w-[14rem] break-words px-2 py-2 text-slate-800 dark:text-slate-100"
                                    >
                                        {cell}
                                    </td>
                                ))}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

function Section({
    title,
    subtitle,
    children,
}: {
    title: string;
    subtitle?: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800 sm:p-6">
            <h2 className="text-base font-semibold text-slate-900 dark:text-white">{title}</h2>
            {subtitle && (
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{subtitle}</p>
            )}
            <div className="mt-4">{children}</div>
        </section>
    );
}

function errorRowKey(eventName: string, featureKey: string): string {
    return `${eventName}::${featureKey}`;
}

export default function Index({ analytics, days, tools }: Props) {
    const [selectedErrorKey, setSelectedErrorKey] = useState<string | null>(null);

    const setDays = (value: number) => {
        router.get(
            route('admin.product-analytics.index'),
            { days: value },
            { preserveState: true, replace: true },
        );
    };

    const selectedErrorDetails = useMemo(() => {
        if (!selectedErrorKey) {
            return [];
        }
        return (analytics.error_details ?? []).filter(
            (detail) => errorRowKey(detail.event_name, detail.feature_key) === selectedErrorKey,
        );
    }, [analytics.error_details, selectedErrorKey]);

    const toggleError = (key: string) => {
        setSelectedErrorKey((prev) => (prev === key ? null : key));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Product analytics"
                    subtitle="Utilizzo, frizione, errori server e colli di bottiglia (aggregati senza PII)"
                    backLink={route('dashboard')}
                />
            }
        >
            <Head title="Product analytics" />
            <PageContent maxWidth="5xl">
                <div className="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                    <p className="font-medium">Tool di debug runtime</p>
                    <p className="mt-1 text-emerald-900/80 dark:text-emerald-100/80">
                        Questa pagina è per trend di prodotto. Per eccezioni live, query lente e job
                        usa Pulse (e Telescope in local/staging).
                    </p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {tools.pulse_enabled && (
                            <a
                                href={tools.pulse_url}
                                className="rounded-lg bg-emerald-700 px-3 py-1.5 font-medium text-white hover:bg-emerald-800"
                            >
                                Apri Laravel Pulse
                            </a>
                        )}
                        {tools.telescope_enabled && (
                            <a
                                href={tools.telescope_url}
                                className="rounded-lg bg-slate-800 px-3 py-1.5 font-medium text-white hover:bg-slate-900 dark:bg-slate-200 dark:text-slate-900"
                            >
                                Apri Telescope
                            </a>
                        )}
                    </div>
                </div>

                <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <span className="text-sm text-slate-600 dark:text-slate-300">Periodo:</span>
                    <div className="flex flex-wrap gap-2">
                        {DAY_OPTIONS.map((option) => (
                            <button
                                key={option}
                                type="button"
                                onClick={() => setDays(option)}
                                className={clsx(
                                    'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                                    days === option
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600',
                                )}
                            >
                                {option} giorni
                            </button>
                        ))}
                    </div>
                    <span className="text-xs text-slate-500 sm:ms-auto">
                        {analytics.from} → {analytics.to}
                    </span>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Section title="Per tipo di evento" subtitle="Conteggi aggregati nel periodo">
                        <MetricCards
                            empty="Nessun evento nel periodo."
                            rows={analytics.by_kind.map((r) => ({
                                key: r.event_kind,
                                title: KIND_LABELS[r.event_kind] ?? r.event_kind,
                                value: r.event_count,
                            }))}
                        />
                        <MetricTable
                            headers={['Tipo', 'Eventi']}
                            rows={analytics.by_kind.map((r) => [
                                KIND_LABELS[r.event_kind] ?? r.event_kind,
                                r.event_count,
                            ])}
                            empty="Nessun evento nel periodo."
                        />
                    </Section>

                    <Section title="Feature più usate" subtitle="Solo eventi di utilizzo">
                        <MetricCards
                            empty="Nessun utilizzo registrato."
                            rows={analytics.top_features.map((r) => ({
                                key: r.feature_key,
                                title: r.feature_key,
                                value: r.event_count,
                            }))}
                        />
                        <MetricTable
                            headers={['Feature', 'Eventi']}
                            rows={analytics.top_features.map((r) => [r.feature_key, r.event_count])}
                            empty="Nessun utilizzo registrato."
                        />
                    </Section>

                    <Section title="Frizione UX" subtitle="Abbandoni form, errori di validazione, retry">
                        <MetricCards
                            empty="Nessuna frizione rilevata."
                            rows={analytics.friction.map((r) => ({
                                key: `${r.event_name}-${r.feature_key}`,
                                title: r.event_name,
                                subtitle: r.feature_key,
                                value: r.event_count,
                            }))}
                        />
                        <MetricTable
                            headers={['Evento', 'Feature', 'Eventi']}
                            rows={analytics.friction.map((r) => [
                                r.event_name,
                                r.feature_key,
                                r.event_count,
                            ])}
                            empty="Nessuna frizione rilevata."
                        />
                    </Section>

                    <Section
                        title="Errori"
                        subtitle="Tocca una riga per vedere eccezione, rotta e status (senza PII)"
                    >
                        <MetricCards
                            empty="Nessun errore aggregato."
                            activeKey={selectedErrorKey}
                            onRowClick={toggleError}
                            rows={analytics.errors.map((r) => ({
                                key: errorRowKey(r.event_name, r.feature_key),
                                title: r.event_name,
                                subtitle: r.feature_key,
                                value: r.event_count,
                                clickable: true,
                            }))}
                        />
                        <MetricTable
                            headers={['Evento', 'Feature', 'Eventi']}
                            rows={analytics.errors.map((r) => [
                                r.event_name,
                                r.feature_key,
                                r.event_count,
                            ])}
                            rowKeys={analytics.errors.map((r) =>
                                errorRowKey(r.event_name, r.feature_key),
                            )}
                            empty="Nessun errore aggregato."
                            onRowClick={toggleError}
                            activeKey={selectedErrorKey}
                        />

                        {selectedErrorKey && (
                            <div className="mt-3 rounded-lg border border-emerald-200 bg-emerald-50/80 p-3 dark:border-emerald-800 dark:bg-emerald-950/30">
                                <div className="mb-2 flex items-center justify-between gap-2">
                                    <p className="text-sm font-medium text-emerald-950 dark:text-emerald-100">
                                        Dettaglio errori
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => setSelectedErrorKey(null)}
                                        className="text-xs font-medium text-emerald-800 hover:underline dark:text-emerald-200"
                                    >
                                        Chiudi
                                    </button>
                                </div>
                                {selectedErrorDetails.length === 0 ? (
                                    <EmptyState message="Nessuna dimensione disponibile per questo errore." />
                                ) : (
                                    <ul className="space-y-2">
                                        {selectedErrorDetails.map((detail, idx) => (
                                            <li
                                                key={`${detail.event_name}-${idx}`}
                                                className="rounded-md border border-emerald-100 bg-white px-3 py-2 text-sm dark:border-emerald-900 dark:bg-slate-900"
                                            >
                                                <p className="break-words text-slate-800 dark:text-slate-100">
                                                    {formatDimensionSummary(detail.dimensions)}
                                                </p>
                                                <p className="mt-1 tabular-nums text-xs text-slate-500 dark:text-slate-400">
                                                    {detail.event_count} eventi
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}
                    </Section>

                    <Section
                        title="Colli di bottiglia"
                        subtitle="Richieste lente (solo nome rotta + bucket ms)"
                    >
                        <MetricCards
                            empty="Nessun collo di bottiglia nel periodo."
                            rows={analytics.bottlenecks.map((r) => ({
                                key: `${r.event_name}-${r.feature_key}`,
                                title: r.event_name,
                                subtitle: r.feature_key,
                                value: r.event_count,
                            }))}
                        />
                        <MetricTable
                            headers={['Evento', 'Feature', 'Eventi']}
                            rows={analytics.bottlenecks.map((r) => [
                                r.event_name,
                                r.feature_key,
                                r.event_count,
                            ])}
                            empty="Nessun collo di bottiglia nel periodo."
                        />
                    </Section>

                    <Section
                        title="Priorità backlog"
                        subtitle="Score = (frizione×2 + errori×3) / √utilizzo"
                    >
                        <MetricCards
                            empty="Nessun segnale di priorità (servono frizione o errori)."
                            rows={analytics.backlog_hints.map((r) => ({
                                key: r.feature_key,
                                title: r.feature_key,
                                subtitle: `Uso ${r.used} · Frizione ${r.friction} · Errori ${r.errors}`,
                                value: r.score,
                            }))}
                        />
                        <MetricTable
                            headers={['Feature', 'Uso', 'Frizione', 'Errori', 'Score']}
                            rows={analytics.backlog_hints.map((r) => [
                                r.feature_key,
                                r.used,
                                r.friction,
                                r.errors,
                                r.score,
                            ])}
                            empty="Nessun segnale di priorità (servono frizione o errori)."
                        />
                    </Section>
                </div>

                <div className="mt-4">
                    <Section title="Trend giornaliero" subtitle="Eventi per giorno e tipo">
                        <MetricCards
                            empty="Nessun trend disponibile."
                            rows={analytics.daily_trend.map((r, idx) => ({
                                key: `${r.day}-${r.event_kind}-${idx}`,
                                title: r.day,
                                subtitle: KIND_LABELS[r.event_kind] ?? r.event_kind,
                                value: r.event_count,
                            }))}
                        />
                        <MetricTable
                            headers={['Giorno', 'Tipo', 'Eventi']}
                            rows={analytics.daily_trend.map((r) => [
                                r.day,
                                KIND_LABELS[r.event_kind] ?? r.event_kind,
                                r.event_count,
                            ])}
                            empty="Nessun trend disponibile."
                        />
                    </Section>
                </div>

                <p className="mt-6 text-xs text-slate-500 dark:text-slate-400">
                    Privacy: solo aggregati giornalieri. Nessun user_id, email, IP, importo o testo
                    libero. Retention tipica 90 giorni. Dettagli in docs/product-analytics.md.
                </p>
            </PageContent>
        </AuthenticatedLayout>
    );
}
