import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import PageContent from '@/Components/PageContent';
import CardBox from '@/Components/CardBox';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { Head, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import { FormEventHandler } from 'react';

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface Asset {
    id: number;
    name: string;
    symbol: string;
    isin: string | null;
    currency_code: string;
}

interface Pac {
    id: number;
    account_id: number | null;
    investment_asset_id: number;
    amount: number;
    fees: number | null;
    adjust_for_inflation: boolean;
    inflation_rate_annual: number | null;
    currency_code: string;
    frequency: string;
    start_date: string;
    end_date: string | null;
    status: 'active' | 'paused';
    notes: string | null;
}

export default function InvestmentPacEdit({ accounts, assets, pac }: { accounts: Account[]; assets: Asset[]; pac: Pac }) {
    const { data, setData, put, processing, errors } = useForm({
        account_id: pac.account_id ? String(pac.account_id) : '',
        investment_asset_id: String(pac.investment_asset_id),
        amount: String(pac.amount),
        fees: pac.fees !== null ? String(pac.fees) : '',
        adjust_for_inflation: pac.adjust_for_inflation,
        inflation_rate_annual: pac.inflation_rate_annual !== null ? String(pac.inflation_rate_annual) : '2',
        currency_code: pac.currency_code,
        frequency: pac.frequency,
        start_date: pac.start_date,
        end_date: pac.end_date ?? '',
        status: pac.status,
        notes: pac.notes ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('investment-pacs.update', pac.id));
    };

    return (
        <AuthenticatedLayout header={<PageHeader title="Modifica PAC" backLink={route('investment-pacs.show', pac.id)} />}>
            <Head title="Modifica PAC" />
            <PageContent maxWidth="3xl">
                <CardBox className="p-4 sm:p-5">
                    <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                        <div>
                            <InputLabel value="Strumento (ISIN)" />
                            <select value={data.investment_asset_id} onChange={(e) => setData('investment_asset_id', e.target.value)} className="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                <option value="">Seleziona</option>
                                {assets.map((a) => <option key={a.id} value={a.id}>{a.name} ({a.isin ?? 'ISIN n/d'})</option>)}
                            </select>
                            <InputError message={errors.investment_asset_id} className="mt-1" />
                        </div>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div>
                                <InputLabel value="Importo mensile" />
                                <TextInput type="number" min="0.01" step="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} className="mt-1 block w-full" />
                                <InputError message={errors.amount} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Commissioni per acquisto (opz.)" />
                                <TextInput type="number" min="0" step="0.01" value={data.fees} onChange={(e) => setData('fees', e.target.value)} className="mt-1 block w-full" placeholder="0.00" />
                                <InputError message={errors.fees} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Valuta" />
                                <TextInput value={data.currency_code} onChange={(e) => setData('currency_code', e.target.value.toUpperCase())} className="mt-1 block w-full" />
                                <InputError message={errors.currency_code} className="mt-1" />
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Stato" />
                                <select value={data.status} onChange={(e) => setData('status', e.target.value as 'active' | 'paused')} className="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                    <option value="active">Attivo</option>
                                    <option value="paused">In pausa</option>
                                </select>
                                <InputError message={errors.status} className="mt-1" />
                            </div>
                        </div>
                        <div className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                            <label className="flex cursor-pointer items-start gap-3">
                                <input
                                    type="checkbox"
                                    checked={data.adjust_for_inflation}
                                    onChange={(e) => setData('adjust_for_inflation', e.target.checked)}
                                    className="mt-1 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <span>
                                    <span className="block text-sm font-medium text-gray-900 dark:text-white">Adeguamento annuo all&apos;inflazione</span>
                                </span>
                            </label>
                            {data.adjust_for_inflation && (
                                <div className="mt-3">
                                    <InputLabel value="Rivalutazione annua (%)" />
                                    <TextInput
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.1"
                                        value={data.inflation_rate_annual}
                                        onChange={(e) => setData('inflation_rate_annual', e.target.value)}
                                        className="mt-1 block w-full max-w-xs"
                                    />
                                    <InputError message={errors.inflation_rate_annual} className="mt-1" />
                                </div>
                            )}
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Data inizio" />
                                <TextInput type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} className="mt-1 block w-full" />
                                <InputError message={errors.start_date} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Data fine (opz.)" />
                                <TextInput type="date" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} className="mt-1 block w-full" />
                                <InputError message={errors.end_date} className="mt-1" />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Conto (opz.)" />
                            <select value={data.account_id} onChange={(e) => setData('account_id', e.target.value)} className="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                <option value="">Nessuno</option>
                                {accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}
                            </select>
                            <InputError message={errors.account_id} className="mt-1" />
                            {!data.account_id && (
                                <p className="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/40 dark:bg-amber-900/20 dark:text-amber-100">
                                    Senza conto associato gli acquisti del PAC non scalano il saldo del conto. Restano visibili tra gli investimenti e nella ripartizione spese.
                                </p>
                            )}
                        </div>
                        <div>
                            <InputLabel value="Note (opz.)" />
                            <TextInput value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="mt-1 block w-full" />
                            <InputError message={errors.notes} className="mt-1" />
                        </div>
                        <FormActionsBar>
                            <PrimaryButton
                                type="submit"
                                disabled={processing}
                                className={clsx(processing && 'opacity-60')}
                            >
                                Salva modifiche
                            </PrimaryButton>
                        </FormActionsBar>
                    </form>
                </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
