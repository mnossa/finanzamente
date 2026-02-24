import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import LinkButton from '@/Components/LinkButton';
import PrimaryButton from '@/Components/PrimaryButton';
import EmptyState from '@/Components/EmptyState';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';
import { useState } from 'react';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';

interface Layout {
    id: number;
    name: string;
    bank_name: string;
    column_mapping: {
        date: number;
        amount: number;
        description: number;
        notes: number | null;
    };
    delimiter: string;
    date_format: string;
    has_header: boolean;
    encoding: string;
    created_at?: string;
}

interface ImportLayoutsProps {
    layouts: Layout[];
    bankNames: Record<string, string>;
}

export default function ImportLayouts({ layouts, bankNames }: ImportLayoutsProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [layoutToDelete, setLayoutToDelete] = useState<Layout | null>(null);

    const handleDeleteClick = (layout: Layout) => {
        setLayoutToDelete(layout);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (!layoutToDelete) return;
        router.delete(route('bank-import-layouts.destroy', layoutToDelete.id), {
            onSuccess: () => {
                setDeleteDialogOpen(false);
                setLayoutToDelete(null);
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Layout di importazione"
                    backLink={route('transactions.index')}
                    actions={
                        <LinkButton href={route('transactions.import')}>
                            Nuova importazione
                        </LinkButton>
                    }
                />
            }
        >
            <Head title="Layout di importazione" />

            <ConfirmDeleteDialog
                open={deleteDialogOpen}
                title="Elimina layout"
                description={`Sei sicuro di voler eliminare il layout "${layoutToDelete?.name}"? Questa azione non può essere annullata.`}
                onConfirm={handleDeleteConfirm}
                onCancel={() => setDeleteDialogOpen(false)}
            />

            <div className="py-4 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
                {layouts.length === 0 ? (
                    <EmptyState
                        icon="📄"
                        title="Nessun layout salvato"
                        description="Non hai ancora salvato nessun layout personalizzato."
                    >
                        <LinkButton href={route('transactions.import')}>
                            Importa file CSV
                        </LinkButton>
                    </EmptyState>
                ) : (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 divide-y divide-gray-100">
                        {layouts.map((layout) => (
                            <div
                                key={layout.id}
                                className="flex items-center justify-between px-5 py-4"
                            >
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium text-gray-900 truncate">{layout.name}</p>
                                    <p className="text-xs text-gray-500 mt-0.5">
                                        {bankNames[layout.bank_name] ?? layout.bank_name}
                                        {' · '}{layout.delimiter === ';' ? 'Punto e virgola' : layout.delimiter === ',' ? 'Virgola' : 'Tab'}
                                        {' · '}{layout.date_format}
                                        {' · '}{layout.encoding}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2 ml-4 flex-shrink-0">
                                    <button
                                        type="button"
                                        onClick={() => handleDeleteClick(layout)}
                                        className={clsx(
                                            'inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg',
                                            'text-red-600 bg-red-50 hover:bg-red-100 border border-red-200',
                                            'transition-colors focus:outline-none focus:ring-2 focus:ring-red-500',
                                        )}
                                        aria-label={`Elimina layout ${layout.name}`}
                                    >
                                        Elimina
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
