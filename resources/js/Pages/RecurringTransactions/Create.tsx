import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CategoryPicker from '@/Components/CategoryPicker';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LinkButton from '@/Components/LinkButton';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import PageHeader from '@/Components/PageHeader';

interface Category {
    id: number;
    name: string;
    type: 'income' | 'expense';
    color: string | null;
    icon: string | null;
}

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface Frequencies {
    [key: string]: string;
}

interface CreateProps {
    accounts: Account[];
    categories: Category[];
    frequencies: Frequencies;
}

export default function Create({ accounts, categories, frequencies }: CreateProps) {
    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        account_id: accounts.length > 0 ? String(accounts[0].id) : '',
        category_id: '',
        amount: '',
        frequency: 'monthly',
        start_date: today,
        end_date: '',
        description: '',
    });

    const selectedCategory = categories.find((c) => c.id === Number(data.category_id));
    const isExpense = selectedCategory?.type === 'expense';

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('recurring-transactions.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Nuova Transazione Ricorrente"
                    backLink={route('recurring-transactions.index')}
                />
            }
        >
            <Head title="Nuova Transazione Ricorrente" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        {accounts.length === 0 ? (
                            <div className="py-8 text-center">
                                <div className="mb-4 text-4xl">🏦</div>
                                <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                                    Nessun conto disponibile
                                </h3>
                                <p className="mb-4 text-gray-500 dark:text-gray-400">
                                    Crea prima un conto per poter creare transazioni ricorrenti.
                                </p>
                                <LinkButton href={route('accounts.create')}>
                                    Crea un Conto
                                </LinkButton>
                            </div>
                        ) : categories.length === 0 ? (
                            <div className="py-8 text-center">
                                <div className="mb-4 text-4xl">🏷️</div>
                                <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                                    Nessuna categoria disponibile
                                </h3>
                                <p className="mb-4 text-gray-500 dark:text-gray-400">
                                    Crea prima delle categorie per classificare le transazioni.
                                </p>
                                <LinkButton href={route('categories.create')}>
                                    Crea una Categoria
                                </LinkButton>
                            </div>
                        ) : (
                            <form onSubmit={submit} className="space-y-6">
                                {/* Conto */}
                                <div>
                                    <InputLabel htmlFor="account_id" value="Conto" />
                                    <select
                                        id="account_id"
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        value={data.account_id}
                                        onChange={(e) => setData('account_id', e.target.value)}
                                        required
                                    >
                                        <option value="">Seleziona un conto</option>
                                        {accounts.map((account) => (
                                            <option key={account.id} value={account.id}>
                                                {account.name} ({account.currency_code})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.account_id} className="mt-2" />
                                </div>

                                {/* Categoria */}
                                <div>
                                    <InputLabel htmlFor="category_id" value="Categoria" />
                                    <CategoryPicker
                                        categories={categories}
                                        value={data.category_id}
                                        onChange={(categoryId) => setData('category_id', categoryId)}
                                        error={errors.category_id}
                                        className="mt-2"
                                    />
                                </div>

                                {/* Importo e Frequenza */}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="amount" value="Importo" />
                                        <div className="relative mt-1">
                                            <span
                                                className={clsx(
                                                    'absolute left-3 top-1/2 -translate-y-1/2 text-lg',
                                                    isExpense ? 'text-red-500' : 'text-green-500'
                                                )}
                                            >
                                                {isExpense ? '-' : '+'}
                                            </span>
                                            <TextInput
                                                id="amount"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                className="block w-full pl-8"
                                                value={data.amount}
                                                onChange={(e) => setData('amount', e.target.value)}
                                                required
                                            />
                                        </div>
                                        <InputError message={errors.amount} className="mt-2" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="frequency" value="Frequenza" />
                                        <select
                                            id="frequency"
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            value={data.frequency}
                                            onChange={(e) => setData('frequency', e.target.value)}
                                            required
                                        >
                                            {Object.entries(frequencies).map(([key, label]) => (
                                                <option key={key} value={key}>
                                                    {label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.frequency} className="mt-2" />
                                    </div>
                                </div>

                                {/* Date */}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <InputLabel htmlFor="start_date" value="Data Inizio" />
                                        <TextInput
                                            id="start_date"
                                            type="date"
                                            className="mt-1 block w-full"
                                            value={data.start_date}
                                            onChange={(e) => setData('start_date', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.start_date} className="mt-2" />
                                    </div>

                                    <div>
                                        <InputLabel htmlFor="end_date" value="Data Fine (opzionale)" />
                                        <TextInput
                                            id="end_date"
                                            type="date"
                                            className="mt-1 block w-full"
                                            value={data.end_date}
                                            onChange={(e) => setData('end_date', e.target.value)}
                                        />
                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            Lascia vuoto per una ricorrenza senza scadenza.
                                        </p>
                                        <InputError message={errors.end_date} className="mt-2" />
                                    </div>
                                </div>

                                {/* Descrizione */}
                                <div>
                                    <InputLabel htmlFor="description" value="Descrizione (opzionale)" />
                                    <textarea
                                        id="description"
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        rows={2}
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="es. Affitto mensile, Abbonamento Netflix"
                                    />
                                    <InputError message={errors.description} className="mt-2" />
                                </div>

                                {/* Azioni */}
                                <div className="flex items-center justify-end space-x-4 border-t border-gray-200 pt-6 dark:border-gray-700">
                                    <Link
                                        href={route('recurring-transactions.index')}
                                        className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        Annulla
                                    </Link>
                                    <PrimaryButton disabled={processing || !data.category_id}>
                                        {processing ? 'Salvataggio...' : 'Crea Ricorrenza'}
                                    </PrimaryButton>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
