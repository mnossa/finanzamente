import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import CardBox from '@/Components/CardBox';
import React from 'react';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';

interface Tag {
    id: number;
    name: string;
    color: string;
    transactions_count: number;
    created_at: string;
}

interface IndexProps {
    tags: Tag[];
}

function TagCard({ tag, onDeleteClick }: { tag: Tag; onDeleteClick: (id: number, name: string) => void }) {
    return (
        <CardBox className="p-4 shadow-sm transition-shadow hover:shadow-md">
            <div className="flex items-start justify-between">
                <div className="flex items-center space-x-3">
                    <div
                        className="flex h-10 w-10 items-center justify-center rounded-full"
                        style={{ backgroundColor: tag.color }}
                    >
                        <span className="text-lg text-white">🏷️</span>
                    </div>
                    <div>
                        <h3 className="font-semibold text-gray-900 dark:text-white">
                            {tag.name}
                        </h3>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {tag.transactions_count}{' '}
                            {tag.transactions_count === 1
                                ? 'transazione'
                                : 'transazioni'}
                        </p>
                    </div>
                </div>
                <div
                    className="h-4 w-4 rounded-full border-2 border-white shadow-sm"
                    style={{ backgroundColor: tag.color }}
                />
            </div>
            <div className="mt-3 flex justify-end space-x-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                <Link
                    href={route('tags.edit', tag.id)}
                    className="rounded p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                    title="Modifica"
                >
                    <PencilIcon size={18} />
                </Link>
                <button
                    onClick={() => onDeleteClick(tag.id, tag.name)}
                    className="rounded p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                    title="Elimina"
                >
                    <TrashIcon size={18} />
                </button>
            </div>
        </CardBox>
    );
}

export default function Index({ tags }: IndexProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = React.useState(false);
    const [deleteTarget, setDeleteTarget] = React.useState<{ id: number; name: string } | null>(null);

    const openDeleteDialog = (id: number, name: string) => {
        setDeleteTarget({ id, name });
        setDeleteDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deleteTarget) {
            router.delete(route('tags.destroy', deleteTarget.id));
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
                    title="Tag"
                    actions={
                        <LinkButton
                            href={route('tags.create')}
                            icon={<PlusIcon />}
                        >
                            Nuovo Tag
                        </LinkButton>
                    }
                />
            }
        >
            <Head title="Tag" />

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Conferma eliminazione"
                description={deleteTarget ? `Sei sicuro di voler eliminare il tag "${deleteTarget.name}"?` : undefined}
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={handleConfirmDelete}
                onCancel={handleCancelDelete}
            />

            <PageContent maxWidth="7xl">
                    <SectionCard className="bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge label="Tag" icon={<span className="text-sm leading-none">🏷️</span>} />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Organizza le transazioni con etichette rapide e facili da filtrare.
                            </p>
                        </div>
                    </SectionCard>
                    {tags.length === 0 ? (
                        <CardBox className="overflow-hidden shadow-sm">
                            <EmptyState
                                icon="🏷️"
                                title="Nessun tag trovato"
                                description="Crea il tuo primo tag per organizzare le tue transazioni."
                                createUrl={route('tags.create')}
                                createLabel="Crea il tuo primo tag"
                            />
                        </CardBox>
                    ) : (
                        <>
                            {/* Riepilogo */}
                            <div className="overflow-hidden rounded-xl bg-linear-to-br from-purple-500 to-pink-600 p-6 text-white shadow-lg">
                                <h3 className="text-sm font-medium text-purple-100">
                                    Totale Tag
                                </h3>
                                <p className="mt-2 text-4xl font-bold">{tags.length}</p>
                                <p className="mt-1 text-sm text-purple-200">
                                    {tags.reduce((acc, t) => acc + t.transactions_count, 0)}{' '}
                                    transazioni taggate
                                </p>
                            </div>

                            {/* Lista Tag */}
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {tags.map((tag) => (
                                    <TagCard key={tag.id} tag={tag} onDeleteClick={openDeleteDialog} />
                                ))}
                            </div>
                        </>
                    )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
