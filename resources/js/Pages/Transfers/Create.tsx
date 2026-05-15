import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LinkButton from '@/Components/LinkButton';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { transfers } from '@/utils/analytics';
import clsx from 'clsx';
import { useMemo } from 'react';
import PageHeader from '@/Components/PageHeader';

interface Account {
    id: number;
    name: string;
    currency_code: string;
    current_balance: number;
}

interface CreateProps {
    accounts: Account[];
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

export default function Create({ accounts }: CreateProps) {
    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        source_account_id: '',
        destination_account_id: '',
        amount: '',
        exchange_rate: '',
        fee: '',
        date: today,
        description: '',
        is_private: false,
    });

    const sourceAccount = useMemo(
        () => accounts.find((a) => a.id === Number(data.source_account_id)),
        [accounts, data.source_account_id]
    );

    const destAccount = useMemo(
        () => accounts.find((a) => a.id === Number(data.destination_account_id)),
        [accounts, data.destination_account_id]
    );

    const isCrossCurrency = sourceAccount && destAccount && sourceAccount.currency_code !== destAccount.currency_code;

    const estimatedDestAmount = useMemo(() => {
        if (!data.amount || !sourceAccount || !destAccount) return null;
        const amount = parseFloat(data.amount);
        if (isNaN(amount)) return null;

        if (isCrossCurrency && data.exchange_rate) {
            const rate = parseFloat(data.exchange_rate);
            if (!isNaN(rate)) {
                return amount * rate;
            }
        } else if (!isCrossCurrency) {
            return amount;
        }
        return null;
    }, [data.amount, data.exchange_rate, sourceAccount, destAccount, isCrossCurrency]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('transfers.store'), {
            onSuccess: () => transfers.created(),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Nuovo Trasferimento"
                    backLink={route('transfers.index')}
                />
            }
        >
            <Head title="Nuovo Trasferimento" />

            <PageContent maxWidth="2xl">
                    <SectionCard className="space-y-4">
                        <header className="hidden sm:block space-y-2">
                            <SectionBadge label="Trasferimenti" icon={<span className="text-sm leading-none">➕</span>} />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Nuovo trasferimento</h2>
                            <p className="text-sm text-gray-600 dark:text-gray-400">Compila origine, destinazione e importi per registrare il movimento.</p>
                        </header>
                        {accounts.length < 2 ? (
                            <div className="py-8 text-center">
                                <div className="mb-4 text-4xl">🏦</div>
                                <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                                    Servono almeno due conti
                                </h3>
                                <p className="mb-4 text-gray-500 dark:text-gray-400">
                                    Per effettuare un trasferimento devi avere almeno due conti attivi.
                                </p>
                                <LinkButton href={route('accounts.create')}>
                                    Crea un Conto
                                </LinkButton>
                            </div>
                        ) : (
                            <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                                {/* Conti */}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="source_account_id" value="Da (Conto Origine)" />
                                        <select
                                            id="source_account_id"
                                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            value={data.source_account_id}
                                            onChange={(e) => setData('source_account_id', e.target.value)}
                                            required
                                        >
                                            <option value="">Seleziona conto</option>
                                            {accounts
                                                .filter((a) => a.id !== Number(data.destination_account_id))
                                                .map((account) => (
                                                    <option key={account.id} value={account.id}>
                                                        {account.name} ({formatCurrency(account.current_balance, account.currency_code)})
                                                    </option>
                                                ))}
                                        </select>
                                        <InputError message={errors.source_account_id} className="mt-2" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="destination_account_id" value="A (Conto Destinazione)" />
                                        <select
                                            id="destination_account_id"
                                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            value={data.destination_account_id}
                                            onChange={(e) => setData('destination_account_id', e.target.value)}
                                            required
                                        >
                                            <option value="">Seleziona conto</option>
                                            {accounts
                                                .filter((a) => a.id !== Number(data.source_account_id))
                                                .map((account) => (
                                                    <option key={account.id} value={account.id}>
                                                        {account.name} ({account.currency_code})
                                                    </option>
                                                ))}
                                        </select>
                                        <InputError message={errors.destination_account_id} className="mt-2" />
                                    </div>
                                </div>

                                {/* Importo */}
                                <div>
                                    <InputLabel htmlFor="amount" value="Importo" />
                                    <div className="mt-1 flex items-center space-x-2">
                                        <TextInput
                                            id="amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            className="block flex-1"
                                            value={data.amount}
                                            onChange={(e) => setData('amount', e.target.value)}
                                            required
                                        />
                                        {sourceAccount && (
                                            <span className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                                {sourceAccount.currency_code}
                                            </span>
                                        )}
                                    </div>
                                    <InputError message={errors.amount} className="mt-2" />
                                </div>

                                {/* Tasso di Cambio (solo per valute diverse) */}
                                {isCrossCurrency && (
                                    <div className="rounded-lg bg-yellow-50 p-4 dark:bg-yellow-900/20">
                                        <p className="mb-3 text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                            ⚠️ Trasferimento tra valute diverse
                                        </p>
                                        <div>
                                            <InputLabel
                                                htmlFor="exchange_rate"
                                                value={`Tasso di cambio (1 ${sourceAccount?.currency_code} = ? ${destAccount?.currency_code})`}
                                            />
                                            <TextInput
                                                id="exchange_rate"
                                                type="number"
                                                step="0.00000001"
                                                min="0.00000001"
                                                className="mt-1 block w-full"
                                                value={data.exchange_rate}
                                                onChange={(e) => setData('exchange_rate', e.target.value)}
                                                required={isCrossCurrency}
                                            />
                                            <InputError message={errors.exchange_rate} className="mt-2" />
                                        </div>
                                    </div>
                                )}

                                {/* Anteprima */}
                                {estimatedDestAmount !== null && destAccount && (
                                    <div className="rounded-lg bg-emerald-50 p-4 dark:bg-emerald-900/20">
                                        <p className="text-sm text-emerald-600 dark:text-emerald-400">
                                            Importo che arriverà sul conto di destinazione:
                                        </p>
                                        <p className="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                                            {formatCurrency(estimatedDestAmount, destAccount.currency_code)}
                                        </p>
                                    </div>
                                )}

                                {/* Data e Commissioni */}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="date" value="Data" />
                                        <TextInput
                                            id="date"
                                            type="date"
                                            className="mt-1 block w-full"
                                            value={data.date}
                                            onChange={(e) => setData('date', e.target.value)}
                                        />
                                        <InputError message={errors.date} className="mt-2" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="fee" value="Commissione (opzionale)" />
                                        <TextInput
                                            id="fee"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="mt-1 block w-full"
                                            value={data.fee}
                                            onChange={(e) => setData('fee', e.target.value)}
                                            placeholder="0.00"
                                        />
                                        <InputError message={errors.fee} className="mt-2" />
                                    </div>
                                </div>

                                {/* Descrizione */}
                                <div>
                                    <InputLabel htmlFor="description" value="Descrizione (opzionale)" />
                                    <textarea
                                        id="description"
                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        rows={2}
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="es. Ricarica carta prepagata"
                                    />
                                    <InputError message={errors.description} className="mt-2" />
                                </div>

                                {/* Trasferimento Privato */}
                                <div className="flex items-start rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                    <div className="flex h-6 items-center">
                                        <input
                                            id="is_private"
                                            type="checkbox"
                                            className="h-4 w-4 rounded border-gray-300 text-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900"
                                            checked={data.is_private}
                                            onChange={(e) => setData('is_private', e.target.checked)}
                                        />
                                    </div>
                                    <div className="ml-3">
                                        <label
                                            htmlFor="is_private"
                                            className="text-sm font-medium text-gray-900 dark:text-white"
                                        >
                                            🔒 Trasferimento privato
                                        </label>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            Solo tu potrai vedere questo trasferimento e le relative transazioni.
                                        </p>
                                    </div>
                                </div>

                                {/* Azioni */}
                                <FormActionsBar className="justify-end">
                                    <Link
                                        href={route('transfers.index')}
                                        className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        Annulla
                                    </Link>
                                    <PrimaryButton
                                        disabled={
                                            processing ||
                                            !data.source_account_id ||
                                            !data.destination_account_id ||
                                            !data.amount ||
                                            (isCrossCurrency && !data.exchange_rate)
                                        }
                                    >
                                        {processing ? 'Trasferimento in corso...' : 'Trasferisci'}
                                    </PrimaryButton>
                                </FormActionsBar>
                            </form>
                        )}
                    </SectionCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
