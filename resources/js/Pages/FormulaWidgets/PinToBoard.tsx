import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import CardBox from '@/Components/CardBox';
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormulaWidgetSummary } from '@/types/formulaWidget';
import { FormEvent } from 'react';

interface BoardOption {
    id: number;
    name: string;
    is_home: boolean;
}

interface PinToBoardProps {
    widget: FormulaWidgetSummary;
    boards: BoardOption[];
    defaultBoardId: number | null;
}

export default function PinToBoard({ widget, boards, defaultBoardId }: PinToBoardProps) {
    const form = useForm({
        board_id: defaultBoardId ?? boards[0]?.id ?? '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(route('formula-widgets.pin', widget.id));
    }

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Aggiungi alla dashboard"
                    backLink={route('formula-widgets.index')}
                />
            }
        >
            <Head title="Aggiungi alla dashboard" />

            <PageContent maxWidth="3xl">
                <CardBox className="p-5">
                    <p className="text-sm text-gray-600 dark:text-gray-300">
                        Dove vuoi aggiungere <span className="font-semibold text-gray-900 dark:text-white">«{widget.name}»</span>?
                    </p>

                    <form onSubmit={submit} className="mt-4 space-y-4">
                        <div>
                            <label htmlFor="pin-board" className="block text-xs font-medium text-gray-600 dark:text-gray-300">
                                Dashboard
                            </label>
                            <select
                                id="pin-board"
                                value={form.data.board_id}
                                onChange={(e) => form.setData('board_id', Number(e.target.value))}
                                className="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                required
                            >
                                {boards.map((board) => (
                                    <option key={board.id} value={board.id}>
                                        {board.name}
                                        {board.is_home ? ' (Home)' : ''}
                                    </option>
                                ))}
                            </select>
                            {form.errors.board_id ? (
                                <p className="mt-1 text-xs text-rose-600">{form.errors.board_id}</p>
                            ) : null}
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <PrimaryButton type="submit" disabled={form.processing}>
                                Aggiungi
                            </PrimaryButton>
                            <Link
                                href={route('formula-widgets.index')}
                                className="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600"
                            >
                                Annulla
                            </Link>
                        </div>
                    </form>
                </CardBox>
            </PageContent>
        </AuthenticatedLayout>
    );
}
