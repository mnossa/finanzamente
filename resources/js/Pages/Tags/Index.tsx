import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import EmptyState from '@/Components/EmptyState';
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
                    className="rounded p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-blue-600 dark:hover:bg-gray-700 dark:hover:text-blue-400"
                    title="Modifica"
                >
                    <PencilIcon size={18} />
                </Link>
                <button
                    onClick={handleDelete}
                    className="rounded p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-red-600 dark:hover:bg-gray-700 dark:hover:text-red-400"
                    title="Elimina"
                >
                    <TrashIcon size={18} />
                </button>
            </div>
        </div>
    );
}

export default function Index({ tags }: IndexProps) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h1 className="text-xl font-semibold leading-tight text-slate-800">
                        Tag
                    </h1>
                    <LinkButton
                        href={route('tags.create')}
                        icon={<PlusIcon />}
                    >
                        Nuovo Tag
                    </LinkButton>
                </div>
            }
        >
            <Head title="Tag" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {tags.length === 0 ? (
                        <div className="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-gray-800">
                            <EmptyState
                                icon="🏷️"
                                title="Nessun tag trovato"
                                description="Crea il tuo primo tag per organizzare le tue transazioni."
                                createUrl={route('tags.create')}
                                createLabel="Crea il tuo primo tag"
                            />
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
