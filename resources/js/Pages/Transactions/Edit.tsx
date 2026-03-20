import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import CategoryPicker from '@/Components/CategoryPicker';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import PageHeader from '@/Components/PageHeader';
import { TAX_DEDUCTION_TYPES } from '@/constants/taxDeductions';

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
    is_tax_deductible: boolean;
    tax_deduction_rate: number | null;
    tax_deduction_type: string | null;
    tax_year: number | null;
    tag_ids: number[];
    transfer_id: number | null;
    debt_credit_id: number | null;
    is_inter_household_transfer?: boolean;
}

interface DebtCredit {
    id: number;
    counterparty: string;
    remaining_amount: number;
    type: 'debt' | 'credit';
    status: string;
    currency_code: string;
}

interface EditProps {
    transaction: Transaction;
    accounts: Account[];
    categories: Category[];
    tags: Tag[];
    debtsCredits: DebtCredit[];
}

export default function Edit({ transaction, accounts, categories, tags, debtsCredits }: EditProps) {
    const { data, setData, patch, processing, errors } = useForm({
        account_id: String(transaction.account_id),
        category_id: String(transaction.category_id),
        amount: String(transaction.amount),
        date: transaction.date,
        description: transaction.description || '',
        is_private: transaction.is_private,
        is_tax_deductible: transaction.is_tax_deductible || false,
        tax_deduction_rate: transaction.tax_deduction_rate ? String(transaction.tax_deduction_rate) : '19',
        tax_deduction_type: transaction.tax_deduction_type || '',
        tax_year: transaction.tax_year || new Date().getFullYear(),
        tag_ids: transaction.tag_ids || [],
        debt_credit_id: transaction.debt_credit_id ? String(transaction.debt_credit_id) : '',
    });

    const selectedCategory = categories.find((c) => c.id === Number(data.category_id));
    const isExpense = selectedCategory?.type === 'expense';
    const isTransfer = transaction.transfer_id !== null;
    const isInterHouseholdTransfer = transaction.is_inter_household_transfer || false;

    // Filtra i debiti/crediti in base al tipo di categoria
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
        patch(route('transactions.update', transaction.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Modifica Transazione"
                    backLink={route('transactions.index')}
                />
            }
        >
            <Head title="Modifica Transazione" />

            <PageContent maxWidth="2xl">
                    <CardBox>
                        {/* Avviso trasferimento inter-household */}
                        {isInterHouseholdTransfer && (
                            <div className="mb-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                                <span className="text-xl">🚫</span>
                                <div>
                                    <p className="font-medium text-red-800 dark:text-red-200">
                                        Transazione di trasferimento tra Household
                                    </p>
                                    <p className="mt-1 text-sm text-red-700 dark:text-red-300">
                                        Questa transazione fa parte di un trasferimento tra Households diverse. 
                                        Non è possibile modificarla. Per eliminarla, vai alla lista dei trasferimenti inter-household.
                                    </p>
                                </div>
                            </div>
                        )}
                        
                        {/* Avviso trasferimento normale */}
                        {isTransfer && !isInterHouseholdTransfer && (
                            <div className="mb-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                                <span className="text-xl">🔄</span>
                                <div>
                                    <p className="font-medium text-amber-800 dark:text-amber-200">
                                        Transazione di trasferimento
                                    </p>
                                    <p className="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                        Questa transazione fa parte di un trasferimento. Non è possibile modificare il conto o la categoria. 
                                        Le modifiche a importo, descrizione e privacy verranno applicate anche alla transazione collegata.
                                    </p>
                                </div>
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-6">
                            {/* Conto */}
                            <div>
                                <InputLabel htmlFor="account_id" value="Conto" />
                                <select
                                    id="account_id"
                                    className={clsx(
                                        'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                                        (isTransfer || isInterHouseholdTransfer) && 'cursor-not-allowed opacity-60'
                                    )}
                                    value={data.account_id}
                                    onChange={(e) => setData('account_id', e.target.value)}
                                    required
                                    disabled={isTransfer || isInterHouseholdTransfer}
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
                            {!isInterHouseholdTransfer && (
                            <div>
                                <InputLabel htmlFor="category_id" value="Categoria" />
                                {isTransfer ? (
                                    <div className="mt-2">
                                        <div className={clsx(
                                            'flex items-center space-x-3 rounded-lg border-2 p-3 cursor-not-allowed opacity-60',
                                            isExpense
                                                ? 'border-red-500 bg-red-50 dark:bg-red-900/20'
                                                : 'border-green-500 bg-green-50 dark:bg-green-900/20'
                                        )}>
                                            <span className="text-2xl">{selectedCategory?.icon || (isExpense ? '💸' : '💰')}</span>
                                            <div>
                                                <p className="font-medium text-gray-900 dark:text-white">
                                                    {selectedCategory?.name}
                                                </p>
                                                <p className={clsx(
                                                    'text-xs',
                                                    isExpense
                                                        ? 'text-red-600 dark:text-red-400'
                                                        : 'text-green-600 dark:text-green-400'
                                                )}>
                                                    {isExpense ? 'Uscita' : 'Entrata'} (non modificabile)
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    <CategoryPicker
                                        categories={categories}
                                        value={data.category_id}
                                        onChange={(categoryId) => setData('category_id', categoryId)}
                                        error={errors.category_id}
                                        className="mt-2"
                                    />
                                )}
                            </div>
                            )}

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
                                            disabled={isInterHouseholdTransfer}
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
                                        disabled={isInterHouseholdTransfer}
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
                                    disabled={isInterHouseholdTransfer}
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
                                        disabled={isInterHouseholdTransfer}
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
                            {isExpense && !isInterHouseholdTransfer && (
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
                                                    onChange={(e) => {
                                                        setData('tax_deduction_type', e.target.value);
                                                        // Auto-imposta la percentuale di default
                                                        const selectedType = TAX_DEDUCTION_TYPES.find(t => t.value === e.target.value);
                                                        if (selectedType) {
                                                            setData('tax_deduction_rate', String(selectedType.defaultRate));
                                                        }
                                                    }}
                                                    required={data.is_tax_deductible}
                                                >
                                                    <option value="">Seleziona tipo</option>
                                                    {TAX_DEDUCTION_TYPES.map((type) => (
                                                        <option key={type.value} value={type.value}>
                                                            {type.label}
                                                        </option>
                                                    ))}
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
                                                💡 Potrai allegare documenti (scontrini, fatture) nella pagina di dettaglio.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Collega a Debito/Credito */}
                            {debtsCredits.length > 0 && !isTransfer && !isInterHouseholdTransfer && (
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
                                            Seleziona prima una categoria per filtrare i debiti/crediti pertinenti.
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
                                    {isInterHouseholdTransfer ? 'Torna Indietro' : 'Annulla'}
                                </Link>
                                {!isInterHouseholdTransfer && (
                                    <PrimaryButton disabled={processing}>
                                        {processing ? 'Salvataggio...' : 'Salva Modifiche'}
                                    </PrimaryButton>
                                )}
                            </div>
                        </form>
                    </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
