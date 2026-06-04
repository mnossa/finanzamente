import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import PageContent from '@/Components/PageContent';
import CardBox from '@/Components/CardBox';
import LinkButton from '@/Components/LinkButton';
import { IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import PlusIcon from '@/Components/Icons/PlusIcon';
import EyeIcon from '@/Components/Icons/EyeIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import { Head, Link, router } from '@inertiajs/react';
import React from 'react';
import { formatCurrency } from '@/utils/format';

interface Pac {
    id: number;
    amount: number;
    adjust_for_inflation: boolean;
    inflation_rate_annual: number | null;
    currency_code: string;
    frequency: string;
    start_date: string;
    end_date: string | null;
    last_executed_at: string | null;
    status: string;
    notes: string | null;
    investments_count: number;
    asset: { id: number; name: string; symbol: string; isin: string | null };
    account: { id: number; name: string } | null;
}

export default function InvestmentPacIndex({ pacs }: { pacs: Pac[] }) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<{ id: number; description: string } | null>(null);

    const openDeleteDialog = (id: number, description: string) => {
        setDeleteTarget({ id, description });
        setDeleteDialogOpen(true);
    };

    const closeDeleteDialog = () => {
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('investment-pacs.destroy', deleteTarget.id));
        closeDeleteDialog();
    };

    const runNow = (pacId: number) => {
        router.post(route('investment-pacs.run-now', pacId));
    };

    const toggleStatus = (pacId: number) => {
        router.post(route('investment-pacs.toggle-status', pacId));
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="PAC — Piani di accumulo"
                    actions={<LinkButton href={route('investment-pacs.create')}>Nuovo PAC</LinkButton>}
                />
            }
        >
            <Head title="PAC Investimenti" />
            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Elimina PAC"
                description={deleteTarget ? `Confermi l'eliminazione di ${deleteTarget.description}? I movimenti già generati restano disponibili in Investimenti.` : undefined}
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={confirmDelete}
                onCancel={closeDeleteDialog}
            />
            <PageContent>
                <IndexPageMobileToolbar>
                    <LinkButton
                        href={route('investment-pacs.create')}
                        icon={<PlusIcon />}
                        size="sm"
                        className="w-full justify-center sm:w-auto"
                    >
                        Nuovo PAC
                    </LinkButton>
                </IndexPageMobileToolbar>
                <CardBox className="p-4">
                    <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        Versamenti mensili automatici su ETF, fondi o altri strumenti. Ogni esecuzione genera un movimento di acquisto nello storico investimenti.
                    </p>
                    <div className="space-y-3">
                        {pacs.length === 0 && (
                            <p className="text-sm text-gray-500 dark:text-gray-400">Nessun PAC configurato. Crea il primo piano di accumulo.</p>
                        )}
                        {pacs.map((pac) => (
                            <div key={pac.id} className="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-semibold text-gray-900 dark:text-white">
                                                {pac.asset.name} ({pac.asset.isin ?? 'ISIN n/d'})
                                            </p>
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${pac.status === 'active'
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                                            }`}
                                            >
                                                {pac.status === 'active' ? 'Attivo' : 'In pausa'}
                                            </span>
                                        </div>
                                        <p className="text-sm text-gray-600 dark:text-gray-300">
                                            {formatCurrency(pac.amount, pac.currency_code)} / mese
                                            {pac.adjust_for_inflation && pac.inflation_rate_annual !== null && (
                                                <span className="ml-2 text-emerald-700 dark:text-emerald-400">
                                                    +{pac.inflation_rate_annual.toFixed(1)}% annuo (inflazione)
                                                </span>
                                            )}
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            Inizio {pac.start_date}
                                            {pac.end_date ? ` — Fine ${pac.end_date}` : ''}
                                            {pac.last_executed_at ? ` · Ultimo versamento ${pac.last_executed_at}` : ''}
                                            {pac.account ? ` · Conto ${pac.account.name}` : ''}
                                            {` · Movimenti ${pac.investments_count}`}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <button
                                            type="button"
                                            onClick={() => runNow(pac.id)}
                                            disabled={pac.status !== 'active'}
                                            className="rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-medium text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            Esegui ora
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => toggleStatus(pac.id)}
                                            className="rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            {pac.status === 'active' ? 'Metti in pausa' : 'Riattiva'}
                                        </button>
                                        <Link
                                            href={route('investment-pacs.show', pac.id)}
                                            className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-emerald-600 dark:hover:bg-gray-700 dark:hover:text-emerald-400"
                                            title="Visualizza"
                                        >
                                            <EyeIcon size={16} />
                                        </Link>
                                        <Link
                                            href={route('investment-pacs.edit', pac.id)}
                                            className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                                            title="Modifica"
                                        >
                                            <PencilIcon size={16} />
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => openDeleteDialog(pac.id, pac.asset.name)}
                                            className="rounded p-1 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                                            title="Elimina"
                                        >
                                            <TrashIcon size={16} />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
