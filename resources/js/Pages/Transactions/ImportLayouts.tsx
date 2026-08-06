import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import ColumnMapper from '@/Components/ColumnMapper';
import ImportLayoutSourceIcon, { LAYOUT_SOURCE_OPTIONS } from '@/Components/ImportLayoutSourceIcon';
import EmptyState from '@/Components/EmptyState';
import InputLabel from '@/Components/InputLabel';
import LinkButton from '@/Components/LinkButton';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import { ConfirmDeleteDialog } from '@/Components/ConfirmDeleteDialog';
import { IndexPageMobileToolbar } from '@/Components/IndexPageListToolbars';
import { Head, router } from '@inertiajs/react';
import clsx from 'clsx';
import { useState } from 'react';

const DELIMITER_OPTIONS = [
    { value: ';', label: 'Punto e virgola (;)' },
    { value: ',', label: 'Virgola (,)' },
    { value: '\t', label: 'Tab' },
];

const ENCODING_OPTIONS = [
    { value: 'UTF-8', label: 'UTF-8' },
    { value: 'ISO-8859-1', label: 'ISO-8859-1 (Latin-1)' },
    { value: 'Windows-1252', label: 'Windows-1252' },
];

const DATE_FORMAT_OPTIONS = [
    { value: 'd/m/Y', label: 'GG/MM/AAAA' },
    { value: 'Y-m-d', label: 'AAAA-MM-GG' },
    { value: 'm/d/Y', label: 'MM/GG/AAAA' },
    { value: 'd-m-Y', label: 'GG-MM-AAAA' },
];

const LAYOUT_SOURCE_ICON_DEFAULT = 'csv';

interface Layout {
    id: number;
    name: string;
    bank_name: string;
    icon: string | null;
    column_mapping: {
        date: number;
        amount: number;
        description: number;
        notes: number | null;
        category?: number | null;
        account?: number | null;
        currency?: number | null;
    };
    delimiter: string;
    date_format: string;
    has_header: boolean;
    encoding: string;
    created_at?: string;
}

interface EditForm {
    name: string;
    bank_name: string;
    icon: string;
    delimiter: string;
    date_format: string;
    has_header: boolean;
    encoding: string;
    column_mapping: {
        date: number | null;
        amount: number | null;
        description: number | null;
        notes: number | null;
        category?: number | null;
        account?: number | null;
        currency?: number | null;
    };
}

interface ImportLayoutsProps {
    layouts: Layout[];
}

export default function ImportLayouts({ layouts }: ImportLayoutsProps) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [layoutToDelete, setLayoutToDelete] = useState<Layout | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editForm, setEditForm] = useState<EditForm | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const [editErrors, setEditErrors] = useState<Record<string, string>>({});

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

    const handleEditClick = (layout: Layout) => {
        setEditingId(layout.id);
        setEditForm({
            name: layout.name,
            bank_name: layout.bank_name,
            icon: layout.icon && layout.icon.trim() !== '' ? layout.icon : LAYOUT_SOURCE_ICON_DEFAULT,
            delimiter: layout.delimiter,
            date_format: layout.date_format,
            has_header: layout.has_header,
            encoding: layout.encoding,
                column_mapping: {
                    date: layout.column_mapping.date,
                    amount: layout.column_mapping.amount,
                    description: layout.column_mapping.description,
                    notes: layout.column_mapping.notes ?? null,
                    category: layout.column_mapping.category ?? null,
                    account: layout.column_mapping.account ?? null,
                    currency: layout.column_mapping.currency ?? null,
                },
        });
        setEditErrors({});
    };

    const handleEditCancel = () => {
        setEditingId(null);
        setEditForm(null);
        setEditErrors({});
    };

    const handleEditSave = () => {
        if (!editForm || editingId === null) return;
        setIsSaving(true);
        router.patch(
            route('bank-import-layouts.update', editingId),
            {
                ...editForm,
                column_mapping: {
                    date: editForm.column_mapping.date ?? 0,
                    amount: editForm.column_mapping.amount ?? 1,
                    description: editForm.column_mapping.description ?? 2,
                    notes: editForm.column_mapping.notes,
                    category: editForm.column_mapping.category ?? null,
                    account: editForm.column_mapping.account ?? null,
                    currency: editForm.column_mapping.currency ?? null,
                },
            },
            {
                onSuccess: () => {
                    setEditingId(null);
                    setEditForm(null);
                    setIsSaving(false);
                    setEditErrors({});
                },
                onError: (errors) => {
                    setEditErrors(errors as Record<string, string>);
                    setIsSaving(false);
                },
            },
        );
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

            <PageContent maxWidth="4xl">
                <IndexPageMobileToolbar>
                    <LinkButton href={route('transactions.import')}>
                        Nuova importazione
                    </LinkButton>
                </IndexPageMobileToolbar>
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
                            <div key={layout.id}>
                                {/* Row header */}
                                <div className="flex items-center justify-between px-5 py-4">
                                <div className="flex items-center gap-3">
                                    <ImportLayoutSourceIcon icon={layout.icon} className="shrink-0" size="md" />
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 truncate">{layout.name}</p>
                                        <p className="text-xs text-gray-500 mt-0.5">
                                            {layout.delimiter === ';' ? 'Punto e virgola' : layout.delimiter === ',' ? 'Virgola' : 'Tab'}
                                            {' · '}{layout.date_format}
                                            {' · '}{layout.encoding}
                                        </p>
                                    </div>
                                </div>
                                    <div className="flex items-center gap-2 ml-4 flex-shrink-0">
                                        <button
                                            type="button"
                                            onClick={() => editingId === layout.id ? handleEditCancel() : handleEditClick(layout)}
                                            className={clsx(
                                                'inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500',
                                                editingId === layout.id
                                                    ? 'text-gray-600 bg-gray-100 border-gray-200 hover:bg-gray-200'
                                                    : 'text-blue-600 bg-blue-50 border-blue-200 hover:bg-blue-100',
                                            )}
                                            aria-label={`Modifica layout ${layout.name}`}
                                        >
                                            {editingId === layout.id ? 'Annulla' : 'Modifica'}
                                        </button>
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

                                {/* Inline edit panel */}
                                {editingId === layout.id && editForm && (
                                    <div className="px-5 pb-5 bg-gray-50 border-t border-gray-100">
                                        <div className="pt-4 space-y-4">
                                            {/* Nome */}
                                            <div>
                                                <InputLabel htmlFor={`edit_name_${layout.id}`} value="Nome layout *" />
                                                <input
                                                    id={`edit_name_${layout.id}`}
                                                    type="text"
                                                    value={editForm.name}
                                                    onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                                                    maxLength={100}
                                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                                />
                                                {editErrors.name && <p className="mt-1 text-xs text-red-600">{editErrors.name}</p>}
                                            </div>

                                            {/* Fonte dati */}
                                            <div>
                                                <p className="text-xs text-gray-500 mb-1.5">Fonte dati</p>
                                                <div className="flex flex-wrap gap-2">
                                                    {LAYOUT_SOURCE_OPTIONS.map((opt) => (
                                                        <button
                                                            key={opt.id}
                                                            type="button"
                                                            onClick={() => setEditForm({ ...editForm, icon: opt.id })}
                                                            className={clsx(
                                                                'flex items-center gap-2 rounded-lg border-2 px-3 py-2 text-left transition-all focus:outline-none focus:ring-2 focus:ring-blue-500',
                                                                editForm.icon === opt.id
                                                                    ? 'border-blue-500 bg-blue-50'
                                                                    : 'border-gray-200 bg-white hover:border-blue-300',
                                                            )}
                                                            aria-pressed={editForm.icon === opt.id}
                                                            aria-label={`${opt.label}: ${opt.hint}`}
                                                        >
                                                            <ImportLayoutSourceIcon icon={opt.id} size="md" />
                                                            <span>
                                                                <span className="block text-sm font-medium text-gray-900">{opt.label}</span>
                                                                <span className="block text-xs text-gray-500">{opt.hint}</span>
                                                            </span>
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>

                                            {/* Separatore / codifica / formato data */}
                                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                                <div>
                                                    <InputLabel htmlFor={`edit_delimiter_${layout.id}`} value="Separatore" />
                                                    <select
                                                        id={`edit_delimiter_${layout.id}`}
                                                        value={editForm.delimiter}
                                                        onChange={(e) => setEditForm({ ...editForm, delimiter: e.target.value })}
                                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                                    >
                                                        {DELIMITER_OPTIONS.map((o) => (
                                                            <option key={o.value} value={o.value}>{o.label}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div>
                                                    <InputLabel htmlFor={`edit_date_format_${layout.id}`} value="Formato data" />
                                                    <select
                                                        id={`edit_date_format_${layout.id}`}
                                                        value={editForm.date_format}
                                                        onChange={(e) => setEditForm({ ...editForm, date_format: e.target.value })}
                                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                                    >
                                                        {DATE_FORMAT_OPTIONS.map((o) => (
                                                            <option key={o.value} value={o.value}>{o.label}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div>
                                                    <InputLabel htmlFor={`edit_encoding_${layout.id}`} value="Codifica" />
                                                    <select
                                                        id={`edit_encoding_${layout.id}`}
                                                        value={editForm.encoding}
                                                        onChange={(e) => setEditForm({ ...editForm, encoding: e.target.value })}
                                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                                    >
                                                        {ENCODING_OPTIONS.map((o) => (
                                                            <option key={o.value} value={o.value}>{o.label}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                            </div>

                                            {/* Ha intestazione */}
                                            <div className="flex items-center gap-2">
                                                <input
                                                    id={`edit_has_header_${layout.id}`}
                                                    type="checkbox"
                                                    checked={editForm.has_header}
                                                    onChange={(e) => setEditForm({ ...editForm, has_header: e.target.checked })}
                                                    className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                />
                                                <label htmlFor={`edit_has_header_${layout.id}`} className="text-sm text-gray-700">
                                                    Il file CSV ha una riga di intestazione
                                                </label>
                                            </div>

                                            {/* Mappatura colonne */}
                                            <div>
                                                <p className="text-sm font-medium text-gray-700 mb-2">Mappatura colonne</p>
                                                <p className="text-xs text-gray-500 mb-3">Gli indici sono in base 0 (la prima colonna è la 0).</p>
                                                <ColumnMapper
                                                    headers={[]}
                                                    columnCount={20}
                                                    mapping={editForm.column_mapping}
                                                    onChange={(m) => setEditForm({ ...editForm, column_mapping: m })}
                                                />
                                            </div>

                                            {/* Pulsanti azione */}
                                            <div className="flex items-center gap-3 pt-2">
                                                <button
                                                    type="button"
                                                    onClick={handleEditSave}
                                                    disabled={isSaving || !editForm.name.trim()}
                                                    className={clsx(
                                                        'inline-flex items-center px-4 py-2 rounded-md text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500',
                                                        isSaving || !editForm.name.trim()
                                                            ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                            : 'bg-blue-600 text-white hover:bg-blue-700',
                                                    )}
                                                >
                                                    {isSaving ? 'Salvataggio…' : 'Salva modifiche'}
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={handleEditCancel}
                                                    className="text-sm text-gray-600 hover:text-gray-900"
                                                >
                                                    Annulla
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </PageContent>
        </AuthenticatedLayout>
    );
}
