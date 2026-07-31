import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PencilIcon from '@/Components/Icons/PencilIcon';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import { Head, useForm } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import { moneyKpiGrid3, moneyTabular } from '@/utils/moneyGridClasses';
import { getAccountTypeIcon } from '@/Components/getAccountTypeIcon';

interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
}

interface MealVoucherMovement {
    lot_id: number;
    unit_value: number;
    quantity: number;
}

interface Transaction {
    id: number;
    amount: number;
    date: string;
    description: string | null;
    category: Category | null;
    user: {
        id: number;
        name: string;
    };
    tickets_delta: number | null;
    meal_voucher_movements?: MealVoucherMovement[];
}

interface Account {
    id: number;
    name: string;
    type: string;
    type_label: string;
    initial_balance: number;
    current_balance: number;
    currency_code: string;
    ticket_unit_value: number | null;
    ticket_count: number | null;
    external_url: string | null;
    is_pension_fund: boolean;
    active: boolean;
    is_private: boolean;
    created_at: string;
}

interface MealVoucherLot {
    id: number;
    unit_value: number;
    quantity_remaining: number;
    acquired_on: string;
    euro_value: number;
}

interface UnitValueHistoryRow {
    unit_value: number;
    effective_from: string;
}

interface ShowProps {
    account: Account;
    recentTransactions: Transaction[];
    mealVoucherLots?: MealVoucherLot[];
    mealVoucherUnitValueHistory?: UnitValueHistoryRow[];
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function formatTicketsDelta(delta: number): string {
    const formatted = new Intl.NumberFormat('it-IT', {
        maximumFractionDigits: 0,
    }).format(Math.abs(delta));
    const sign = delta > 0 ? '+' : delta < 0 ? '−' : '';
    return `${sign}${formatted} ticket`;
}

function TransactionRow({
    transaction,
    currency,
    showTickets,
}: {
    transaction: Transaction;
    currency: string;
    showTickets: boolean;
}) {
    const isIncome = transaction.amount > 0;
    return (
        <div className="flex items-center justify-between border-b border-gray-100 py-3 last:border-0 dark:border-gray-700">
            <div className="flex items-center space-x-3">
                <div
                    className="flex h-10 w-10 items-center justify-center rounded-full text-lg"
                    style={{
                        backgroundColor: transaction.category?.color
                            ? `${transaction.category.color}20`
                            : isIncome
                                ? '#22c55e20'
                                : '#ef444420',
                    }}
                >
                    {transaction.category?.icon || (isIncome ? '💰' : '💸')}
                </div>
                <div>
                    <p className="font-medium text-gray-900 dark:text-white">
                        {transaction.description || transaction.category?.name || 'Transazione'}
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {new Date(transaction.date).toLocaleDateString('it-IT')}
                    </p>
                    {showTickets && (transaction.meal_voucher_movements?.length ?? 0) > 0 && (
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            {transaction.meal_voucher_movements!
                                .map((m) => `${Math.abs(m.quantity)}×${formatCurrency(m.unit_value, currency)}`)
                                .join(' · ')}
                        </p>
                    )}
                </div>
            </div>
            <div className="text-right">
                <p
                    className={clsx(
                        'font-semibold',
                        moneyTabular,
                        isIncome ? 'text-green-500' : 'text-red-500'
                    )}
                >
                    {isIncome ? '+' : ''}
                    {formatCurrency(transaction.amount, currency)}
                </p>
                {showTickets && transaction.tickets_delta !== null && (
                    <p
                        className={clsx(
                            'text-xs',
                            moneyTabular,
                            transaction.tickets_delta > 0
                                ? 'text-green-600 dark:text-green-400'
                                : transaction.tickets_delta < 0
                                    ? 'text-red-600 dark:text-red-400'
                                    : 'text-gray-500 dark:text-gray-400'
                        )}
                    >
                        {formatTicketsDelta(transaction.tickets_delta)}
                    </p>
                )}
            </div>
        </div>
    );
}

function PensionFundPositionForm({
    accountId,
    currentBalance,
    currencyCode,
}: {
    accountId: number;
    currentBalance: number;
    currencyCode: string;
}) {
    const { data, setData, post, processing, errors } = useForm({
        position: String(currentBalance),
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('accounts.position.update', accountId), { preserveScroll: true });
            }}
            className="space-y-3"
        >
            <p className="text-sm text-gray-600 dark:text-gray-300">
                Inserisci il montante che vedi nell&apos;area riservata del fondo. I contributi aziendali e i
                rendimenti restano nel numero: niente entrate/uscite libere su questo conto. Per un versamento
                tuo dal corrente usa un trasferimento.
            </p>
            <div>
                <InputLabel htmlFor="position" value="Posizione attuale" />
                <TextInput
                    id="position"
                    type="number"
                    step="0.01"
                    min="0"
                    className="mt-1 block w-full"
                    value={data.position}
                    onChange={(e) => setData('position', e.target.value)}
                    required
                />
                <InputError message={errors.position} className="mt-2" />
            </div>
            <PrimaryButton type="submit" disabled={processing}>
                {processing ? 'Aggiornamento…' : 'Aggiorna posizione'}
            </PrimaryButton>
            <p className="text-xs text-gray-500 dark:text-gray-400">
                Saldo attuale: {formatCurrency(currentBalance, currencyCode)}
            </p>
        </form>
    );
}

function MealVoucherUnitValueForm({ accountId }: { accountId: number }) {
    const today = new Date().toISOString().split('T')[0];
    const { data, setData, post, processing, errors, reset } = useForm({
        unit_value: '',
        effective_from: today,
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(route('accounts.meal-voucher-unit-value.store', accountId), {
                    preserveScroll: true,
                    onSuccess: () => reset('unit_value'),
                });
            }}
            className="space-y-3"
        >
            <p className="text-sm text-gray-600 dark:text-gray-300">
                Dal giorno indicato i nuovi accrediti useranno questo valore. Puoi impostare anche date
                passate per categorizzare movimenti storici con l&apos;importo corretto. I ticket già in
                cassa non cambiano.
            </p>
            <div className="grid gap-3 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor="unit_value" value="Nuovo valore ticket" />
                    <TextInput
                        id="unit_value"
                        type="number"
                        step="0.01"
                        min="0.01"
                        className="mt-1 block w-full"
                        value={data.unit_value}
                        onChange={(e) => setData('unit_value', e.target.value)}
                        required
                    />
                    <InputError message={errors.unit_value} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="effective_from" value="Valido dal" />
                    <TextInput
                        id="effective_from"
                        type="date"
                        className="mt-1 block w-full"
                        value={data.effective_from}
                        onChange={(e) => setData('effective_from', e.target.value)}
                        required
                    />
                    <InputError message={errors.effective_from} className="mt-1" />
                </div>
            </div>
            <PrimaryButton disabled={processing}>
                {processing ? 'Salvataggio...' : 'Salva valore'}
            </PrimaryButton>
        </form>
    );
}

export default function Show({
    account,
    recentTransactions,
    mealVoucherLots = [],
    mealVoucherUnitValueHistory = [],
}: ShowProps) {
    const isMealVoucher = account.type === 'meal_voucher';
    const isPensionFund = account.is_pension_fund || account.type === 'pension_fund';

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`Conto: ${account.name}`}
                    backLink={route('accounts.index')}
                    actions={
                        <LinkButton href={route('accounts.edit', account.id)} icon={<PencilIcon />}>
                            Modifica
                        </LinkButton>
                    }
                />
            }
        >
            <Head title={account.name} />

            <PageContent maxWidth="5xl">
                    <IndexPageMobileToolbar>
                        <LinkButton href={route('accounts.edit', account.id)} icon={<PencilIcon />}>
                            Modifica
                        </LinkButton>
                    </IndexPageMobileToolbar>
                    <SectionCard className="hidden sm:block bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge
                                label="Dettaglio conto"
                                icon={<span className="text-sm leading-none">{getAccountTypeIcon(account.type)}</span>}
                            />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                {isMealVoucher
                                    ? 'Saldo in euro, lotti di buoni pasto e ultime operazioni.'
                                    : isPensionFund
                                        ? 'Posizione del fondo pensione: aggiornala dal portale e usa i trasferimenti per i versamenti dal corrente.'
                                        : 'Stato del conto, saldi e ultime operazioni in un unico riepilogo.'}
                            </p>
                        </div>
                    </SectionCard>
                    <div className={moneyKpiGrid3}>
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {isPensionFund ? 'Posizione' : 'Saldo corrente'}
                            </p>
                            <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                {formatCurrency(account.current_balance, account.currency_code)}
                            </p>
                        </CardBox>
                        {isMealVoucher ? (
                            <>
                                <CardBox className="p-4 shadow-sm">
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Ticket disponibili
                                    </p>
                                    <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                        {account.ticket_count ?? 0}
                                    </p>
                                </CardBox>
                                <CardBox className="p-4 shadow-sm">
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Valore ticket vigente
                                    </p>
                                    <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                        {account.ticket_unit_value !== null
                                            ? formatCurrency(account.ticket_unit_value, account.currency_code)
                                            : '—'}
                                    </p>
                                </CardBox>
                            </>
                        ) : isPensionFund ? (
                            <>
                                <CardBox className="p-4 shadow-sm">
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Tipo
                                    </p>
                                    <p className="mt-1 text-lg font-bold text-gray-900 dark:text-white">
                                        {account.type_label}
                                    </p>
                                </CardBox>
                                <CardBox className="p-4 shadow-sm">
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Creato il
                                    </p>
                                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                                        {account.created_at}
                                    </p>
                                </CardBox>
                            </>
                        ) : (
                            <>
                                <CardBox className="p-4 shadow-sm">
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Saldo iniziale
                                    </p>
                                    <p className={clsx('mt-1 text-2xl font-bold text-gray-900 dark:text-white', moneyTabular)}>
                                        {formatCurrency(account.initial_balance, account.currency_code)}
                                    </p>
                                </CardBox>
                                <CardBox className="p-4 shadow-sm">
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Creato il
                                    </p>
                                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-white">
                                        {account.created_at}
                                    </p>
                                </CardBox>
                            </>
                        )}
                    </div>

                    {isPensionFund && (
                        <CardBox className="overflow-hidden p-4 shadow-sm">
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <h3 className="font-semibold text-gray-900 dark:text-white">Aggiorna posizione</h3>
                                {account.external_url && (
                                    <a
                                        href={account.external_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-sm font-medium text-emerald-600 underline hover:text-emerald-700 dark:text-emerald-400"
                                    >
                                        Apri area riservata
                                    </a>
                                )}
                            </div>
                            <PensionFundPositionForm
                                accountId={account.id}
                                currentBalance={account.current_balance}
                                currencyCode={account.currency_code}
                            />
                            <div className="mt-4 border-t border-gray-100 pt-3 dark:border-gray-700">
                                <LinkButton href={route('transfers.create')}>
                                    Trasferimento da/verso il fondo
                                </LinkButton>
                            </div>
                        </CardBox>
                    )}

                    {isMealVoucher && (
                        <>
                            <CardBox className="overflow-hidden p-4 shadow-sm">
                                <h3 className="mb-3 font-semibold text-gray-900 dark:text-white">Lotti in cassa</h3>
                                {mealVoucherLots.length === 0 ? (
                                    <p className="text-sm text-gray-500 dark:text-gray-400">Nessun ticket in cassa.</p>
                                ) : (
                                    <ul className="space-y-2">
                                        {mealVoucherLots.map((lot) => (
                                            <li
                                                key={lot.id}
                                                className="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2 dark:border-gray-700"
                                            >
                                                <div>
                                                    <p className="text-sm font-medium text-gray-900 dark:text-white">
                                                        {lot.quantity_remaining} ticket da{' '}
                                                        {formatCurrency(lot.unit_value, account.currency_code)}
                                                    </p>
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                                        Acquisiti il {new Date(lot.acquired_on).toLocaleDateString('it-IT')}
                                                    </p>
                                                </div>
                                                <p className={clsx('text-sm font-semibold', moneyTabular)}>
                                                    {formatCurrency(lot.euro_value, account.currency_code)}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardBox>

                            <CardBox className="overflow-hidden p-4 shadow-sm">
                                <h3 className="mb-3 font-semibold text-gray-900 dark:text-white">
                                    Storico valore ticket
                                </h3>
                                {mealVoucherUnitValueHistory.length > 0 && (
                                    <ul className="mb-4 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                                        {mealVoucherUnitValueHistory.map((row) => (
                                            <li key={row.effective_from}>
                                                Dal {new Date(row.effective_from).toLocaleDateString('it-IT')}:{' '}
                                                <span className="font-medium text-gray-900 dark:text-white">
                                                    {formatCurrency(row.unit_value, account.currency_code)}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <MealVoucherUnitValueForm accountId={account.id} />
                            </CardBox>
                        </>
                    )}

                    <CardBox className="overflow-hidden shadow-sm">
                        <div className="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-700">
                            <h3 className="font-semibold text-gray-900 dark:text-white">
                                Ultime transazioni
                            </h3>
                            {isMealVoucher && (
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Ticket interi per movimento
                                </p>
                            )}
                        </div>
                        <div className="p-4">
                            {recentTransactions.length > 0 ? (
                                recentTransactions.map((transaction) => (
                                    <TransactionRow
                                        key={transaction.id}
                                        transaction={transaction}
                                        currency={account.currency_code}
                                        showTickets={isMealVoucher}
                                    />
                                ))
                            ) : (
                                <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                                    Nessuna transazione recente.
                                </div>
                            )}
                        </div>
                    </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
