import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CategoryPicker from '@/Components/CategoryPicker';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LinkButton from '@/Components/LinkButton';
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

interface CreateProps {
    accounts: Account[];
    categories: Category[];
    tags: Tag[];
    defaultAccountId?: string;
}

export default function Create({ accounts, categories, tags, defaultAccountId }: CreateProps) {
    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        account_id: defaultAccountId || (accounts.length > 0 ? String(accounts[0].id) : ''),
        category_id: '',
        amount: '',
        date: today,
        description: '',
        is_private: false,
        tag_ids: [] as number[],
    });

    const selectedCategory = categories.find((c) => c.id === Number(data.category_id));
    const isExpense = selectedCategory?.type === 'expense';

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
        post(route('transactions.store'));
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
                        Nuova Transazione
                    </h2>
                </div>
            }
        >
            <Head title="Nuova Transazione" />

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
                                    Crea prima un conto per poter registrare transazioni.
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
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        rows={2}
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="es. Spesa settimanale al supermercato"
                                    />
                                    <InputError message={errors.description} className="mt-2" />
                                </div>

                                {/* Transazione Privata */}
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
                                                        '--tw-ring-color': tag.color || '#6366f1',
                                                    } as React.CSSProperties}
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
                                    <PrimaryButton disabled={processing || !data.category_id}>
                                        {processing ? 'Salvataggio...' : 'Salva Transazione'}
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
