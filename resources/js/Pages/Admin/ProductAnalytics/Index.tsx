import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';

type CountRow = { feature_key: string; event_count: number };
type KindRow = { event_kind: string; event_count: number };
type EventRow = { event_name: string; feature_key: string; event_count: number };
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
    bottlenecks: EventRow[];
    daily_trend: TrendRow[];
    backlog_hints: HintRow[];
}

interface Props {
    analytics: AnalyticsPayload;
    days: number;
}

const DAY_OPTIONS = [7, 14, 30, 90] as const;

const KIND_LABELS: Record<string, string> = {
    used: 'Utilizzo',
    friction: 'Frizione',
    error: 'Errori',
    performance: 'Performance',
};

function EmptyState({ message }: { message: string }) {
    return <p className="text-sm text-slate-500 dark:text-slate-400">{message}</p>;
}

function MetricTable({
    headers,
    rows,
    empty,
}: {
    headers: string[];
    rows: Array<Array<string | number>>;
    empty: string;
}) {
    if (rows.length === 0) {
        return <EmptyState message={empty} />;
    }

    return (
        <div className="overflow-x-auto">
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
                    {rows.map((row, idx) => (
                        <tr
                            key={`${row[0]}-${idx}`}
                            className="border-b border-slate-100 dark:border-slate-800"
                        >
                            {row.map((cell, cellIdx) => (
                                <td key={cellIdx} className="px-2 py-2 text-slate-800 dark:text-slate-100">
                                    {cell}
                                </td>
                            ))}
                        </tr>
                    ))}
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

export default function Index({ analytics, days }: Props) {
    const setDays = (value: number) => {
        router.get(
            route('admin.product-analytics.index'),
            { days: value },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Product analytics"
                    subtitle="Utilizzo, frizione e colli di bottiglia (aggregati, senza dati personali)"
                    backLink={route('dashboard')}
                />
            }
        >
            <Head title="Product analytics" />
            <PageContent maxWidth="5xl">
                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <span className="text-sm text-slate-600 dark:text-slate-300">Periodo:</span>
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
                    <span className="ms-auto text-xs text-slate-500">
                        {analytics.from} → {analytics.to}
                    </span>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Section title="Per tipo di evento" subtitle="Conteggi aggregati nel periodo">
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
                        <MetricTable
                            headers={['Feature', 'Eventi']}
                            rows={analytics.top_features.map((r) => [r.feature_key, r.event_count])}
                            empty="Nessun utilizzo registrato."
                        />
                    </Section>

                    <Section title="Frizione UX" subtitle="Abbandoni form, errori di validazione, retry">
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

                    <Section title="Errori" subtitle="Eventi feature.error / exception">
                        <MetricTable
                            headers={['Evento', 'Feature', 'Eventi']}
                            rows={analytics.errors.map((r) => [
                                r.event_name,
                                r.feature_key,
                                r.event_count,
                            ])}
                            empty="Nessun errore aggregato."
                        />
                    </Section>

                    <Section
                        title="Colli di bottiglia"
                        subtitle="Richieste lente (solo nome rotta + bucket ms)"
                    >
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
