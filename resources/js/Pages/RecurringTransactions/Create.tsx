import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import CategoryPicker from '@/Components/CategoryPicker';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LinkButton from '@/Components/LinkButton';
import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
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
    frequencies: Frequencies;
    debtsCredits: DebtCredit[];
}

export default function Create({ accounts, categories, frequencies, debtsCredits }: CreateProps) {
    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        account_id: accounts.length > 0 ? String(accounts[0].id) : '',
        category_id: '',
        amount: '',
        frequency: 'monthly',
        start_date: today,
        end_date: '',
        description: '',
        debt_credit_id: '',
    });

    const selectedCategory = categories.find((c) => c.id === Number(data.category_id));
    const isExpense = selectedCategory?.type === 'expense';

    // Filtra debiti/crediti in base al tipo di categoria
    const filteredDebtsCredits = selectedCategory
        ? debtsCredits.filter((dc) => (isExpense ? dc.type === 'debt' : dc.type === 'credit'))
        : debtsCredits;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('recurring-transactions.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Nuova Transazione Ricorrente"
                />
            }
        >
            <Head title="Nuova Transazione Ricorrente" />

            <PageContent maxWidth="2xl">
                    <CardBox>
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
                            <form onSubmit={submit} className="space-y-4">
                                {/* Conto */}
                                <div>
                                    <InputLabel htmlFor="account_id" value="Conto" />
                                        <CardBox>
                                            <select
                                                id="account_id"
                                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
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
                                        </CardBox>
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
                                            className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
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
                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        rows={2}
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="es. Affitto mensile, Abbonamento Netflix"
                                    />
                                    <InputError message={errors.description} className="mt-2" />
                                </div>

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
                                                className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
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
                                                Ogni transazione generata aggiornerà automaticamente il saldo del {isExpense ? 'debito' : 'credito'}.
                                            </p>
                                        )}
                                        <InputError message={errors.debt_credit_id} className="mt-2" />
                                    </div>
                                )}

                                {/* Azioni */}
                                <FormActionsBar className="justify-end">
                                    <Link
                                        href={route('recurring-transactions.index')}
                                        className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        Annulla
                                    </Link>
                                    <PrimaryButton disabled={processing || !data.category_id}>
                                        {processing ? 'Salvataggio...' : 'Crea Ricorrenza'}
                                    </PrimaryButton>
                                </FormActionsBar>
                            </form>
                        )}
                    </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
