import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions } from '@/Components/IndexPageListToolbars';
import CardBox from '@/Components/CardBox';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import { FormEvent, useState } from 'react';
import type { PageProps } from '@/types';

interface Board {
    id: number;
    name: string;
    is_home: boolean;
    sort_order: number;
    widget_count: number;
    updated_at: string | null;
}

interface BoardsPageProps {
    boards: Board[];
    boardLimit: number;
    canCreate: boolean;
}

export default function Boards({ boards, boardLimit, canCreate }: BoardsPageProps) {
    const { flash } = usePage<PageProps & { flash?: { success?: string } }>().props;
    const [showCreate, setShowCreate] = useState(false);
    const createForm = useForm({
        name: '',
        template: 'essential' as 'empty' | 'essential' | 'default',
    });
    const [renameId, setRenameId] = useState<number | null>(null);
    const renameForm = useForm({ name: '' });

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route('dashboard.boards.store'), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset();
                setShowCreate(false);
            },
        });
    };

    const submitRename = (event: FormEvent, boardId: number) => {
        event.preventDefault();
        renameForm.patch(route('dashboard.boards.update', boardId), {
            preserveScroll: true,
            onSuccess: () => setRenameId(null),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Le mie dashboard"
                    backLink={route('dashboard')}
                    actions={
                        canCreate ? (
                            <IndexPageHeaderActions>
                                <PrimaryButton type="button" onClick={() => setShowCreate(true)}>
                                    Nuova dashboard
                                </PrimaryButton>
                            </IndexPageHeaderActions>
                        ) : undefined
                    }
                />
            }
        >
            <Head title="Le mie dashboard" />

            <PageContent maxWidth="3xl">
                {flash?.success ? (
                    <p className="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
                        {flash.success}
                    </p>
                ) : null}

                <p className="mb-4 text-sm text-gray-600 dark:text-gray-300">
                    Personalizza la Home o crea viste aggiuntive con i widget che preferisci.
                    Limite piano: {boards.length}/{boardLimit}.
                </p>

                {!canCreate ? (
                    <p className="mb-4 text-sm text-amber-700 dark:text-amber-300">
                        Hai raggiunto il limite di dashboard. Passa a Pro per crearne altre.
                    </p>
                ) : null}

                {canCreate ? (
                    <div className="mb-4 lg:hidden">
                        <PrimaryButton type="button" onClick={() => setShowCreate(true)}>
                            Nuova dashboard
                        </PrimaryButton>
                    </div>
                ) : null}

                {showCreate ? (
                    <CardBox className="mb-6 p-4">
                        <h2 className="text-sm font-semibold text-gray-900 dark:text-white">Nuova dashboard</h2>
                        <form onSubmit={submitCreate} className="mt-3 space-y-3">
                            <div>
                                <label htmlFor="board-name" className="block text-xs font-medium text-gray-600 dark:text-gray-300">
                                    Nome
                                </label>
                                <input
                                    id="board-name"
                                    type="text"
                                    value={createForm.data.name}
                                    onChange={(e) => createForm.setData('name', e.target.value)}
                                    className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                    placeholder="es. Risparmi"
                                    required
                                />
                                {createForm.errors.name ? (
                                    <p className="mt-1 text-xs text-rose-600">{createForm.errors.name}</p>
                                ) : null}
                            </div>
                            <div>
                                <label htmlFor="board-template" className="block text-xs font-medium text-gray-600 dark:text-gray-300">
                                    Template iniziale
                                </label>
                                <select
                                    id="board-template"
                                    value={createForm.data.template}
                                    onChange={(e) => createForm.setData('template', e.target.value as 'empty' | 'essential' | 'default')}
                                    className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                >
                                    <option value="essential">Essenziale</option>
                                    <option value="default">Completa</option>
                                    <option value="empty">Vuota</option>
                                </select>
                            </div>
                            <div className="flex gap-2">
                                <PrimaryButton type="submit" disabled={createForm.processing}>
                                    Crea
                                </PrimaryButton>
                                <button
                                    type="button"
                                    onClick={() => setShowCreate(false)}
                                    className="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600"
                                >
                                    Annulla
                                </button>
                            </div>
                        </form>
                    </CardBox>
                ) : null}

                <div className="space-y-3">
                    {boards.map((board) => (
                        <CardBox key={board.id} className="p-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    {renameId === board.id ? (
                                        <form onSubmit={(e) => submitRename(e, board.id)} className="flex flex-wrap items-center gap-2">
                                            <input
                                                type="text"
                                                value={renameForm.data.name}
                                                onChange={(e) => renameForm.setData('name', e.target.value)}
                                                className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900"
                                                autoFocus
                                            />
                                            <PrimaryButton type="submit" disabled={renameForm.processing}>
                                                Salva
                                            </PrimaryButton>
                                            <button type="button" onClick={() => setRenameId(null)} className="text-sm text-gray-500">
                                                Annulla
                                            </button>
                                        </form>
                                    ) : (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="text-base font-semibold text-gray-900 dark:text-white">{board.name}</h3>
                                            {board.is_home ? (
                                                <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">
                                                    Home
                                                </span>
                                            ) : null}
                                        </div>
                                    )}
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {board.widget_count} widget
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Link
                                        href={
                                            board.is_home
                                                ? route('dashboard')
                                                : route('dashboard', { board: board.id })
                                        }
                                        className={clsx(
                                            'rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white',
                                            'hover:bg-emerald-700',
                                        )}
                                        data-testid={board.is_home ? 'board-open-home' : `board-open-${board.id}`}
                                    >
                                        Apri
                                    </Link>
                                    <Link
                                        href={
                                            board.is_home
                                                ? route('dashboard', { edit: 1 })
                                                : route('dashboard', { board: board.id, edit: 1 })
                                        }
                                        className="rounded-lg border border-emerald-300 px-3 py-1.5 text-sm font-medium text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/20"
                                        data-testid={board.is_home ? 'board-customize-home' : `board-customize-${board.id}`}
                                    >
                                        Personalizza
                                    </Link>
                                    {!board.is_home ? (
                                        <button
                                            type="button"
                                            onClick={() => router.post(route('dashboard.boards.set-home', board.id))}
                                            className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600"
                                        >
                                            Imposta come Home
                                        </button>
                                    ) : null}
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setRenameId(board.id);
                                            renameForm.setData('name', board.name);
                                        }}
                                        className="rounded-lg border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600"
                                    >
                                        Rinomina
                                    </button>
                                    {!board.is_home ? (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (window.confirm(`Eliminare «${board.name}»?`)) {
                                                    router.delete(route('dashboard.boards.destroy', board.id));
                                                }
                                            }}
                                            className="rounded-lg border border-rose-300 px-3 py-1.5 text-sm text-rose-700 dark:border-rose-800 dark:text-rose-300"
                                        >
                                            Elimina
                                        </button>
                                    ) : null}
                                </div>
                            </div>
                        </CardBox>
                    ))}
                </div>
            </PageContent>
        </AuthenticatedLayout>
    );
}
