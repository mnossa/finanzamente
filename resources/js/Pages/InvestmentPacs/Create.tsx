import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import PageContent from '@/Components/PageContent';
import CardBox from '@/Components/CardBox';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, useForm } from '@inertiajs/react';

interface Account { id: number; name: string; currency_code: string }
interface Asset { id: number; name: string; symbol: string; isin: string | null; currency_code: string }

export default function InvestmentPacCreate({ accounts, assets }: { accounts: Account[]; assets: Asset[] }) {
    const { data, setData, post, processing, errors } = useForm({
        account_id: '',
        investment_asset_id: '',
        amount: '',
        currency_code: 'EUR',
        frequency: 'monthly',
        start_date: new Date().toISOString().slice(0, 10),
        end_date: '',
        notes: '',
    });

    return (
        <AuthenticatedLayout header={<PageHeader title="Nuovo PAC" backLink={route('investment-pacs.index')} />}>
            <Head title="Nuovo PAC" />
            <PageContent maxWidth="2xl">
                <CardBox className="p-4 space-y-4">
                    <div>
                        <InputLabel value="Asset (ISIN)" />
                        <select value={data.investment_asset_id} onChange={(e) => setData('investment_asset_id', e.target.value)} className="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800">
                            <option value="">Seleziona</option>
                            {assets.map((a) => <option key={a.id} value={a.id}>{a.name} ({a.isin ?? 'ISIN n/d'})</option>)}
                        </select>
                        <InputError message={errors.investment_asset_id} className="mt-1" />
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Importo" />
                            <TextInput type="number" min="0.01" step="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} className="mt-1 block w-full" />
                            <InputError message={errors.amount} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Valuta" />
                            <TextInput value={data.currency_code} onChange={(e) => setData('currency_code', e.target.value.toUpperCase())} className="mt-1 block w-full" />
                        </div>
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Data inizio" />
                            <TextInput type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} className="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel value="Data fine (opz.)" />
                            <TextInput type="date" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} className="mt-1 block w-full" />
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Conto (opz.)" />
                        <select value={data.account_id} onChange={(e) => setData('account_id', e.target.value)} className="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800">
                            <option value="">Nessuno</option>
                            {accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                        </select>
                    </div>
                    <PrimaryButton disabled={processing} onClick={() => post(route('investment-pacs.store'))}>Crea PAC</PrimaryButton>
                </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
