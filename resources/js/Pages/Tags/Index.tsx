import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions } from '@/Components/IndexPageListToolbars';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import IndexIntroSection from '@/Components/Index/IndexIntroSection';
import IndexEntityCard, {
    IndexEntityCardFooterButton,
    IndexEntityCardFooterLink,
} from '@/Components/Index/IndexEntityCard';
import { Head, router } from '@inertiajs/react';
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
        <IndexEntityCard
            icon={<span className="text-lg text-white">🏷️</span>}
            iconClassName="flex h-10 w-10 items-center justify-center rounded-full"
            iconStyle={{ backgroundColor: tag.color }}
            title={tag.name}
            subtitle={`${tag.transactions_count} ${tag.transactions_count === 1 ? 'transazione' : 'transazioni'}`}
            aside={
                <span
                    className="mt-1 block h-4 w-4 rounded-full border-2 border-white shadow-sm"
                    style={{ backgroundColor: tag.color }}
                    aria-hidden
                />
            }
            footer={
                <>
                    <IndexEntityCardFooterLink href={route('tags.edit', tag.id)} title="Modifica">
                        <PencilIcon size={16} />
                    </IndexEntityCardFooterLink>
                    <IndexEntityCardFooterButton
                        onClick={() => onDeleteClick(tag.id, tag.name)}
                        title="Elimina"
                        className="hover:text-red-600 dark:hover:text-red-400"
                    >
                        <TrashIcon size={16} />
                    </IndexEntityCardFooterButton>
                </>
            }
        />
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
                        <IndexPageHeaderActions>
                            <LinkButton href={route('tags.create')} icon={<PlusIcon />}>
                                Nuovo Tag
                            </LinkButton>
                        </IndexPageHeaderActions>
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
                    <IndexIntroSection
                        label="Tag"
                        icon={<span className="text-sm leading-none">🏷️</span>}
                        description="Organizza le transazioni con etichette rapide e facili da filtrare."
                    />
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
