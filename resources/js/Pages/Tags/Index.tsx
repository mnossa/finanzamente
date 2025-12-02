import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import clsx from 'clsx';

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

function EmptyState() {
    return (
        <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="mb-4 text-6xl">🏷️</div>
            <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                Nessun tag trovato
            </h3>
            <p className="mb-6 text-gray-500 dark:text-gray-400">
                Crea il tuo primo tag per organizzare le tue transazioni.
            </p>
            <Link
                href={route('tags.create')}
                className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700"
            >
                <span className="mr-2">➕</span>
                Crea il tuo primo tag
            </Link>
        </div>
    );
}

function TagCard({ tag }: { tag: Tag }) {
    const handleDelete = () => {
        if (confirm('Sei sicuro di voler eliminare questo tag?')) {
            router.delete(route('tags.destroy', tag.id));
        }
    };

    return (
        <div className="rounded-xl bg-white p-4 shadow-sm transition-shadow hover:shadow-md dark:bg-gray-800">
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
                    className="rounded px-3 py-1 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
                >
                    Modifica
                </Link>
                <button
                    onClick={handleDelete}
                    className="rounded px-3 py-1 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                >
                    Elimina
                </button>
            </div>
        </div>
    );
}

export default function Index({ tags }: IndexProps) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        Tag
                    </h2>
                    <Link
                        href={route('tags.create')}
                        className="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        <span className="mr-2">➕</span>
                        Nuovo Tag
                    </Link>
                </div>
            }
        >
            <Head title="Tag" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {tags.length === 0 ? (
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <EmptyState />
                        </div>
                    ) : (
                        <>
                            {/* Riepilogo */}
                            <div className="overflow-hidden rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 p-6 text-white shadow-lg">
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
                                    <TagCard key={tag.id} tag={tag} />
                                ))}
                            </div>
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
