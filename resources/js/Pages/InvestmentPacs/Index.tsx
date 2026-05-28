import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import PageContent from '@/Components/PageContent';
import CardBox from '@/Components/CardBox';
import LinkButton from '@/Components/LinkButton';
import { Head } from '@inertiajs/react';
import { formatCurrency } from '@/utils/format';

interface Pac {
    id: number;
    amount: number;
    adjust_for_inflation: boolean;
    inflation_rate_annual: number | null;
    currency_code: string;
    frequency: string;
    start_date: string;
    end_date: string | null;
    last_executed_at: string | null;
    status: string;
    asset: { id: number; name: string; symbol: string; isin: string | null };
    account: { id: number; name: string } | null;
}

export default function InvestmentPacIndex({ pacs }: { pacs: Pac[] }) {
    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="PAC — Piani di accumulo"
                    actions={<LinkButton href={route('investment-pacs.create')}>Nuovo PAC</LinkButton>}
                />
            }
        >
            <Head title="PAC Investimenti" />
            <PageContent maxWidth="3xl">
                <CardBox className="p-4">
                    <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        Versamenti mensili automatici su ETF, fondi o altri strumenti. Configura importo, eventuale rivalutazione inflazione e date.
                    </p>
                    <div className="space-y-3">
                        {pacs.length === 0 && (
                            <p className="text-sm text-gray-500 dark:text-gray-400">Nessun PAC configurato. Crea il primo piano di accumulo.</p>
                        )}
                        {pacs.map((pac) => (
                            <div key={pac.id} className="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                <p className="font-semibold text-gray-900 dark:text-white">
                                    {pac.asset.name} ({pac.asset.isin ?? 'ISIN n/d'})
                                </p>
                                <p className="text-sm text-gray-600 dark:text-gray-300">
                                    {formatCurrency(pac.amount, pac.currency_code)} / mese
                                    {pac.adjust_for_inflation && pac.inflation_rate_annual !== null && (
                                        <span className="ml-2 text-emerald-700 dark:text-emerald-400">
                                            +{pac.inflation_rate_annual.toFixed(1)}% annuo (inflazione)
                                        </span>
                                    )}
                                </p>
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Inizio {pac.start_date}
                                    {pac.end_date ? ` — Fine ${pac.end_date}` : ''}
                                    {pac.last_executed_at ? ` · Ultimo versamento ${pac.last_executed_at}` : ''}
                                    {pac.account ? ` · Conto ${pac.account.name}` : ''}
                                </p>
                            </div>
                        ))}
                    </div>
                </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
