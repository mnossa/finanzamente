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
import { TAX_DEDUCTION_TYPES } from '@/constants/taxDeductions';
import CardBox from '@/Components/CardBox';

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

interface DebtCredit {
    id: number;
    counterparty: string;
    remaining_amount: number;
    type: 'debt' | 'credit';
    status: string;
    currency_code: string;
}

interface CreateProps {
    accounts: Account[];
    categories: Category[];
    tags: Tag[];
    defaultAccountId?: string;
    defaultDebtCreditId?: string;
    debtsCredits: DebtCredit[];
}

export default function Create({ accounts, categories, tags, defaultAccountId, defaultDebtCreditId, debtsCredits }: CreateProps) {
    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        account_id: defaultAccountId || (accounts.length > 0 ? String(accounts[0].id) : ''),
        category_id: '',
        amount: '',
        date: today,
        description: '',
        is_private: false,
        is_tax_deductible: false,
        tax_deduction_rate: '19',
        tax_deduction_type: '',
        tax_year: new Date().getFullYear(),
        tag_ids: [] as number[],
        debt_credit_id: defaultDebtCreditId || '',
    });

    const selectedCategory = categories.find((c) => c.id === Number(data.category_id));
    const isExpense = selectedCategory?.type === 'expense';

    // Filtra i debiti/crediti in base al tipo di categoria:
    // spesa → debiti (stai pagando ciò che devi)
    // entrata → crediti (stai incassando ciò che ti devono)
    const filteredDebtsCredits = selectedCategory
        ? debtsCredits.filter((dc) => (isExpense ? dc.type === 'debt' : dc.type === 'credit'))
        : debtsCredits;

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
                <PageHeader
                    title="Nuova Transazione"
                    backLink={route('transactions.index')}
                />
            }
        >
            <Head title="Nuova Transazione" />

            <div className="py-6">
                <div className="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                    <CardBox>
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

                                {/* Detrazione Fiscale (solo per spese) */}
                                {isExpense && (
                                    <div className="space-y-4 rounded-lg border-2 border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-700 dark:bg-emerald-900/20">
                                        <div className="flex items-start">
                                            <div className="flex h-6 items-center">
                                                <input
                                                    id="is_tax_deductible"
                                                    type="checkbox"
                                                    className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                                    checked={data.is_tax_deductible}
                                                    onChange={(e) => setData('is_tax_deductible', e.target.checked)}
                                                />
                                            </div>
                                            <div className="ml-3">
                                                <label
                                                    htmlFor="is_tax_deductible"
                                                    className="text-sm font-medium text-gray-900 dark:text-white"
                                                >
                                                    📋 Spesa detraibile/deducibile (730)
                                                </label>
                                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                                    Segna questa spesa per la dichiarazione dei redditi.
                                                </p>
                                            </div>
                                        </div>

                                        {data.is_tax_deductible && (
                                            <div className="ml-7 space-y-4 border-l-2 border-emerald-300 pl-4 dark:border-emerald-600">
                                                {/* Tipo di detrazione */}
                                                <div>
                                                    <InputLabel htmlFor="tax_deduction_type" value="Tipo di detrazione" />
                                                    <select
                                                        id="tax_deduction_type"
                                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                                        value={data.tax_deduction_type}
                                                        onChange={(e) => setData('tax_deduction_type', e.target.value)}
                                                        required={data.is_tax_deductible}
                                                    >
                                                        <option value="">Seleziona tipo</option>
                                                        <option value="mediche">🏥 Spese Mediche (19%)</option>
                                                        <option value="veterinarie">🐾 Spese Veterinarie (19%)</option>
                                                        <option value="istruzione">🎓 Istruzione (19%)</option>
                                                        <option value="mutuo">🏠 Mutuo Prima Casa (19%)</option>
                                                        <option value="ristrutturazione">🔨 Ristrutturazione (50%)</option>
                                                        <option value="assicurazioni">🛡️ Assicurazioni (19%)</option>
                                                        <option value="previdenza">💼 Previdenza Complementare</option>
                                                        <option value="donazioni">❤️ Donazioni (19%-26%)</option>
                                                        <option value="altro">📌 Altro</option>
                                                    </select>
                                                    <InputError message={errors.tax_deduction_type} className="mt-2" />
                                                </div>

                                                {/* Percentuale e Anno */}
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <InputLabel htmlFor="tax_deduction_rate" value="Percentuale detrazione (%)" />
                                                        <TextInput
                                                            id="tax_deduction_rate"
                                                            type="number"
                                                            step="0.01"
                                                            min="0.01"
                                                            max="100"
                                                            className="mt-1 block w-full"
                                                            value={data.tax_deduction_rate}
                                                            onChange={(e) => setData('tax_deduction_rate', e.target.value)}
                                                            placeholder="es. 19"
                                                            required={data.is_tax_deductible}
                                                        />
                                                        <InputError message={errors.tax_deduction_rate} className="mt-2" />
                                                    </div>

                                                    <div>
                                                        <InputLabel htmlFor="tax_year" value="Anno fiscale" />
                                                        <TextInput
                                                            id="tax_year"
                                                            type="number"
                                                            min="2000"
                                                            max="2100"
                                                            className="mt-1 block w-full"
                                                            value={data.tax_year}
                                                            onChange={(e) => setData('tax_year', Number(e.target.value))}
                                                        />
                                                        <InputError message={errors.tax_year} className="mt-2" />
                                                    </div>
                                                </div>

                                                <p className="text-xs text-emerald-700 dark:text-emerald-300">
                                                    💡 Potrai allegare documenti (scontrini, fatture) dopo aver salvato la transazione.
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                )}

                                {/* Collega a Debito/Credito */}
                                {debtsCredits.length > 0 && (
                                    <div className="rounded-lg border border-purple-200 bg-purple-50 p-4 dark:border-purple-700 dark:bg-purple-900/20">
                                        <InputLabel htmlFor="debt_credit_id" value="🔗 Collega a Debito/Credito (opzionale)" />
                                        {filteredDebtsCredits.length === 0 && selectedCategory ? (
                                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                Nessun {isExpense ? 'debito' : 'credito'} aperto da collegare.
                                            </p>
                                        ) : (
                                            <select
                                                id="debt_credit_id"
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                                value={data.debt_credit_id}
                                                onChange={(e) => setData('debt_credit_id', e.target.value)}
                                            >
                                                <option value="">Nessun collegamento</option>
                                                {filteredDebtsCredits.map((dc) => (
                                                    <option key={dc.id} value={dc.id}>
                                                        {dc.type === 'debt' ? '📤' : '📥'} {dc.counterparty} — rimanenti: {new Intl.NumberFormat('it-IT', { style: 'currency', currency: dc.currency_code }).format(dc.remaining_amount)}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                        {!selectedCategory && (
                                            <p className="mt-1 text-xs text-purple-600 dark:text-purple-400">
                                                Seleziona prima una categoria per filtrare i {isExpense ? 'debiti' : 'crediti'} pertinenti.
                                            </p>
                                        )}
                                        {selectedCategory && filteredDebtsCredits.length > 0 && (
                                            <p className="mt-1 text-xs text-purple-700 dark:text-purple-300">
                                                Il saldo del {isExpense ? 'debito' : 'credito'} verrà aggiornato automaticamente.
                                            </p>
                                        )}
                                        <InputError message={errors.debt_credit_id} className="mt-2" />
                                    </div>
                                )}

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
                    </CardBox>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
