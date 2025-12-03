import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LinkButton from '@/Components/LinkButton';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';

interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
    type: 'income' | 'expense';
}

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface Frequencies {
    [key: string]: string;
}

interface RecurringTransaction {
    id: number;
    amount: number;
    frequency: string;
    frequency_label: string;
    start_date: string;
    end_date: string | null;
    description: string | null;
    next_due_date: string | null;
    is_active: boolean;
    generated_count: number;
    created_at: string;
    updated_at: string;
    category: Category | null;
    account: Account;
    user: {
        id: number;
        name: string;
    };
}

interface ShowProps {
    recurringTransaction: RecurringTransaction;
    frequencies: Frequencies;
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('it-IT', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

function FrequencyBadge({ frequency, frequencyLabel }: { frequency: string; frequencyLabel: string }) {
    const colors: Record<string, string> = {
        daily: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
        weekly: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        monthly: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        yearly: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    };

    const icons: Record<string, string> = {
        daily: '📅',
        weekly: '📆',
        monthly: '🗓️',
        yearly: '📋',
    };

    return (
        <span
            className={clsx(
                'inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium',
                colors[frequency] || colors.monthly
            )}
        >
            {icons[frequency]} {frequencyLabel}
        </span>
    );
}

export default function Show({ recurringTransaction: rt, frequencies }: ShowProps) {
    const isIncome = rt.amount > 0;

    const handleGenerate = () => {
        if (confirm('Vuoi generare la prossima transazione adesso?')) {
            router.post(route('recurring-transactions.generate', rt.id));
        }
    };

    const handleDelete = () => {
        if (confirm('Sei sicuro di voler eliminare questa transazione ricorrente?')) {
            router.delete(route('recurring-transactions.destroy', rt.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center space-x-4">
                        <Link
                            href={route('recurring-transactions.index')}
                            className="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                        >
                            ←
                        </Link>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            Dettaglio Ricorrenza
                        </h2>
                    </div>
                    <LinkButton href={route('recurring-transactions.edit', rt.id)} icon="✏️">
                        Modifica
                    </LinkButton>
                </div>
            }
        >
            <Head title={`Ricorrenza - ${rt.description || rt.category?.name || 'Dettaglio'}`} />

            <div className="py-6">
                <div className="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Card principale */}
                    <div className="overflow-hidden rounded-xl bg-white p-6 shadow-sm dark:bg-gray-800">
                        <div className="text-center">
                            <div
                                className={clsx(
                                    'mx-auto flex h-16 w-16 items-center justify-center rounded-full text-3xl',
                                    !rt.is_active && 'opacity-50'
                                )}
                                style={{
                                    backgroundColor: rt.category?.color
                                        ? `${rt.category.color}20`
                                        : isIncome
                                        ? '#22c55e20'
                                        : '#ef444420',
                                }}
                            >
                                {rt.category?.icon || (isIncome ? '💰' : '💸')}
                            </div>
                            <h3 className={clsx(
                                'mt-4 text-xl font-semibold text-gray-900 dark:text-white',
                                !rt.is_active && 'line-through opacity-50'
                            )}>
                                {rt.description || rt.category?.name || 'Transazione Ricorrente'}
                            </h3>
                            <div className="mt-2 flex justify-center gap-2">
                                <FrequencyBadge frequency={rt.frequency} frequencyLabel={rt.frequency_label} />
                                {!rt.is_active && (
                                    <span className="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                        Terminata
                                    </span>
                                )}
                            </div>
                            <p
                                className={clsx(
                                    'mt-4 text-4xl font-bold',
                                    !rt.is_active && 'opacity-50',
                                    isIncome ? 'text-green-500' : 'text-red-500'
                                )}
                            >
                                {isIncome ? '+' : ''}
                                {formatCurrency(rt.amount, rt.account.currency_code)}
                            </p>
                            {rt.next_due_date && rt.is_active && (
                                <p className="mt-2 text-gray-500 dark:text-gray-400">
                                    Prossima: <span className="font-medium text-gray-900 dark:text-white">{formatDate(rt.next_due_date)}</span>
                                </p>
                            )}
                        </div>

                        {/* Dettagli */}
                        <div className="mt-8 space-y-4">
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Conto</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {rt.account.name}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Categoria</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {rt.category ? (
                                        <>
                                            {rt.category.icon} {rt.category.name}
                                        </>
                                    ) : (
                                        'Non categorizzata'
                                    )}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Tipo</span>
                                <span
                                    className={clsx(
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-medium',
                                        isIncome
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                    )}
                                >
                                    {isIncome ? '📥 Entrata' : '📤 Uscita'}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Data Inizio</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {formatDate(rt.start_date)}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Data Fine</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {rt.end_date ? formatDate(rt.end_date) : 'Nessuna (continua indefinitamente)'}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Transazioni Generate</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {rt.generated_count}
                                </span>
                            </div>
                            <div className="flex justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                <span className="text-gray-500 dark:text-gray-400">Creata da</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {rt.user.name}
                                </span>
                            </div>
                            <div className="flex justify-between pb-3">
                                <span className="text-gray-500 dark:text-gray-400">Data creazione</span>
                                <span className="font-medium text-gray-900 dark:text-white">
                                    {rt.created_at}
                                </span>
                            </div>
                        </div>

                        {rt.description && (
                            <div className="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                                <p className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    Descrizione
                                </p>
                                <p className="mt-1 text-gray-900 dark:text-white">
                                    {rt.description}
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Azioni */}
                    <div className="flex flex-wrap justify-center gap-3">
                        {rt.is_active && rt.next_due_date && (
                            <button
                                onClick={handleGenerate}
                                className="inline-flex items-center rounded-lg bg-emerald-600 px-6 py-3 text-sm font-medium text-white hover:bg-emerald-700"
                            >
                                ⚡ Genera Ora
                            </button>
                        )}
                        <LinkButton href={route('recurring-transactions.edit', rt.id)} size="lg" icon="✏️">
                            Modifica
                        </LinkButton>
                        <button
                            onClick={handleDelete}
                            className="inline-flex items-center rounded-lg border border-red-300 px-6 py-3 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
                        >
                            🗑️ Elimina
                        </button>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
