import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import clsx from 'clsx';

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

interface Tag {
    id: number;
    name: string;
    color: string | null;
}

interface Transaction {
    id: number;
    account_id: number;
    category_id: number;
    amount: number;
    date: string;
    description: string | null;
    is_private: boolean;
    tag_ids: number[];
}

interface EditProps {
    transaction: Transaction;
    accounts: Account[];
    categories: Category[];
    tags: Tag[];
}

export default function Edit({ transaction, accounts, categories, tags }: EditProps) {
    const { data, setData, patch, processing, errors } = useForm({
        account_id: String(transaction.account_id),
        category_id: String(transaction.category_id),
        amount: String(transaction.amount),
        date: transaction.date,
        description: transaction.description || '',
        is_private: transaction.is_private,
        tag_ids: transaction.tag_ids || [],
    });

    const selectedCategory = categories.find((c) => c.id === Number(data.category_id));
    const isExpense = selectedCategory?.type === 'expense';

    const incomeCategories = categories.filter((c) => c.type === 'income');
    const expenseCategories = categories.filter((c) => c.type === 'expense');

    const toggleTag = (tagId: number) => {
        const currentTags = data.tag_ids;
        if (currentTags.includes(tagId)) {
            setData('tag_ids', currentTags.filter((id) => id !== tagId));
        } else {
            setData('tag_ids', [...currentTags, tagId]);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('transactions.update', transaction.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center space-x-4">
                    <Link
                        href={route('transactions.index')}
                        className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        ← Indietro
                    </Link>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Modifica Transazione
                    </h2>
                </div>
            }
        >
            <Head title="Modifica Transazione" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <div className="overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        <form onSubmit={submit} className="space-y-6">
                            {/* Conto */}
                            <div>
                                <InputLabel htmlFor="account_id" value="Conto" />
                                <select
                                    id="account_id"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    value={data.account_id}
                                    onChange={(e) => setData('account_id', e.target.value)}
                                    required
                                >
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
                                <div className="mt-2 space-y-4">
                                    {/* Entrate */}
                                    <div>
                                        <p className="mb-2 text-sm font-medium text-green-600 dark:text-green-400">
                                            💰 Entrate
                                        </p>
                                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            {incomeCategories.map((category) => (
                                                <button
                                                    key={category.id}
                                                    type="button"
                                                    onClick={() => setData('category_id', String(category.id))}
                                                    className={clsx(
                                                        'flex items-center space-x-2 rounded-lg border-2 p-2 text-left text-sm transition-colors',
                                                        data.category_id === String(category.id)
                                                            ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                                                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-700'
                                                    )}
                                                >
                                                    <span>{category.icon || '💰'}</span>
                                                    <span className="truncate">{category.name}</span>
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    {/* Uscite */}
                                    <div>
                                        <p className="mb-2 text-sm font-medium text-red-600 dark:text-red-400">
                                            💸 Uscite
                                        </p>
                                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            {expenseCategories.map((category) => (
                                                <button
                                                    key={category.id}
                                                    type="button"
                                                    onClick={() => setData('category_id', String(category.id))}
                                                    className={clsx(
                                                        'flex items-center space-x-2 rounded-lg border-2 p-2 text-left text-sm transition-colors',
                                                        data.category_id === String(category.id)
                                                            ? 'border-red-500 bg-red-50 dark:bg-red-900/20'
                                                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-700'
                                                    )}
                                                >
                                                    <span>{category.icon || '💸'}</span>
                                                    <span className="truncate">{category.name}</span>
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                                <InputError message={errors.category_id} className="mt-2" />
                            </div>

                            {/* Importo e Data */}
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
                                    <InputLabel htmlFor="date" value="Data" />
                                    <TextInput
                                        id="date"
                                        type="date"
                                        className="mt-1 block w-full"
                                        value={data.date}
                                        onChange={(e) => setData('date', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.date} className="mt-2" />
                                </div>
                            </div>

                            {/* Descrizione */}
                            <div>
                                <InputLabel htmlFor="description" value="Descrizione (opzionale)" />
                                <textarea
                                    id="description"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    rows={2}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                />
                                <InputError message={errors.description} className="mt-2" />
                            </div>

                            {/* Transazione Privata */}
                            <div className="flex items-start rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                <div className="flex h-6 items-center">
                                    <input
                                        id="is_private"
                                        type="checkbox"
                                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                                        checked={data.is_private}
                                        onChange={(e) => setData('is_private', e.target.checked)}
                                    />
                                </div>
                                <div className="ml-3">
                                    <label
                                        htmlFor="is_private"
                                        className="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        🔒 Transazione privata
                                    </label>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Solo tu potrai vedere questa transazione.
                                    </p>
                                </div>
                            </div>

                            {/* Tag */}
                            {tags.length > 0 && (
                                <div>
                                    <InputLabel value="Tag (opzionale)" />
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {tags.map((tag) => (
                                            <button
                                                key={tag.id}
                                                type="button"
                                                onClick={() => toggleTag(tag.id)}
                                                className={clsx(
                                                    'inline-flex items-center rounded-full px-3 py-1 text-sm font-medium transition-colors',
                                                    data.tag_ids.includes(tag.id)
                                                        ? 'ring-2 ring-offset-2 dark:ring-offset-gray-800'
                                                        : 'opacity-60 hover:opacity-100'
                                                )}
                                                style={{
                                                    backgroundColor: tag.color ? `${tag.color}20` : '#e5e7eb',
                                                    color: tag.color || '#374151',
                                                    borderColor: tag.color || '#d1d5db',
                                                    ringColor: tag.color || '#6366f1',
                                                }}
                                            >
                                                🏷️ {tag.name}
                                            </button>
                                        ))}
                                    </div>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Clicca sui tag per aggiungerli o rimuoverli.
                                    </p>
                                </div>
                            )}

                            {/* Azioni */}
                            <div className="flex items-center justify-end space-x-4 border-t border-gray-200 pt-6 dark:border-gray-700">
                                <Link
                                    href={route('transactions.index')}
                                    className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Salvataggio...' : 'Salva Modifiche'}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
