import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';
import { useState } from 'react';

interface Category {
    id: number;
    name: string;
    type: string;
    type_label: string;
    color: string | null;
    icon: string | null;
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

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="mb-4 text-6xl">📁</div>
            <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                Nessuna categoria trovata
            </h3>
            <p className="mb-6 text-slate-500">
                Crea le tue categorie per classificare entrate e uscite.
            </p>
            <LinkButton
                href={route('categories.create')}
                icon={<PlusIcon />}
            >
                Crea la tua prima categoria
            </LinkButton>
        </div>
    );
}

function CategoryCard({
    category,
    onDelete,
}: {
    category: Category;
    onDelete: (id: number) => void;
}) {
    return (
        <div className="flex items-center justify-between rounded-lg bg-white p-4 shadow-sm dark:bg-gray-800">
            <div className="flex items-center space-x-3">
                <span
                    className="flex h-10 w-10 items-center justify-center rounded-full text-xl"
                    style={{
                        backgroundColor: category.color
                            ? category.color + '20'
                            : '#e5e7eb',
                    }}
                >
                    {category.icon || '📁'}
                </span>
                <div>
                    <h4 className="font-medium text-gray-900 dark:text-white">
                        {category.name}
                    </h4>
                    <span
                        className={clsx(
                            'text-xs',
                            category.type === 'income'
                                ? 'text-green-600 dark:text-green-400'
                                : 'text-red-600 dark:text-red-400'
                        )}
                    >
                        {category.type_label}
                    </span>
                </div>
            </div>
            <div className="flex items-center space-x-2">
                <Link
                    href={route('categories.edit', category.id)}
                    className="rounded px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                >
                    Modifica
                </Link>
                <button
                    onClick={() => onDelete(category.id)}
                    className="rounded px-3 py-1 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                >
                    Elimina
                </button>
            </div>
        </div>
    );
}

export default function Index({ categories, byType, categoryTypes }: IndexProps) {
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const handleDelete = (id: number) => {
        if (confirm('Sei sicuro di voler eliminare questa categoria?')) {
            setDeletingId(id);
            router.delete(route('categories.destroy', id), {
                onFinish: () => setDeletingId(null),
            });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold leading-tight text-slate-800">
                        Categorie
                    </h1>
                    <LinkButton
                        href={route('categories.create')}
                        icon={<PlusIcon />}
                    >
                        Nuova Categoria
                    </LinkButton>
                </div>
            }
        >
            <Head title="Categorie" />

            <div className="py-6">
                <div className="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {categories.length === 0 ? (
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <EmptyState />
                        </div>
                    ) : (
                        <>
                            {/* Riepilogo */}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="rounded-xl bg-green-50 p-4 dark:bg-green-900/20">
                                    <div className="flex items-center space-x-2">
                                        <span className="text-2xl">📈</span>
                                        <span className="font-medium text-green-700 dark:text-green-400">
                                            Entrate
                                        </span>
                                    </div>
                                    <p className="mt-2 text-2xl font-bold text-green-900 dark:text-green-100">
                                        {byType.income.length}
                                    </p>
                                    <p className="text-sm text-green-600 dark:text-green-400">
                                        {byType.income.length === 1
                                            ? 'categoria'
                                            : 'categorie'}
                                    </p>
                                </div>
                                <div className="rounded-xl bg-red-50 p-4 dark:bg-red-900/20">
                                    <div className="flex items-center space-x-2">
                                        <span className="text-2xl">📉</span>
                                        <span className="font-medium text-red-700 dark:text-red-400">
                                            Uscite
                                        </span>
                                    </div>
                                    <p className="mt-2 text-2xl font-bold text-red-900 dark:text-red-100">
                                        {byType.expense.length}
                                    </p>
                                    <p className="text-sm text-red-600 dark:text-red-400">
                                        {byType.expense.length === 1
                                            ? 'categoria'
                                            : 'categorie'}
                                    </p>
                                </div>
                            </div>

                            {/* Categorie Entrate */}
                            {byType.income.length > 0 && (
                                <div>
                                    <h3 className="mb-4 flex items-center space-x-2 text-lg font-semibold text-gray-900 dark:text-white">
                                        <span>📈</span>
                                        <span>Categorie Entrate</span>
                                    </h3>
                                    <div className="space-y-3">
                                        {byType.income.map((category) => (
                                            <CategoryCard
                                                key={category.id}
                                                category={category}
                                                onDelete={handleDelete}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Categorie Uscite */}
                            {byType.expense.length > 0 && (
                                <div>
                                    <h3 className="mb-4 flex items-center space-x-2 text-lg font-semibold text-gray-900 dark:text-white">
                                        <span>📉</span>
                                        <span>Categorie Uscite</span>
                                    </h3>
                                    <div className="space-y-3">
                                        {byType.expense.map((category) => (
                                            <CategoryCard
                                                key={category.id}
                                                category={category}
                                                onDelete={handleDelete}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
