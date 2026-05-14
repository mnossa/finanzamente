import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions } from '@/Components/IndexPageListToolbars';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import { useState } from 'react';
import CardBox from '@/Components/CardBox';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';

interface Category {
    id: number;
    name: string;
    type: string;
    type_label: string;
    color: string | null;
    icon: string | null;
    is_fixed_expense: boolean;
    expense_distribution: 'needs' | 'wants' | 'investments' | null;
    transactions_count: number;
    created_at: string;
}

interface CategoryTypes {
    [key: string]: string;
}

interface ByType {
    income: Category[];
    expense: Category[];
}

interface IndexProps {
    categories: Category[];
    byType: ByType;
    categoryTypes: CategoryTypes;
}

function CategoryCard({
    category,
    onDelete,
}: {
    category: Category;
    onDelete: (id: number) => void;
}) {
    return (
        <div className="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
            {/* Top row: icon + name + actions */}
            <div className="flex items-start justify-between gap-2">
                <div className="flex items-center gap-3 min-w-0">
                    <span
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xl"
                        style={{ backgroundColor: category.color ? category.color + '20' : '#e5e7eb' }}
                    >
                        {category.icon || '📁'}
                    </span>
                    <h4 className="font-medium text-gray-900 dark:text-white truncate">
                        {category.name}
                    </h4>
                </div>
                <div className="flex items-center shrink-0 gap-1">
                    <Link
                        href={route('categories.edit', category.id)}
                        className="rounded p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                        title="Modifica"
                    >
                        <PencilIcon size={16} />
                    </Link>
                    <button
                        onClick={(e) => { e.preventDefault(); onDelete(category.id); }}
                        className="rounded p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                        title="Elimina"
                    >
                        <TrashIcon size={16} />
                    </button>
                </div>
            </div>

            {/* Bottom row: badges + transaction count */}
            <div className="flex flex-wrap items-center gap-1.5">
                {/* Utilizzo */}
                <span className={clsx(
                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                    category.transactions_count === 0
                        ? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                        : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
                )}>
                    {category.transactions_count === 0
                        ? '— Mai usata'
                        : `${category.transactions_count} transazion${category.transactions_count === 1 ? 'e' : 'i'}`}
                </span>

                {category.is_fixed_expense && (
                    <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        📌 Spesa Fissa
                    </span>
                )}
                {category.expense_distribution && (
                    <span className={clsx(
                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                        category.expense_distribution === 'needs'       && 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                        category.expense_distribution === 'wants'       && 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200',
                        category.expense_distribution === 'investments' && 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                    )}>
                        {category.expense_distribution === 'needs'       && '🏠 Necessità'}
                        {category.expense_distribution === 'wants'       && '🎯 Extra'}
                        {category.expense_distribution === 'investments' && '📈 Investimenti'}
                    </span>
                )}
            </div>
        </div>
    );
}

export default function Index({ categories, byType, categoryTypes }: IndexProps) {
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string } | null>(null);

    const openDeleteDialog = (id: number) => {
        const category = categories.find(c => c.id === id);
        if (category) {
            setDeleteTarget({ id, name: category.name });
            setDeleteDialogOpen(true);
        }
    };

    const handleConfirmDelete = () => {
        if (deleteTarget) {
            setDeletingId(deleteTarget.id);
            router.delete(route('categories.destroy', deleteTarget.id), {
                onFinish: () => setDeletingId(null),
            });
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
                    title="Categorie"
                    actions={
                        <IndexPageHeaderActions>
                            <LinkButton href={route('categories.create')} icon={<PlusIcon />}>
                                Nuova Categoria
                            </LinkButton>
                        </IndexPageHeaderActions>
                    }
                />
            }
        >
            <Head title="Categorie" />

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Conferma eliminazione"
                description={deleteTarget ? `Sei sicuro di voler eliminare la categoria "${deleteTarget.name}"?` : undefined}
                confirmLabel="Elimina"
                cancelLabel="Annulla"
                onConfirm={handleConfirmDelete}
                onCancel={handleCancelDelete}
            />

            <PageContent maxWidth="7xl">
                    <SectionCard className="hidden sm:block bg-linear-to-br from-emerald-50 via-white to-teal-50 dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20">
                        <div className="space-y-2">
                            <SectionBadge
                                label="Classificazione movimenti"
                                icon={<span className="text-sm leading-none">🏷️</span>}
                            />
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Organizza entrate e uscite con categorie pulite e coerenti per analisi migliori.
                            </p>
                        </div>
                    </SectionCard>
                    {categories.length === 0 ? (
                        <CardBox className="overflow-hidden shadow-sm">
                            <EmptyState
                                icon="📁"
                                title="Nessuna categoria trovata"
                                description="Crea le tue categorie per classificare entrate e uscite."
                                createUrl={route('categories.create')}
                                createLabel="Crea la tua prima categoria"
                            />
                        </CardBox>
                    ) : (
                        <>
                            {/* Categorie Entrate */}
                            {byType.income.length > 0 && (
                                <div>
                                    <h3 className="mb-3 flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-white">
                                        <span>📈</span>
                                        <span>Entrate</span>
                                        <span className="ml-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                            {byType.income.length}
                                        </span>
                                    </h3>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        {byType.income.map((category) => (
                                            <CategoryCard
                                                key={category.id}
                                                category={category}
                                                onDelete={openDeleteDialog}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Categorie Uscite */}
                            {byType.expense.length > 0 && (
                                <div>
                                    <h3 className="mb-3 flex items-center gap-2 text-base font-semibold text-gray-900 dark:text-white">
                                        <span>📉</span>
                                        <span>Uscite</span>
                                        <span className="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                            {byType.expense.length}
                                        </span>
                                    </h3>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        {byType.expense.map((category) => (
                                            <CategoryCard
                                                key={category.id}
                                                category={category}
                                                onDelete={openDeleteDialog}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
