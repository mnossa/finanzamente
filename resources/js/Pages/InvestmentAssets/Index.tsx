import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import React from 'react';
import CardBox from '@/Components/CardBox';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';

interface Currency {
    code: string;
    symbol: string;
}

interface InvestmentAsset {
    id: number;
    type: string;
    type_label: string;
    type_icon: string;
    symbol: string | null;
    name: string;
    currency: Currency;
    investments_count: number;
}

interface TypeStat {
    label: string;
    icon: string;
    count: number;
}

interface Stats {
    total_assets: number;
    by_type: TypeStat[];
}

interface Types {
    [key: string]: string;
}

interface TypeIcons {
    [key: string]: string;
}

interface IndexProps {
    assets: InvestmentAsset[];
    groupedAssets: { [key: string]: InvestmentAsset[] };
    stats: Stats;
    types: Types;
    typeIcons: TypeIcons;
}

function TypeBadge({ type, typeLabel, typeIcon }: { type: string; typeLabel: string; typeIcon: string }) {
    const colors: Record<string, string> = {
        crypto: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
        etf: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        stock: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        index: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
        commodity: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        insurance: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
        other: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    };

    return (
        <span className={clsx('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium', colors[type] || colors.other)}>
            {typeIcon} {typeLabel}
        </span>
    );
}

export default function Index({ assets, groupedAssets, stats, types, typeIcons }: IndexProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<{ id: number; name: string } | null>(null);

    const openDeleteDialog = (id: number, name: string) => {
        setDeleteTarget({ id, name });
        setDeleteDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deleteTarget) {
            router.delete(route('investment-assets.destroy', deleteTarget.id));
        }
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    const handleCancelDelete = () => {
        setDeleteDialogOpen(false);
        setDeleteTarget(null);
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Asset Finanziari"
                    actions={
                        <LinkButton
                            href={route('investment-assets.create')}
                            icon={<PlusIcon />}
                        >
                            Nuovo Asset
                        </LinkButton>
                    }
                />
            }
        >
            <Head title="Asset Finanziari" />

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Conferma eliminazione"
                description={deleteTarget ? `Sei sicuro di voler eliminare l'asset "${deleteTarget.name}"?` : undefined}
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={handleConfirmDelete}
                onCancel={handleCancelDelete}
            />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/* Statistiche */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <CardBox className="p-4 shadow-sm">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Totale Asset
                            </p>
                            <p className="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
                                {stats.total_assets}
                            </p>
                        </CardBox>
                        {stats.by_type.slice(0, 3).map((stat) => (
                            <CardBox key={stat.label} className="p-4 shadow-sm">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {stat.icon} {stat.label}
                                </p>
                                <p className="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
                                    {stat.count}
                                </p>
                            </CardBox>
                        ))}
                    </div>

                    {/* Lista Asset per Tipo */}
                    {Object.entries(groupedAssets).map(([type, typeAssets]) => (
                        <CardBox key={type} className="overflow-hidden shadow-sm">
                            <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                                <h3 className="font-semibold text-gray-900 dark:text-white">
                                    {typeIcons[type]} {types[type]} ({typeAssets.length})
                                </h3>
                            </div>
                            <div className="divide-y divide-gray-200 dark:divide-gray-700">
                                {typeAssets.map((asset) => (
                                    <div
                                        key={asset.id}
                                        className="flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-xl dark:bg-gray-700">
                                                {asset.type_icon}
                                            </div>
                                            <div>
                                                <p className="font-medium text-gray-900 dark:text-white">
                                                    {asset.name}
                                                    {asset.symbol && (
                                                        <span className="ml-2 text-sm text-gray-500 dark:text-gray-400">
                                                            ({asset.symbol})
                                                        </span>
                                                    )}
                                                </p>
                                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                                    {asset.currency.code} • {asset.investments_count} investimenti
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Link
                                                href={route('investment-assets.edit', asset.id)}
                                                className="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                                                title="Modifica"
                                            >
                                                <PencilIcon size={18} />
                                            </Link>
                                            {asset.investments_count === 0 && (
                                                <button
                                                    onClick={() => openDeleteDialog(asset.id, asset.name)}
                                                    className="rounded-lg p-2 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                                    title="Elimina"
                                                >
                                                    <TrashIcon size={18} />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardBox>
                    ))}

                    {/* Empty State */}
                    {assets.length === 0 && (
                        <CardBox className="overflow-hidden shadow-sm">
                            <EmptyState
                                icon="💼"
                                title="Nessun asset configurato"
                                description="Crea il tuo primo asset finanziario (azioni, ETF, crypto, ecc.) per iniziare a tracciare i tuoi investimenti."
                                createUrl={route('investment-assets.create')}
                                createLabel="Crea il Primo Asset"
                            />
                        </CardBox>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
