import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import BankSelector from '@/Components/BankSelector';
import ColumnMapper from '@/Components/ColumnMapper';
import ImportWizardStep from '@/Components/ImportWizardStep';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LinkButton from '@/Components/LinkButton';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import clsx from 'clsx';
import { useRef, useState } from 'react';

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface PredefinedLayout {
    name: string;
    bank_name: string;
    delimiter: string;
    date_format: string;
    has_header: boolean;
    encoding: string;
    column_mapping: {
        date: number;
        amount: number;
        description: number;
        notes: number | null;
    };
}

interface UserLayout {
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
}

interface ImportRow {
    line_number: number;
    date: string;
    amount: number;
    description: string;
    notes: string | null;
    raw: string;
    errors: string[];
}

interface PreviewResponse {
    headers: string[];
    valid: ImportRow[];
    invalid: ImportRow[];
    total: number;
    valid_count: number;
    invalid_count: number;
}

interface ColumnMapping {
    date: number | null;
    amount: number | null;
    description: number | null;
    notes: number | null;
}

interface ImportProps {
    accounts: Account[];
    predefinedLayouts: Record<string, PredefinedLayout>;
    userLayouts: UserLayout[];
    bankNames: Record<string, string>;
}

const WIZARD_STEPS = [
    'Seleziona banca',
    'Carica file',
    'Mappa colonne',
    'Conferma',
];

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

export default function Import({ accounts, predefinedLayouts, userLayouts, bankNames }: ImportProps) {
    const [currentStep, setCurrentStep] = useState(0);
    const [selectedBank, setSelectedBank] = useState('');
    const [csvFile, setCsvFile] = useState<File | null>(null);
    const [delimiter, setDelimiter] = useState(';');
    const [dateFormat, setDateFormat] = useState('d/m/Y');
    const [hasHeader, setHasHeader] = useState(true);
    const [encoding, setEncoding] = useState('UTF-8');
    const [columnMapping, setColumnMapping] = useState<ColumnMapping>({
        date: 0,
        amount: 1,
        description: 2,
        notes: null,
    });
    const [previewData, setPreviewData] = useState<PreviewResponse | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [selectedRows, setSelectedRows] = useState<Set<number>>(new Set());
    const fileInputRef = useRef<HTMLInputElement>(null);

    const { data, setData, post, processing, errors } = useForm({
        account_id: accounts.length > 0 ? String(accounts[0].id) : '',
        rows: [] as { date: string; amount: number; description: string; notes: string | null }[],
    });

    const applyUserLayout = (layout: UserLayout) => {
        setSelectedBank(layout.bank_name);
        setDelimiter(layout.delimiter);
        setDateFormat(layout.date_format);
        setHasHeader(layout.has_header);
        setEncoding(layout.encoding);
        setColumnMapping({
            date: layout.column_mapping.date,
            amount: layout.column_mapping.amount,
            description: layout.column_mapping.description,
            notes: layout.column_mapping.notes ?? null,
        });
    };

    const steps = WIZARD_STEPS.map((label, index) => ({
        label,
        completed: index < currentStep,
        active: index === currentStep,
    }));

    const applyPredefinedLayout = (bankKey: string) => {
        const layout = predefinedLayouts[bankKey];
        if (layout) {
            setDelimiter(layout.delimiter);
            setDateFormat(layout.date_format);
            setHasHeader(layout.has_header);
            setEncoding(layout.encoding);
            setColumnMapping({
                date: layout.column_mapping.date,
                amount: layout.column_mapping.amount,
                description: layout.column_mapping.description,
                notes: layout.column_mapping.notes ?? null,
            });
        }
    };

    const handleBankSelect = (bank: string) => {
        setSelectedBank(bank);
        applyPredefinedLayout(bank);
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setCsvFile(file);
        setPreviewData(null);
    };

    const callPreview = async () => {
        if (!csvFile) return;
        setPreviewLoading(true);
        setPreviewError(null);

        const formData = new FormData();
        formData.append('csv_file', csvFile);
        formData.append('bank_name', selectedBank || 'custom');
        formData.append('delimiter', delimiter);
        formData.append('date_format', dateFormat);
        formData.append('has_header', hasHeader ? '1' : '0');
        formData.append('encoding', encoding);
        formData.append('column_mapping[date]', String(columnMapping.date ?? 0));
        formData.append('column_mapping[amount]', String(columnMapping.amount ?? 1));
        formData.append('column_mapping[description]', String(columnMapping.description ?? 2));
        if (columnMapping.notes !== null) {
            formData.append('column_mapping[notes]', String(columnMapping.notes));
        }

        try {
            const response = await axios.post<PreviewResponse>(route('transactions.import.preview'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setPreviewData(response.data);
            const allIndices = new Set(response.data.valid.map((_, i) => i));
            setSelectedRows(allIndices);
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.message) {
                setPreviewError(err.response.data.message);
            } else {
                setPreviewError('Errore durante la lettura del file. Verifica la configurazione.');
            }
        } finally {
            setPreviewLoading(false);
        }
    };

    const handleNextStep = async () => {
        if (currentStep === 2) {
            await callPreview();
        }
        if (currentStep === 2 && previewError) return;
        setCurrentStep((s) => Math.min(s + 1, WIZARD_STEPS.length - 1));
    };

    const handlePrevStep = () => {
        setCurrentStep((s) => Math.max(s - 1, 0));
    };

    const toggleRow = (index: number) => {
        setSelectedRows((prev) => {
            const next = new Set(prev);
            if (next.has(index)) {
                next.delete(index);
            } else {
                next.add(index);
            }
            return next;
        });
    };

    const toggleAllRows = () => {
        if (previewData && selectedRows.size === previewData.valid.length) {
            setSelectedRows(new Set());
        } else if (previewData) {
            setSelectedRows(new Set(previewData.valid.map((_, i) => i)));
        }
    };

    const handleImport = (e: React.FormEvent) => {
        e.preventDefault();
        if (!previewData) return;

        const rowsToImport = previewData.valid
            .filter((_, i) => selectedRows.has(i))
            .map((row) => ({
                date: row.date,
                amount: row.amount,
                description: row.description,
                notes: row.notes,
            }));

        setData('rows', rowsToImport);
        post(route('transactions.import.store'));
    };

    const canProceedFromStep = (): boolean => {
        if (currentStep === 0) return selectedBank !== '';
        if (currentStep === 1) return csvFile !== null;
        if (currentStep === 2) return columnMapping.date !== null && columnMapping.amount !== null && columnMapping.description !== null;
        if (currentStep === 3) return selectedRows.size > 0 && data.account_id !== '';
        return false;
    };

    const formatAmount = (amount: number) => {
        return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(amount);
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Importa Transazioni"
                    backLink={route('transactions.index')}
                />
            }
        >
            <Head title="Importa Transazioni" />

            <div className="py-4 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
                <ImportWizardStep steps={steps} className="mb-6" />

                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    {/* Step 0: Seleziona banca */}
                    {currentStep === 0 && (
                        <div>
                            <h2 className="text-lg font-semibold text-gray-900 mb-1">Seleziona la tua banca</h2>
                            <p className="text-sm text-gray-500 mb-4">
                                Scegli la banca per applicare il formato CSV predefinito, oppure scegli &ldquo;Layout personalizzato&rdquo;.
                            </p>
                            <BankSelector
                                bankNames={bankNames}
                                selectedBank={selectedBank}
                                onSelect={handleBankSelect}
                            />
                            {userLayouts.length > 0 && (
                                <div className="mt-6">
                                    <h3 className="text-sm font-medium text-gray-700 mb-2">Oppure carica un layout salvato:</h3>
                                    <div className="flex flex-wrap gap-2">
                                        {userLayouts.map((layout) => (
                                            <button
                                                key={layout.id}
                                                type="button"
                                                onClick={() => applyUserLayout(layout)}
                                                className={clsx(
                                                    'inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors',
                                                    'bg-white border-gray-300 text-gray-700 hover:border-blue-400 hover:bg-blue-50',
                                                    'focus:outline-none focus:ring-2 focus:ring-blue-500',
                                                )}
                                            >
                                                {layout.name}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Step 1: Carica file */}
                    {currentStep === 1 && (
                        <div className="space-y-5">
                            <h2 className="text-lg font-semibold text-gray-900">Carica il file CSV</h2>

                            {/* File upload */}
                            <div>
                                <InputLabel htmlFor="csv_file" value="File CSV *" />
                                <div
                                    className={clsx(
                                        'mt-1 flex flex-col items-center justify-center border-2 border-dashed rounded-lg p-6 cursor-pointer',
                                        csvFile ? 'border-blue-400 bg-blue-50' : 'border-gray-300 bg-gray-50 hover:border-blue-400 hover:bg-blue-50',
                                    )}
                                    onClick={() => fileInputRef.current?.click()}
                                    onKeyDown={(e) => e.key === 'Enter' && fileInputRef.current?.click()}
                                    role="button"
                                    tabIndex={0}
                                    aria-label="Carica file CSV"
                                >
                                    {csvFile ? (
                                        <p className="text-sm font-medium text-blue-700">📄 {csvFile.name}</p>
                                    ) : (
                                        <>
                                            <p className="text-sm text-gray-500">Trascina il file qui o clicca per selezionarlo</p>
                                            <p className="text-xs text-gray-400 mt-1">CSV, TXT – max 5 MB</p>
                                        </>
                                    )}
                                </div>
                                <input
                                    id="csv_file"
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".csv,.txt"
                                    className="hidden"
                                    onChange={handleFileChange}
                                    aria-label="Seleziona file CSV"
                                />
                            </div>

                            {/* Configuration */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel htmlFor="delimiter" value="Separatore colonne" />
                                    <select
                                        id="delimiter"
                                        value={delimiter}
                                        onChange={(e) => setDelimiter(e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    >
                                        {DELIMITER_OPTIONS.map((opt) => (
                                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <InputLabel htmlFor="date_format" value="Formato data" />
                                    <select
                                        id="date_format"
                                        value={dateFormat}
                                        onChange={(e) => setDateFormat(e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    >
                                        {DATE_FORMAT_OPTIONS.map((opt) => (
                                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <InputLabel htmlFor="encoding" value="Codifica file" />
                                    <select
                                        id="encoding"
                                        value={encoding}
                                        onChange={(e) => setEncoding(e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    >
                                        {ENCODING_OPTIONS.map((opt) => (
                                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                                        ))}
                                    </select>
                                </div>

                                <div className="flex items-center gap-3 pt-5">
                                    <input
                                        id="has_header"
                                        type="checkbox"
                                        checked={hasHeader}
                                        onChange={(e) => setHasHeader(e.target.checked)}
                                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <InputLabel htmlFor="has_header" value="Il file ha una riga di intestazione" className="mb-0" />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 2: Mappa colonne */}
                    {currentStep === 2 && (
                        <div className="space-y-4">
                            <h2 className="text-lg font-semibold text-gray-900">Mappa le colonne</h2>
                            <p className="text-sm text-gray-500">
                                Indica quale colonna del CSV corrisponde a ciascun campo.
                            </p>
                            <ColumnMapper
                                headers={previewData?.headers ?? []}
                                columnCount={6}
                                mapping={columnMapping}
                                onChange={setColumnMapping}
                            />
                            <PrimaryButton
                                type="button"
                                onClick={callPreview}
                                disabled={previewLoading || !csvFile}
                                className="mt-2"
                            >
                                {previewLoading ? 'Lettura in corso…' : 'Aggiorna anteprima'}
                            </PrimaryButton>
                            {previewError && (
                                <div className="mt-2 text-sm text-red-600 bg-red-50 border border-red-200 rounded-md p-3">
                                    {previewError}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Step 3: Conferma */}
                    {currentStep === 3 && (
                        <form onSubmit={handleImport} className="space-y-5">
                            <h2 className="text-lg font-semibold text-gray-900">Anteprima e conferma</h2>

                            {/* Account selector */}
                            <div>
                                <InputLabel htmlFor="account_id" value="Conto di destinazione *" />
                                <select
                                    id="account_id"
                                    value={data.account_id}
                                    onChange={(e) => setData('account_id', e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    required
                                >
                                    {accounts.map((acc) => (
                                        <option key={acc.id} value={acc.id}>
                                            {acc.name} ({acc.currency_code})
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.account_id} className="mt-1" />
                            </div>

                            {/* Stats */}
                            {previewData && (
                                <div className="flex flex-wrap gap-3 text-sm">
                                    <span className="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-800">
                                        ✓ {previewData.valid_count} transazioni valide
                                    </span>
                                    {previewData.invalid_count > 0 && (
                                        <span className="inline-flex items-center px-3 py-1 rounded-full bg-red-100 text-red-800">
                                            ✗ {previewData.invalid_count} righe non valide (ignorate)
                                        </span>
                                    )}
                                    <span className="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-800">
                                        Selezionate: {selectedRows.size}
                                    </span>
                                </div>
                            )}

                            {/* Preview table */}
                            {previewData && previewData.valid.length > 0 && (
                                <div className="overflow-x-auto -mx-6">
                                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-4 py-3 text-left">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedRows.size === previewData.valid.length}
                                                        onChange={toggleAllRows}
                                                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                        aria-label="Seleziona tutte"
                                                    />
                                                </th>
                                                <th className="px-4 py-3 text-left font-medium text-gray-600">Data</th>
                                                <th className="px-4 py-3 text-right font-medium text-gray-600">Importo</th>
                                                <th className="px-4 py-3 text-left font-medium text-gray-600">Descrizione</th>
                                                <th className="px-4 py-3 text-left font-medium text-gray-600 hidden sm:table-cell">Note</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-100">
                                            {previewData.valid.map((row, index) => (
                                                <tr
                                                    key={index}
                                                    className={clsx(
                                                        'hover:bg-gray-50 transition-colors',
                                                        !selectedRows.has(index) && 'opacity-40',
                                                    )}
                                                >
                                                    <td className="px-4 py-3">
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedRows.has(index)}
                                                            onChange={() => toggleRow(index)}
                                                            className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                            aria-label={`Seleziona riga ${index + 1}`}
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3 text-gray-700 whitespace-nowrap">
                                                        {new Date(row.date).toLocaleDateString('it-IT')}
                                                    </td>
                                                    <td className={clsx(
                                                        'px-4 py-3 text-right font-medium whitespace-nowrap',
                                                        row.amount >= 0 ? 'text-green-600' : 'text-red-600',
                                                    )}>
                                                        {formatAmount(row.amount)}
                                                    </td>
                                                    <td className="px-4 py-3 text-gray-700 max-w-xs truncate">
                                                        {row.description}
                                                    </td>
                                                    <td className="px-4 py-3 text-gray-500 max-w-xs truncate hidden sm:table-cell">
                                                        {row.notes ?? '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {previewData && previewData.valid.length === 0 && (
                                <div className="text-center py-8 text-gray-500">
                                    Nessuna transazione valida trovata nel file. Torna indietro e verifica la configurazione.
                                </div>
                            )}

                            <InputError message={errors.rows} className="mt-1" />
                        </form>
                    )}

                    {/* Navigation */}
                    <div className="mt-8 flex items-center justify-between gap-3 border-t border-gray-100 pt-5">
                        <div>
                            {currentStep > 0 && (
                                <button
                                    type="button"
                                    onClick={handlePrevStep}
                                    className="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors"
                                >
                                    ← Indietro
                                </button>
                            )}
                            {currentStep === 0 && (
                                <LinkButton href={route('transactions.index')}>Annulla</LinkButton>
                            )}
                        </div>

                        <div>
                            {currentStep < WIZARD_STEPS.length - 1 ? (
                                <PrimaryButton
                                    type="button"
                                    onClick={handleNextStep}
                                    disabled={!canProceedFromStep() || previewLoading}
                                >
                                    {previewLoading && currentStep === 2 ? 'Lettura in corso…' : 'Avanti →'}
                                </PrimaryButton>
                            ) : (
                                <PrimaryButton
                                    type="button"
                                    onClick={handleImport}
                                    disabled={selectedRows.size === 0 || processing || !data.account_id}
                                >
                                    {processing ? 'Importazione…' : `Importa ${selectedRows.size} transazioni`}
                                </PrimaryButton>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
