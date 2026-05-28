import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import PageContent from '@/Components/PageContent';
import CardBox from '@/Components/CardBox';
import LinkButton from '@/Components/LinkButton';
import { Head } from '@inertiajs/react';

interface Pac {
    id: number;
    amount: number;
    currency_code: string;
    frequency: string;
    start_date: string;
    end_date: string | null;
    last_executed_at: string | null;
    status: string;
    notes: string | null;
    asset: { id: number; name: string; symbol: string; isin: string | null };
    account: { id: number; name: string } | null;
}

export default function InvestmentPacIndex({ pacs }: { pacs: Pac[] }) {
    return (
        <AuthenticatedLayout
            header={<PageHeader title="PAC Investimenti" actions={<LinkButton href={route('investment-pacs.create')}>Nuovo PAC</LinkButton>} />}
        >
            <Head title="PAC Investimenti" />
            <PageContent>
                <CardBox className="p-4">
                    <div className="space-y-3">
                        {pacs.length === 0 && <p className="text-sm text-gray-500">Nessun PAC configurato.</p>}
                        {pacs.map((pac) => (
                            <div key={pac.id} className="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                <p className="font-semibold text-gray-900 dark:text-white">{pac.asset.name} ({pac.asset.isin ?? 'ISIN n/d'})</p>
                                <p className="text-sm text-gray-600 dark:text-gray-300">{pac.amount.toFixed(2)} {pac.currency_code} - {pac.frequency}</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400">Inizio {pac.start_date} {pac.end_date ? `- Fine ${pac.end_date}` : ''}</p>
                            </div>
                        ))}
                    </div>
                </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
