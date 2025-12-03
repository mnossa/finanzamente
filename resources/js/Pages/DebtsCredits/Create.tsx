import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import clsx from 'clsx';

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface Types {
    [key: string]: string;
}

interface CreateProps {
    currencies: Currency[];
    types: Types;
}

export default function Create({ currencies, types }: CreateProps) {
    const { data, setData, post, processing, errors } = useForm({
        counterparty: '',
        amount: '',
        currency_code: 'EUR',
        type: 'debt',
        due_date: '',
        description: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('debts-credits.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center space-x-4">
                    <Link
                        href={route('debts-credits.index')}
                        className="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                    >
                        ←
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Nuovo Debito/Credito
                    </h2>
                </div>
            }
        >
            <Head title="Nuovo Debito/Credito" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <form onSubmit={submit} className="p-6">
                            <div className="space-y-6">
                                {/* Tipo */}
                                <div>
                                    <InputLabel value="Tipo *" />
                                    <div className="mt-2 grid grid-cols-2 gap-4">
                                        <button
                                            type="button"
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
                                            <span className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Soldi che devi
                                            </span>
                                        </button>
                                        <button
                                            type="button"
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
                                            <span className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Soldi che ti devono
                                            </span>
                                        </button>
                                    </div>
                                    <InputError message={errors.type} className="mt-2" />
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
                                        autoFocus
                                        placeholder={
                                            data.type === 'debt'
                                                ? 'A chi devi i soldi?'
                                                : 'Chi ti deve i soldi?'
                                        }
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
                                            placeholder="0,00"
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
                                            onChange={(e) =>
                                                setData('currency_code', e.target.value)
                                            }
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
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
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Se impostata, riceverai un avviso quando il
                                        debito/credito è scaduto.
                                    </p>
                                    <InputError message={errors.due_date} className="mt-2" />
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
                                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        placeholder="Descrivi il motivo del debito/credito..."
                                    />
                                    <InputError
                                        message={errors.description}
                                        className="mt-2"
                                    />
                                </div>
                            </div>

                            <div className="mt-8 flex items-center justify-end space-x-4">
                                <Link
                                    href={route('debts-credits.index')}
                                    className="rounded-lg px-4 py-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    {data.type === 'debt' ? 'Aggiungi Debito' : 'Aggiungi Credito'}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
