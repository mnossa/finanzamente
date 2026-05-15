import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import PageHeader from '@/Components/PageHeader';

interface Account {
    id: number;
    name: string;
    currency_code: string;
    current_balance: number;
    currency?: {
        code: string;
        symbol: string;
    };
}

interface Household {
    id: number;
    name: string;
    exclude_inter_transfers_from_stats: boolean;
}

interface CreateProps {
    sourceAccounts: Account[];
    userHouseholds: Household[];
    activeHouseholdExcludesDefault: boolean;
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

export default function Create({ sourceAccounts, userHouseholds, activeHouseholdExcludesDefault }: CreateProps) {
    const today = new Date().toISOString().split('T')[0];
    const [destAccounts, setDestAccounts] = useState<Account[]>([]);
    const [loadingAccounts, setLoadingAccounts] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        source_account_id: '',
        dest_household_id: '',
        dest_account_id: '',
        dest_user_id: '',
        source_amount: '',
        dest_amount: '',
        source_currency: '',
        dest_currency: '',
        exchange_rate: '',
        fee: '',
        transfer_date: today,
        description: '',
        notes: '',
        exclude_from_stats: activeHouseholdExcludesDefault,
    });

    const sourceAccount = useMemo(
        () => sourceAccounts.find((a) => a.id === Number(data.source_account_id)),
        [sourceAccounts, data.source_account_id]
    );

    const destAccount = useMemo(
        () => destAccounts.find((a) => a.id === Number(data.dest_account_id)),
        [destAccounts, data.dest_account_id]
    );

    // Carica gli account della household destinataria quando viene selezionata
    useEffect(() => {
        if (data.dest_household_id) {
            setLoadingAccounts(true);
            axios
                .get(route('households.accounts', data.dest_household_id))
                .then((response) => {
                    setDestAccounts(response.data);
                })
                .catch((error) => {
                    console.error('Errore nel caricamento degli account:', error);
                    setDestAccounts([]);
                })
                .finally(() => {
                    setLoadingAccounts(false);
                });

            // Aggiorna il default di exclude_from_stats in base alle impostazioni delle due households
            const destHousehold = userHouseholds.find((h) => h.id === Number(data.dest_household_id));
            const shouldExclude =
                activeHouseholdExcludesDefault || (destHousehold?.exclude_inter_transfers_from_stats ?? false);
            setData('exclude_from_stats', shouldExclude);
        } else {
            setDestAccounts([]);
            setData('dest_account_id', '');
        }
    }, [data.dest_household_id]);

    // Aggiorna le valute quando vengono selezionati gli account
    useEffect(() => {
        if (sourceAccount && !data.source_currency) {
            setData('source_currency', sourceAccount.currency_code);
        }
    }, [sourceAccount]);

    useEffect(() => {
        if (destAccount && !data.dest_currency) {
            setData('dest_currency', destAccount.currency_code);
        }
    }, [destAccount]);

    const isCrossCurrency =
        data.source_currency && data.dest_currency && data.source_currency !== data.dest_currency;

    // Calcola automaticamente l'importo di destinazione se c'è un tasso di cambio
    useEffect(() => {
        if (isCrossCurrency && data.source_amount && data.exchange_rate) {
            const sourceAmount = parseFloat(data.source_amount);
            const rate = parseFloat(data.exchange_rate);
            if (!isNaN(sourceAmount) && !isNaN(rate) && rate > 0) {
                const calculated = sourceAmount * rate;
                setData('dest_amount', calculated.toFixed(2));
            }
        } else if (!isCrossCurrency && data.source_amount) {
            // Quando le valute sono uguali, l'importo di destinazione è sempre uguale a quello sorgente
            setData('dest_amount', data.source_amount);
        }
    }, [data.source_amount, data.exchange_rate, isCrossCurrency]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('inter-household-transfers.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Nuovo Trasferimento tra Households"
                    backLink={route('inter-household-transfers.index')}
                />
            }
        >
            <Head title="Nuovo Trasferimento tra Households" />

            <PageContent>
                    <CardBox>
                        {sourceAccounts.length === 0 ? (
                            <div className="py-8 text-center">
                                <div className="mb-4 text-4xl">🏦</div>
                                <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                                    Nessun account disponibile
                                </h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-400">
                                    Devi prima creare almeno un account nella tua household attiva.
                                </p>
                                <Link
                                    href={route('accounts.create')}
                                    className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                >
                                    Crea Account
                                </Link>
                            </div>
                        ) : userHouseholds.length === 0 ? (
                            <div className="py-8 text-center">
                                <div className="mb-4 text-4xl">🏠</div>
                                <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                                    Nessuna household di destinazione
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    Non ci sono altre households disponibili per i trasferimenti.
                                </p>
                            </div>
                        ) : (
                            <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                                {/* Info box */}
                                <div className="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                                    <div className="flex">
                                        <div className="flex-shrink-0">
                                            <svg
                                                className="h-5 w-5 text-blue-400"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fillRule="evenodd"
                                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                    clipRule="evenodd"
                                                />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <p className="text-sm text-blue-700 dark:text-blue-300">
                                                Stai trasferendo fondi tra le tue households. Le transazioni verranno create immediatamente.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Account sorgente */}
                                <div>
                                    <InputLabel htmlFor="source_account_id" value="Account Sorgente" />
                                    <select
                                        id="source_account_id"
                                        value={data.source_account_id}
                                        onChange={(e) => setData('source_account_id', e.target.value)}
                                        className={clsx(
                                            'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                                            errors.source_account_id && 'border-red-500'
                                        )}
                                    >
                                        <option value="">Seleziona un account</option>
                                        {sourceAccounts.map((account) => (
                                            <option key={account.id} value={account.id}>
                                                {account.name} -{' '}
                                                {formatCurrency(account.current_balance, account.currency_code)}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.source_account_id} className="mt-2" />
                                </div>

                                {/* Household destinataria */}
                                <div>
                                    <InputLabel htmlFor="dest_household_id" value="Household Destinataria" />
                                    <select
                                        id="dest_household_id"
                                        value={data.dest_household_id}
                                        onChange={(e) => setData('dest_household_id', e.target.value)}
                                        className={clsx(
                                            'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                                            errors.dest_household_id && 'border-red-500'
                                        )}
                                    >
                                        <option value="">Seleziona una household</option>
                                        {userHouseholds.map((household) => (
                                            <option key={household.id} value={household.id}>
                                                {household.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.dest_household_id} className="mt-2" />
                                </div>

                                {/* Account destinatario */}
                                <div>
                                    <InputLabel htmlFor="dest_account_id" value="Account Destinatario" />
                                    <select
                                        id="dest_account_id"
                                        name="dest_account_id"
                                        value={data.dest_account_id}
                                        onChange={(e) => setData('dest_account_id', e.target.value)}
                                        disabled={!data.dest_household_id || loadingAccounts}
                                        className={clsx(
                                            'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                                            errors.dest_account_id && 'border-red-500',
                                            (!data.dest_household_id || loadingAccounts) &&
                                                'cursor-not-allowed opacity-50'
                                        )}
                                    >
                                        <option value="">
                                            {loadingAccounts
                                                ? 'Caricamento...'
                                                : destAccounts.length === 0
                                                  ? 'Nessun account disponibile'
                                                  : 'Seleziona un account'}
                                        </option>
                                        {destAccounts.map((account) => (
                                            <option key={account.id} value={account.id}>
                                                {account.name} ({account.currency_code})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.dest_account_id} className="mt-2" />
                                </div>

                                {/* Importo sorgente */}
                                <div>
                                    <InputLabel htmlFor="source_amount" value="Importo" />
                                    <div className="relative mt-1">
                                        <TextInput
                                            id="source_amount"
                                            name="source_amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            value={data.source_amount}
                                            onChange={(e) => setData('source_amount', e.target.value)}
                                            className="block w-full"
                                            placeholder="0,00"
                                        />
                                        {data.source_currency && (
                                            <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                                <span className="text-gray-500 dark:text-gray-400">
                                                    {data.source_currency}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                    <InputError message={errors.source_amount} className="mt-2" />
                                </div>

                                {/* Campi per cambio valuta */}
                                {isCrossCurrency && (
                                    <>
                                        <div>
                                            <InputLabel
                                                htmlFor="exchange_rate"
                                                value="Tasso di Cambio"
                                            />
                                            <TextInput
                                                id="exchange_rate"
                                                type="number"
                                                step="0.000001"
                                                min="0.000001"
                                                value={data.exchange_rate}
                                                onChange={(e) => setData('exchange_rate', e.target.value)}
                                                className="mt-1 block w-full"
                                                placeholder={`1 ${data.source_currency} = ? ${data.dest_currency}`}
                                            />
                                            <InputError message={errors.exchange_rate} className="mt-2" />
                                        </div>

                                        <div>
                                            <InputLabel
                                                htmlFor="dest_amount"
                                                value="Importo Destinazione"
                                            />
                                            <div className="relative mt-1">
                                                <TextInput
                                                    id="dest_amount"
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    value={data.dest_amount}
                                                    onChange={(e) => setData('dest_amount', e.target.value)}
                                                    className="block w-full"
                                                    placeholder="0,00"
                                                />
                                                {data.dest_currency && (
                                                    <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                                        <span className="text-gray-500 dark:text-gray-400">
                                                            {data.dest_currency}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                            <InputError message={errors.dest_amount} className="mt-2" />
                                        </div>
                                    </>
                                )}

                                {/* Commissione */}
                                <div>
                                    <InputLabel htmlFor="fee" value="Commissione (opzionale)" />
                                    <div className="relative mt-1">
                                        <TextInput
                                            id="fee"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.fee}
                                            onChange={(e) => setData('fee', e.target.value)}
                                            className="block w-full"
                                            placeholder="0,00"
                                        />
                                        {data.source_currency && (
                                            <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                                <span className="text-gray-500 dark:text-gray-400">
                                                    {data.source_currency}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                    <InputError message={errors.fee} className="mt-2" />
                                </div>

                                {/* Data */}
                                <div>
                                    <InputLabel htmlFor="transfer_date" value="Data Trasferimento" />
                                    <TextInput
                                        id="transfer_date"
                                        type="date"
                                        max={today}
                                        value={data.transfer_date}
                                        onChange={(e) => setData('transfer_date', e.target.value)}
                                        className="mt-1 block w-full"
                                    />
                                    <InputError message={errors.transfer_date} className="mt-2" />
                                </div>

                                {/* Descrizione */}
                                <div>
                                    <InputLabel htmlFor="description" value="Descrizione (opzionale)" />
                                    <textarea
                                        id="description"
                                        name="description"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        rows={3}
                                        maxLength={500}
                                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        placeholder="Inserisci una descrizione del trasferimento..."
                                    />
                                    <InputError message={errors.description} className="mt-2" />
                                </div>

                                {/* Note */}
                                <div>
                                    <InputLabel htmlFor="notes" value="Note (opzionali)" />
                                    <textarea
                                        id="notes"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        rows={3}
                                        maxLength={1000}
                                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        placeholder="Aggiungi note aggiuntive..."
                                    />
                                    <InputError message={errors.notes} className="mt-2" />
                                </div>

                                {/* Escludi dai calcoli statistici */}
                                <div className="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                    <label className="flex cursor-pointer items-start gap-3">
                                        <div className="relative mt-0.5 flex-shrink-0">
                                            <input
                                                type="checkbox"
                                                id="exclude_from_stats"
                                                checked={data.exclude_from_stats}
                                                onChange={(e) =>
                                                    setData('exclude_from_stats', e.target.checked)
                                                }
                                                className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600"
                                            />
                                        </div>
                                        <div>
                                            <span className="block text-sm font-medium text-gray-900 dark:text-gray-100">
                                                Escludi dai calcoli statistici
                                            </span>
                                            <span className="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                                Attiva se questo è uno spostamento interno tra tue households (es. casa
                                                principale ↔ seconda casa). Le transazioni generate non influiranno su
                                                entrate, uscite e lifestyle score di nessuna delle due households.
                                            </span>
                                            {data.exclude_from_stats && (
                                                <span className="mt-1.5 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                                    <svg className="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path
                                                            fillRule="evenodd"
                                                            d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                                                            clipRule="evenodd"
                                                        />
                                                    </svg>
                                                    Impostato automaticamente per questa coppia di households
                                                </span>
                                            )}
                                        </div>
                                    </label>
                                </div>

                                {/* Pulsanti */}
                                <div className="flex items-center justify-end space-x-4 pt-4">
                                    <Link
                                        href={route('inter-household-transfers.index')}
                                        className="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                    >
                                        Annulla
                                    </Link>
                                    <PrimaryButton disabled={processing}>
                                        {processing ? 'Creazione...' : 'Crea Trasferimento'}
                                    </PrimaryButton>
                                </div>
                            </form>
                        )}
                    </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
