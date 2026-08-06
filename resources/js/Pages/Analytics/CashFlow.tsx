import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import CashFlowLineChart from '@/Components/Charts/CashFlowLineChart';
import { CashFlowDataPoint } from '@/Components/Charts/CashFlowChart';
import { Head } from '@inertiajs/react';

interface Props {
    cashFlowData: CashFlowDataPoint[];
}

export default function CashFlow({ cashFlowData }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Panoramica cashflow"
                    subtitle="Storico entrate, uscite e risparmio mensile"
                    backLink={route('dashboard')}
                />
            }
        >
            <Head title="Panoramica cashflow" />
            <PageContent maxWidth="4xl">
                <div className="overflow-hidden rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800 sm:p-6">
                    <CashFlowLineChart data={cashFlowData} />
                </div>
            </PageContent>
        </AuthenticatedLayout>
    );
}
