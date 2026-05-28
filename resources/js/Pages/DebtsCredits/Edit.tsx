import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FM_MOBILE_PRIMARY_FORM_ID } from '@/utils/mobilePrimaryFab';
import { FormEventHandler, useState } from 'react';
import clsx from 'clsx';
import PageHeader from '@/Components/PageHeader';

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface Types {
    [key: string]: string;
}

interface Statuses {
    [key: string]: string;
}

interface DebtCredit {
    id: number;
    counterparty: string;
    amount: number;
    currency_code: string;
    type: string;
    due_date: string | null;
    start_date: string | null;
    status: string;
    description: string | null;
    interest_rate: number | null;
    tan_rate: number | null;
    taeg_rate: number | null;
    interest_type: string;
    interest_calculation_date: string | null;
    has_linked_transactions: boolean;
}

interface EditProps {
    debtCredit: DebtCredit;
    currencies: Currency[];
    types: Types;
    statuses: Statuses;
}

export default function Edit({ debtCredit, currencies, types, statuses }: EditProps) {
    const [showInterest, setShowInterest] = useState(
        Boolean(debtCredit.interest_rate && debtCredit.interest_rate > 0)
    );

    const { data, setData, put, processing, errors } = useForm({
        counterparty: debtCredit.counterparty,
        amount: debtCredit.amount.toString(),
        currency_code: debtCredit.currency_code,
        type: debtCredit.type,
        due_date: debtCredit.due_date || '',
        start_date: debtCredit.start_date || '',
        status: debtCredit.status,
        description: debtCredit.description || '',
        interest_rate: debtCredit.interest_rate?.toString() || '',
        tan_rate: debtCredit.tan_rate?.toString() || '',
        taeg_rate: debtCredit.taeg_rate?.toString() || '',
        interest_type: debtCredit.interest_type || 'simple',
        interest_calculation_date: debtCredit.interest_calculation_date || '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('debts-credits.update', debtCredit.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Modifica ${types[debtCredit.type]}`}
                    backLink={route('debts-credits.index')}
                />
            }
        >
            <Head title={`Modifica ${types[debtCredit.type]}`} />

            <PageContent maxWidth="2xl">
                    <SectionCard className="space-y-4">
                        <header className="hidden sm:block space-y-2">
                            <SectionBadge label="Debiti/Crediti" icon={<span className="text-sm leading-none">✏️</span>} />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Modifica posizione</h2>
                            <p className="text-sm text-gray-600 dark:text-gray-400">Aggiorna stato, importo e dettagli della posizione selezionata.</p>
                        </header>
                        <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                            <div className="space-y-6">
                                {/* Tipo */}
                                <div>
                                    <InputLabel value="Tipo *" />
                                    <div className="mt-2 grid grid-cols-2 gap-4">
                                        <button
                                            type="button"
                                            disabled={debtCredit.has_linked_transactions}
                                            onClick={() => setData('type', 'debt')}
                                            className={clsx(
                                                'flex flex-col items-center rounded-xl border-2 p-4 transition-all',
                                                data.type === 'debt'
                                                    ? 'border-red-500 bg-red-50 dark:bg-red-900/20'
                                                    : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'
                                            )}
                                        >
                                            <span className="text-3xl">📤</span>
                                            <span
                                                className={clsx(
                                                    'mt-2 font-medium',
                                                    data.type === 'debt'
                                                        ? 'text-red-600 dark:text-red-400'
                                                        : 'text-gray-700 dark:text-gray-300'
                                                )}
                                            >
                                                Debito
                                            </span>
                                        </button>
                                        <button
                                            type="button"
                                            disabled={debtCredit.has_linked_transactions}
                                            onClick={() => setData('type', 'credit')}
                                            className={clsx(
                                                'flex flex-col items-center rounded-xl border-2 p-4 transition-all',
                                                data.type === 'credit'
                                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'
                                                    : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'
                                            )}
                                        >
                                            <span className="text-3xl">📥</span>
                                            <span
                                                className={clsx(
                                                    'mt-2 font-medium',
                                                    data.type === 'credit'
                                                        ? 'text-emerald-600 dark:text-emerald-400'
                                                        : 'text-gray-700 dark:text-gray-300'
                                                )}
                                            >
                                                Credito
                                            </span>
                                        </button>
                                    </div>
                                    <InputError message={errors.type} className="mt-2" />
                                </div>

                                {/* Stato */}
                                <div>
                                    <InputLabel htmlFor="status" value="Stato *" />
                                    <select
                                        id="status"
                                        value={data.status}
                                        onChange={(e) => setData('status', e.target.value)}
                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        required
                                    >
                                        {Object.entries(statuses).map(([key, label]) => (
                                            <option key={key} value={key}>
                                                {label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.status} className="mt-2" />
                                </div>

                                {/* Controparte */}
                                <div>
                                    <InputLabel htmlFor="counterparty" value="Controparte *" />
                                    <TextInput
                                        id="counterparty"
                                        type="text"
                                        value={data.counterparty}
                                        className="mt-1 block w-full"
                                        onChange={(e) =>
                                            setData('counterparty', e.target.value)
                                        }
                                        required
                                    />
                                    <InputError message={errors.counterparty} className="mt-2" />
                                </div>

                                {/* Importo e Valuta */}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="amount" value="Importo *" />
                                        <TextInput
                                            id="amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            value={data.amount}
                                            className="mt-1 block w-full"
                                            onChange={(e) =>
                                                setData('amount', e.target.value)
                                            }
                                            required
                                        />
                                        <InputError message={errors.amount} className="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel
                                            htmlFor="currency_code"
                                            value="Valuta *"
                                        />
                                        <select
                                            id="currency_code"
                                            value={data.currency_code}
                                            disabled={debtCredit.has_linked_transactions}
                                            onChange={(e) =>
                                                setData('currency_code', e.target.value)
                                            }
                                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 disabled:opacity-60"
                                            required
                                        >
                                            {currencies.map((curr) => (
                                                <option key={curr.code} value={curr.code}>
                                                    {curr.symbol} - {curr.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.currency_code}
                                            className="mt-2"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <InputLabel htmlFor="start_date" value="Data di inizio (opzionale)" />
                                    <TextInput
                                        id="start_date"
                                        type="date"
                                        value={data.start_date}
                                        className="mt-1 block w-full"
                                        onChange={(e) => setData('start_date', e.target.value)}
                                    />
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Utile per analisi e calcolo interessi nel tempo.
                                    </p>
                                    <InputError message={errors.start_date} className="mt-2" />
                                </div>

                                {/* Data Scadenza */}
                                <div>
                                    <InputLabel
                                        htmlFor="due_date"
                                        value="Data di Scadenza (opzionale)"
                                    />
                                    <TextInput
                                        id="due_date"
                                        type="date"
                                        value={data.due_date}
                                        className="mt-1 block w-full"
                                        onChange={(e) => setData('due_date', e.target.value)}
                                    />
                                    <InputError message={errors.due_date} className="mt-2" />
                                </div>

                                <div>
                                    <button
                                        type="button"
                                        onClick={() => setShowInterest(!showInterest)}
                                        className="text-sm font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
                                    >
                                        {showInterest ? '▼' : '▶'} Interessi (facoltativo)
                                    </button>
                                    {showInterest && (
                                        <div className="mt-3 space-y-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <InputLabel htmlFor="interest_rate" value="Tasso annuo (%)" />
                                                    <TextInput
                                                        id="interest_rate"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                        value={data.interest_rate}
                                                        className="mt-1 block w-full"
                                                        onChange={(e) => setData('interest_rate', e.target.value)}
                                                    />
                                                    <InputError message={errors.interest_rate} className="mt-2" />
                                                </div>
                                                <div>
                                                    <InputLabel htmlFor="interest_type" value="Tipo interesse" />
                                                    <select
                                                        id="interest_type"
                                                        value={data.interest_type}
                                                        onChange={(e) => setData('interest_type', e.target.value)}
                                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-900"
                                                    >
                                                        <option value="simple">Semplice</option>
                                                        <option value="compound">Composto</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <div>
                                                    <InputLabel htmlFor="tan_rate" value="TAN (%)" />
                                                    <TextInput
                                                        id="tan_rate"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                        value={data.tan_rate}
                                                        className="mt-1 block w-full"
                                                        onChange={(e) => setData('tan_rate', e.target.value)}
                                                    />
                                                    <InputError message={errors.tan_rate} className="mt-2" />
                                                </div>
                                                <div>
                                                    <InputLabel htmlFor="taeg_rate" value="TAEG (%)" />
                                                    <TextInput
                                                        id="taeg_rate"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                        value={data.taeg_rate}
                                                        className="mt-1 block w-full"
                                                        onChange={(e) => setData('taeg_rate', e.target.value)}
                                                    />
                                                    <InputError message={errors.taeg_rate} className="mt-2" />
                                                </div>
                                            </div>
                                            <div>
                                                <InputLabel htmlFor="interest_calculation_date" value="Data calcolo interessi" />
                                                <TextInput
                                                    id="interest_calculation_date"
                                                    type="date"
                                                    value={data.interest_calculation_date}
                                                    className="mt-1 block w-full"
                                                    onChange={(e) => setData('interest_calculation_date', e.target.value)}
                                                />
                                                <InputError message={errors.interest_calculation_date} className="mt-2" />
                                            </div>
                                        </div>
                                    )}
                                </div>

                                {/* Descrizione */}
                                <div>
                                    <InputLabel htmlFor="description" value="Note" />
                                    <textarea
                                        id="description"
                                        value={data.description}
                                        onChange={(e) =>
                                            setData('description', e.target.value)
                                        }
                                        rows={3}
                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    />
                                    <InputError
                                        message={errors.description}
                                        className="mt-2"
                                    />
                                </div>
                            </div>

                            <FormActionsBar className="justify-end">
                                <Link
                                    href={route('debts-credits.index')}
                                    className="rounded-lg px-4 py-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    Salva Modifiche
                                </PrimaryButton>
                            </FormActionsBar>
                        </form>
                    </SectionCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
