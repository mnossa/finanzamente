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
import PageHeader from '@/Components/PageHeader';

interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
}

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface OriginalTransaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    account: Account | null;
    category: Category | null;
}

interface Refund {
    id: number;
    amount: number;
    max_refundable: number;
    description: string | null;
    date: string;
    is_private: boolean;
    original_transaction: OriginalTransaction;
}

interface EditProps {
    refund: Refund;
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('it-IT', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

export default function Edit({ refund }: EditProps) {
    const originalTx = refund.original_transaction;

    const { data, setData, patch, processing, errors } = useForm({
        amount: refund.amount.toString(),
        date: refund.date,
        description: refund.description || '',
        is_private: refund.is_private,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('refunds.update', refund.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Modifica Rimborso"
                    backLink={route('refunds.index')}
                />
            }
        >
            <Head title="Modifica Rimborso" />

            <PageContent maxWidth="2xl">
                    <SectionCard className="space-y-4">
                        <header className="hidden sm:block space-y-2">
                            <SectionBadge label="Rimborsi" icon={<span className="text-sm leading-none">✏️</span>} />
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Modifica rimborso</h2>
                            <p className="text-sm text-gray-600 dark:text-gray-400">Aggiorna importo, data e visibilità del rimborso registrato.</p>
                        </header>
                        <form id={FM_MOBILE_PRIMARY_FORM_ID} onSubmit={submit} className="space-y-4">
                            {/* Transazione originale (solo lettura) */}
                            <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                                <h4 className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Transazione rimborsata:
                                </h4>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-3">
                                        <div
                                            className="flex h-10 w-10 items-center justify-center rounded-full text-lg"
                                            style={{
                                                backgroundColor: originalTx.category?.color
                                                    ? `${originalTx.category.color}20`
                                                    : '#ef444420',
                                            }}
                                        >
                                            {originalTx.category?.icon || '💸'}
                                        </div>
                                        <div>
                                            <p className="font-medium text-gray-900 dark:text-white">
                                                {originalTx.description || originalTx.category?.name || 'Transazione'}
                                            </p>
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                {originalTx.account?.name} • {formatDate(originalTx.date)}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-lg font-semibold text-red-600 dark:text-red-400">
                                            {formatCurrency(originalTx.amount, originalTx.account?.currency_code || 'EUR')}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Importo */}
                            <div>
                                <InputLabel htmlFor="amount" value="Importo del rimborso" />
                                <div className="mt-1 flex items-center space-x-2">
                                    <TextInput
                                        id="amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        max={refund.max_refundable}
                                        className="block flex-1"
                                        value={data.amount}
                                        onChange={(e) => setData('amount', e.target.value)}
                                        required
                                    />
                                    <span className="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {originalTx.account?.currency_code || 'EUR'}
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => setData('amount', refund.max_refundable.toFixed(2))}
                                        className="rounded-md bg-gray-100 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                                    >
                                        Max
                                    </button>
                                </div>
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Massimo rimborsabile: {formatCurrency(refund.max_refundable, originalTx.account?.currency_code || 'EUR')}
                                </p>
                                <InputError message={errors.amount} className="mt-2" />
                            </div>

                            {/* Data */}
                            <div>
                                <InputLabel htmlFor="date" value="Data del rimborso" />
                                <TextInput
                                    id="date"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.date}
                                    onChange={(e) => setData('date', e.target.value)}
                                />
                                <InputError message={errors.date} className="mt-2" />
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
                                />
                                <InputError message={errors.description} className="mt-2" />
                            </div>

                            {/* Privato */}
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
                                        🔒 Rimborso privato
                                    </label>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Solo tu potrai vedere questo rimborso e la transazione collegata.
                                    </p>
                                </div>
                            </div>

                            {/* Azioni */}
                            <FormActionsBar className="justify-end">
                                <Link
                                    href={route('refunds.show', refund.id)}
                                    className="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                >
                                    Annulla
                                </Link>
                                <PrimaryButton
                                    disabled={processing || !data.amount || Number(data.amount) > refund.max_refundable}
                                >
                                    {processing ? 'Salvataggio...' : 'Salva Modifiche'}
                                </PrimaryButton>
                            </FormActionsBar>
                        </form>
                    </SectionCard>
            </PageContent>
        </AuthenticatedLayout>
    );
}
