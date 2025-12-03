import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import clsx from 'clsx';

interface Category {
    id: number;
    name: string;
    icon: string | null;
}

interface Currency {
    code: string;
    name: string;
    symbol: string;
}

interface CreateProps {
    categories: Category[];
    currencies: Currency[];
}

export default function Create({ categories, currencies }: CreateProps) {
    // Imposta il periodo corrente come mese attuale
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    const { data, setData, post, processing, errors } = useForm({
        category_id: '',
        amount: '',
        currency_code: 'EUR',
        period_start: firstDay.toISOString().split('T')[0],
        period_end: lastDay.toISOString().split('T')[0],
        description: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('budgets.store'));
    };

    // Helper per impostare periodi predefiniti
    const setCurrentMonth = () => {
        const first = new Date(now.getFullYear(), now.getMonth(), 1);
        const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        setData({
            ...data,
            period_start: first.toISOString().split('T')[0],
            period_end: last.toISOString().split('T')[0],
        });
    };

    const setNextMonth = () => {
        const first = new Date(now.getFullYear(), now.getMonth() + 1, 1);
        const last = new Date(now.getFullYear(), now.getMonth() + 2, 0);
        setData({
            ...data,
            period_start: first.toISOString().split('T')[0],
            period_end: last.toISOString().split('T')[0],
        });
    };

    const setCurrentYear = () => {
        const first = new Date(now.getFullYear(), 0, 1);
        const last = new Date(now.getFullYear(), 11, 31);
        setData({
            ...data,
            period_start: first.toISOString().split('T')[0],
            period_end: last.toISOString().split('T')[0],
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center space-x-4">
                    <Link
                        href={route('budgets.index')}
                        className="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                    >
                        ←
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Nuovo Budget
                    </h2>
                </div>
            }
        >
            <Head title="Nuovo Budget" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                        <form onSubmit={submit} className="p-6">
                            <div className="space-y-6">
                                {/* Categoria */}
                                <div>
                                    <InputLabel htmlFor="category_id" value="Categoria *" />
                                    <select
                                        id="category_id"
                                        value={data.category_id}
                                        onChange={(e) =>
                                            setData('category_id', e.target.value)
                                        }
                                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        required
                                    >
                                        <option value="">Seleziona una categoria</option>
                                        {categories.map((cat) => (
                                            <option key={cat.id} value={cat.id}>
                                                {cat.icon || '📁'} {cat.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.category_id}
                                        className="mt-2"
                                    />
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

                                {/* Periodo */}
                                <div>
                                    <div className="mb-2 flex items-center justify-between">
                                        <InputLabel value="Periodo *" />
                                        <div className="flex space-x-2">
                                            <button
                                                type="button"
                                                onClick={setCurrentMonth}
                                                className="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600"
                                            >
                                                Mese corrente
                                            </button>
                                            <button
                                                type="button"
                                                onClick={setNextMonth}
                                                className="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600"
                                            >
                                                Prossimo mese
                                            </button>
                                            <button
                                                type="button"
                                                onClick={setCurrentYear}
                                                className="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600"
                                            >
                                                Anno corrente
                                            </button>
                                        </div>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <InputLabel
                                                htmlFor="period_start"
                                                value="Data inizio"
                                            />
                                            <TextInput
                                                id="period_start"
                                                type="date"
                                                value={data.period_start}
                                                className="mt-1 block w-full"
                                                onChange={(e) =>
                                                    setData('period_start', e.target.value)
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.period_start}
                                                className="mt-2"
                                            />
                                        </div>
                                        <div>
                                            <InputLabel
                                                htmlFor="period_end"
                                                value="Data fine"
                                            />
                                            <TextInput
                                                id="period_end"
                                                type="date"
                                                value={data.period_end}
                                                className="mt-1 block w-full"
                                                onChange={(e) =>
                                                    setData('period_end', e.target.value)
                                                }
                                                required
                                            />
                                            <InputError
                                                message={errors.period_end}
                                                className="mt-2"
                                            />
                                        </div>
                                    </div>
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
                                        placeholder="Note opzionali sul budget..."
                                    />
                                    <InputError
                                        message={errors.description}
                                        className="mt-2"
                                    />
                                </div>
                            </div>

                            <div className="mt-8 flex items-center justify-end space-x-4">
                                <Link
                                    href={route('budgets.index')}
                                    className="rounded-lg px-4 py-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    Crea Budget
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
