import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import OrganizationHubNav from '@/Components/OrganizationHubNav';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions } from '@/Components/IndexPageListToolbars';
import IndexCardGrid from '@/Components/Index/IndexCardGrid';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import ArchiveIcon from '@/Components/Icons/ArchiveIcon';
import EmptyState from '@/Components/EmptyState';
import CardBox from '@/Components/CardBox';
import IndexEntityCard, {
    IndexEntityCardFooterButton,
    IndexEntityCardFooterLink,
} from '@/Components/Index/IndexEntityCard';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';
import { moneyTabular } from '@/utils/moneyGridClasses';
import { getAccountTypeIcon } from '@/Components/getAccountTypeIcon';

interface Account {
    id: number;
    name: string;
    type: string;
    type_label: string;
    initial_balance: number;
    current_balance: number;
    currency_code: string;
    active: boolean;
    is_private: boolean;
    owner: { id: number; name: string } | null;
    created_at: string;
}

interface IndexProps {
    accounts: Account[];
    totalBalance: number;
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}


function AccountCard({ account }: { account: Account }) {
    const showInitialBalance = account.current_balance !== account.initial_balance;

    return (
        <IndexEntityCard
            href={route('accounts.show', account.id)}
            icon={getAccountTypeIcon(account.type)}
            dimmed={!account.active}
            title={
                <>
                    {account.name}
                    {account.is_private && (
                        <span className="ml-1 text-xs text-gray-400">🔒</span>
                    )}
                    {!account.active && (
                        <span className="ml-1.5 rounded bg-gray-200 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-400 sm:text-xs">
                            Archiviato
                        </span>
                    )}
                </>
            }
            subtitle={account.type_label}
            amount={formatCurrency(account.current_balance, account.currency_code)}
            amountClassName={
                account.current_balance >= 0 ? 'text-gray-900 dark:text-white' : 'text-red-500'
            }
            amountDetail={
                showInitialBalance ? (
                    <p className={clsx('text-[11px] text-gray-400 sm:text-xs', moneyTabular)}>
                        Iniziale: {formatCurrency(account.initial_balance, account.currency_code)}
                    </p>
                ) : undefined
            }
            footer={
                <>
                    <IndexEntityCardFooterLink
                        href={route('accounts.edit', account.id)}
                        title="Modifica"
                    >
                        <PencilIcon size={16} />
                    </IndexEntityCardFooterLink>
                    <IndexEntityCardFooterButton
                        onClick={() => router.post(route('accounts.toggle-active', account.id))}
                        title={account.active ? 'Archivia' : 'Riattiva'}
                    >
                        <ArchiveIcon size={16} />
                    </IndexEntityCardFooterButton>
                </>
            }
        />
    );
}

export default function Index({
    accounts,
    totalBalance,
}: IndexProps) {
    const activeAccounts = accounts.filter((a) => a.active);
    const archivedAccounts = accounts.filter((a) => !a.active);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="I tuoi Conti"
                    actions={
                        <IndexPageHeaderActions>
                            <LinkButton href={route('accounts.create')} icon={<PlusIcon />}>
                                Nuovo Conto
                            </LinkButton>
                        </IndexPageHeaderActions>
                    }
                />
            }
        >
            <Head title="Conti" />

            <PageContent maxWidth="7xl">
                    <OrganizationHubNav active="accounts" />
                    {accounts.length === 0 ? (
                        <CardBox className="overflow-hidden shadow-sm">
                            <EmptyState
                                icon="🏦"
                                title="Nessun conto trovato"
                                description="Crea il tuo primo conto per iniziare a monitorare le tue finanze."
                                createUrl={route('accounts.create')}
                                createLabel="Crea il tuo primo conto"
                            />
                        </CardBox>
                    ) : (
                        <>
                            {/* Saldo conti */}
                            <div className="overflow-hidden rounded-2xl bg-linear-to-br from-slate-800 to-slate-900 p-3 text-white shadow-lg sm:p-6">
                                <h3 className="text-xs font-medium text-slate-300 sm:text-sm">
                                    Saldo conti
                                </h3>
                                <p className={clsx('mt-1 text-2xl font-bold sm:mt-2 sm:text-4xl', moneyTabular)}>
                                    {formatCurrency(totalBalance)}
                                </p>
                                <p className="mt-0.5 text-xs text-slate-400 sm:mt-1 sm:text-sm">
                                    {activeAccounts.length} {activeAccounts.length === 1 ? 'conto attivo' : 'conti attivi'}
                                </p>
                            </div>

                            {/* Lista Conti Attivi */}
                            {activeAccounts.length > 0 && (
                                <div>
                                    <h3 className="mb-2 text-base font-semibold text-gray-900 dark:text-white sm:mb-4 sm:text-lg">
                                        Conti Attivi
                                    </h3>
                                    <IndexCardGrid className="gap-2 lg:grid-cols-2 xl:grid-cols-3 sm:gap-3">
                                        {activeAccounts.map((account) => (
                                            <AccountCard key={account.id} account={account} />
                                        ))}
                                    </IndexCardGrid>
                                </div>
                            )}

                            {/* Lista Conti Archiviati */}
                            {archivedAccounts.length > 0 && (
                                <div>
                                    <h3 className="mb-2 text-base font-semibold text-gray-500 dark:text-gray-400 sm:mb-4 sm:text-lg">
                                        Conti Archiviati
                                    </h3>
                                    <IndexCardGrid className="gap-2 lg:grid-cols-2 xl:grid-cols-3 sm:gap-3">
                                        {archivedAccounts.map((account) => (
                                            <AccountCard key={account.id} account={account} />
                                        ))}
                                    </IndexCardGrid>
                                </div>
                            )}
                        </>
                    )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
