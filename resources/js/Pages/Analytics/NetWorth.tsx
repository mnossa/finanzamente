import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import NetWorthChart, { NetWorthDataPoint } from '@/Components/Charts/NetWorthChart';
import { Head } from '@inertiajs/react';

interface Props {
    netWorthData: NetWorthDataPoint[];
    summary: {
        start: string;
        end: string;
        growth_pct: number | null;
    };
}

export default function NetWorth({ netWorthData, summary }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Patrimonio nel tempo"
                    subtitle="Andamento completo dal primo movimento registrato"
                    backLink={route('dashboard')}
                />
            }
        >
            <Head title="Patrimonio nel tempo" />
            <PageContent maxWidth="4xl">
                {summary.growth_pct !== null && netWorthData.length > 0 && (
                    <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        Dal {summary.start} al {summary.end}:{' '}
                        <span className={summary.growth_pct >= 0 ? 'text-emerald-600' : 'text-red-500'}>
                            {summary.growth_pct >= 0 ? '+' : ''}{summary.growth_pct}%
                        </span>
                    </p>
                )}
                <div className="overflow-hidden rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800 sm:p-6">
                    <NetWorthChart
                        data={netWorthData}
                        title="Patrimonio nel tempo"
                        subtitle="Liquidità + investimenti collegati al ledger (costo di carico)"
                    />
                </div>
            </PageContent>
        </AuthenticatedLayout>
    );
}
