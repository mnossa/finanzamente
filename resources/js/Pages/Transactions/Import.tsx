import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ColumnMapper from '@/Components/ColumnMapper';
import ImportWizardStep from '@/Components/ImportWizardStep';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LinkButton from '@/Components/LinkButton';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, Link, router, useForm } from '@inertiajs/react';
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

interface ExistingTransaction {
    id: number;
    date: string;
    amount: number;
    description: string;
}

interface DuplicateInfo {
    row_index: number;
    date: string;
    amount: number;
    description: string;
    existing: ExistingTransaction[];
}

type DuplicateAction = 'import' | 'ignore' | 'replace' | 'update';

interface DuplicateResolution {
    action: DuplicateAction;
    duplicate_transaction_id: number | null;
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

export default function Import({ accounts, predefinedLayouts, userLayouts: initialUserLayouts, bankNames }: ImportProps) {
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

    /** true se il file selezionato è un XLSX */
    const isXlsx = csvFile?.name.toLowerCase().endsWith('.xlsx') ?? false;

    const { data, setData } = useForm({
        account_id: '',
    });
    const [importProcessing, setImportProcessing] = useState(false);
    const [importErrors, setImportErrors] = useState<Record<string, string>>({});

    // Gestione duplicati
    const [duplicateCheckLoading, setDuplicateCheckLoading] = useState(false);
    const [duplicates, setDuplicates] = useState<DuplicateInfo[]>([]);
    const [duplicateResolutions, setDuplicateResolutions] = useState<Record<number, DuplicateResolution>>({});
    const [showDuplicateModal, setShowDuplicateModal] = useState(false);

    // Layout salvati (gestito come stato per poter aggiungere nuovi layout dinamicamente)
    const [userLayouts, setUserLayouts] = useState<UserLayout[]>(initialUserLayouts);
    const [selectedLayoutId, setSelectedLayoutId] = useState<number | null>(null);
    const [saveLayoutName, setSaveLayoutName] = useState('');
    const [savingLayout, setSavingLayout] = useState(false);
    const [saveLayoutSuccess, setSaveLayoutSuccess] = useState<string | null>(null);
    const [saveLayoutError, setSaveLayoutError] = useState<string | null>(null);

    const saveCurrentLayout = async () => {
        if (!saveLayoutName.trim()) {
            setSaveLayoutError('Inserisci un nome per il layout.');
            return;
        }
        setSavingLayout(true);
        setSaveLayoutError(null);
        setSaveLayoutSuccess(null);
        try {
            const response = await axios.post<{ success: boolean; message: string; layout: UserLayout }>(
                route('bank-import-layouts.store'),
                {
                    name: saveLayoutName.trim(),
                    bank_name: selectedBank || 'custom',
                    delimiter: isXlsx ? ',' : delimiter,
                    date_format: dateFormat,
                    has_header: hasHeader,
                    encoding: isXlsx ? 'UTF-8' : encoding,
                    column_mapping: {
                        date: columnMapping.date ?? 0,
                        amount: columnMapping.amount ?? 1,
                        description: columnMapping.description ?? 2,
                        notes: columnMapping.notes ?? null,
                    },
                },
                { headers: { Accept: 'application/json' } },
            );
            setSaveLayoutSuccess(response.data.message);
            setSaveLayoutName('');
            setUserLayouts((prev) => [...prev, response.data.layout]);
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.message) {
                setSaveLayoutError(err.response.data.message);
            } else if (axios.isAxiosError(err) && err.response?.data?.errors) {
                const firstError = Object.values(err.response.data.errors as Record<string, string[]>)[0];
                setSaveLayoutError(Array.isArray(firstError) ? firstError[0] : String(firstError));
            } else {
                setSaveLayoutError('Errore durante il salvataggio del layout.');
            }
        } finally {
            setSavingLayout(false);
        }
    };

    const applyUserLayout = (layout: UserLayout) => {
        setSelectedBank(layout.bank_name);
        setSelectedLayoutId(layout.id);
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

    const handleSelectCustom = () => {
        setSelectedBank('custom');
        setSelectedLayoutId(null);
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

    const callPreview = async (): Promise<boolean> => {
        if (!csvFile) return false;
        setPreviewLoading(true);
        setPreviewError(null);

        const formData = new FormData();
        formData.append('csv_file', csvFile);
        formData.append('bank_name', selectedBank || 'custom');
        formData.append('date_format', dateFormat);
        formData.append('has_header', hasHeader ? '1' : '0');
        // Delimiter e encoding non servono per XLSX: li omettiamo
        if (!isXlsx) {
            formData.append('delimiter', delimiter);
            formData.append('encoding', encoding);
        }
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
            return true;
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.message) {
                setPreviewError(err.response.data.message);
            } else {
                setPreviewError('Errore durante la lettura del file. Verifica la configurazione.');
            }
            return false;
        } finally {
            setPreviewLoading(false);
        }
    };

    const handleNextStep = async () => {
        // Entrando nello step 2 (Mappa colonne): carica subito l'anteprima per mostrare le intestazioni
        if (currentStep === 1) {
            const ok = await callPreview();
            if (!ok) return;
        }
        // Uscendo dallo step 2 verso la conferma: ri-chiama l'anteprima con la mappatura corrente
        if (currentStep === 2) {
            const ok = await callPreview();
            if (!ok) return;
        }
        setCurrentStep((s) => Math.min(s + 1, WIZARD_STEPS.length - 1));
    };

    const handlePrevStep = () => {
        setCurrentStep((s) => Math.max(s - 1, 0));
    };

    const toggleRow = (index: number) => {
        setShowDuplicateModal(false);
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
        setShowDuplicateModal(false);
        if (previewData && selectedRows.size === previewData.valid.length) {
            setSelectedRows(new Set());
        } else if (previewData) {
            setSelectedRows(new Set(previewData.valid.map((_, i) => i)));
        }
    };

    const getRowsToImport = () => {
        if (!previewData) return [];
        return previewData.valid
            .filter((_, i) => selectedRows.has(i))
            .map((row) => ({
                date: row.date,
                amount: row.amount,
                description: row.description,
                notes: row.notes,
            }));
    };

    const doImport = (rows: { date: string; amount: number; description: string; notes: string | null }[]) => {
        const rowsWithResolutions = rows.map((row, idx) => {
            const res = duplicateResolutions[idx];
            if (!res) return row;
            return {
                ...row,
                duplicate_action: res.action,
                ...(res.duplicate_transaction_id !== null ? { duplicate_transaction_id: res.duplicate_transaction_id } : {}),
            };
        });
        setImportProcessing(true);
        setImportErrors({});
        router.post(
            route('transactions.import.store'),
            { account_id: data.account_id, rows: rowsWithResolutions },
            {
                onFinish: () => setImportProcessing(false),
                onError:  (errs) => setImportErrors(errs as Record<string, string>),
            },
        );
    };

    const handleImport = async () => {
        if (!previewData || !data.account_id) return;
        const rows = getRowsToImport();
        setDuplicateCheckLoading(true);
        setImportErrors({});
        try {
            const resp = await axios.post<{ duplicates: DuplicateInfo[] }>(
                route('transactions.import.check-duplicates'),
                { account_id: data.account_id, rows },
            );
            const dups = resp.data.duplicates;
            if (dups.length === 0) {
                doImport(rows);
            } else {
                const resolutions: Record<number, DuplicateResolution> = {};
                dups.forEach((d) => {
                    resolutions[d.row_index] = {
                        action: 'import',
                        duplicate_transaction_id: d.existing[0]?.id ?? null,
                    };
                });
                setDuplicates(dups);
                setDuplicateResolutions(resolutions);
                setShowDuplicateModal(true);
            }
        } catch {
            // In caso di errore nel check, procedi comunque con l'import
            doImport(rows);
        } finally {
            setDuplicateCheckLoading(false);
        }
    };

    const handleConfirmImport = () => {
        setShowDuplicateModal(false);
        doImport(getRowsToImport());
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

            <div className="py-2 px-3 sm:py-4 sm:px-6 lg:px-8 max-w-4xl mx-auto">
                <ImportWizardStep steps={steps} className="mb-3 sm:mb-6" />

                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-6">
                    {/* Step 0: Seleziona layout */}
                    {currentStep === 0 && (
                        <div>
                            <div className="flex items-center justify-between mb-1">
                                <h2 className="text-lg font-semibold text-gray-900">Seleziona un layout</h2>
                                <Link
                                    href={route('bank-import-layouts.index')}
                                    className="text-xs font-medium text-blue-600 hover:text-blue-700 underline"
                                >
                                    Gestisci template →
                                </Link>
                            </div>
                            <p className="text-sm text-gray-500 mb-4">
                                Scegli uno dei tuoi layout salvati oppure configura manualmente.
                            </p>

                            {/* Griglia layout personalizzati dell'utente */}
                            {userLayouts.length > 0 ? (
                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    {userLayouts.map((layout) => (
                                        <button
                                            key={layout.id}
                                            type="button"
                                            onClick={() => applyUserLayout(layout)}
                                            className={clsx(
                                                'flex flex-col items-center justify-center gap-2 p-4 rounded-xl border-2 transition-all text-center',
                                                'hover:border-blue-400 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500',
                                                selectedLayoutId === layout.id
                                                    ? 'border-blue-500 bg-blue-50 shadow-sm'
                                                    : 'border-gray-200 bg-white',
                                            )}
                                            aria-pressed={selectedLayoutId === layout.id}
                                            aria-label={`Seleziona layout ${layout.name}`}
                                        >
                                            <span className="text-2xl" aria-hidden="true">⚙️</span>
                                            <span className={clsx(
                                                'text-sm font-medium',
                                                selectedLayoutId === layout.id ? 'text-blue-700' : 'text-gray-700',
                                            )}>
                                                {layout.name}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center py-8 text-center border-2 border-dashed border-gray-200 rounded-xl bg-gray-50">
                                    <span className="text-3xl mb-2" aria-hidden="true">📂</span>
                                    <p className="text-sm font-medium text-gray-700 mb-1">Nessun layout personalizzato trovato</p>
                                    <p className="text-xs text-gray-500 mb-3">Crea un template per importare facilmente i file della tua banca.</p>
                                    <Link
                                        href={route('bank-import-layouts.index')}
                                        className="text-xs font-medium text-blue-600 hover:text-blue-700 underline"
                                    >
                                        Crea il tuo template →
                                    </Link>
                                </div>
                            )}

                            {/* Layout personalizzato manuale – fuori dalla griglia */}
                            <div className="mt-6 pt-4 border-t border-gray-100">
                                <p className="text-sm text-gray-500 mb-2">Oppure configura manualmente:</p>
                                <button
                                    type="button"
                                    onClick={handleSelectCustom}
                                    className={clsx(
                                        'inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 text-sm font-medium transition-all',
                                        'focus:outline-none focus:ring-2 focus:ring-blue-500',
                                        selectedBank === 'custom' && selectedLayoutId === null
                                            ? 'border-blue-500 bg-blue-50 text-blue-700 shadow-sm'
                                            : 'border-gray-200 bg-white text-gray-700 hover:border-blue-400 hover:bg-blue-50',
                                    )}
                                    aria-pressed={selectedBank === 'custom' && selectedLayoutId === null}
                                >
                                    <span aria-hidden="true">⚙️</span>
                                    Layout personalizzato
                                </button>
                            </div>
                        </div>
                    )}

                    {/* Step 1: Carica file */}
                    {currentStep === 1 && (
                        <div className="space-y-5">
                            <h2 className="text-lg font-semibold text-gray-900">Carica il file</h2>

                            {/* File upload */}
                            <div>
                                <InputLabel htmlFor="csv_file" value="File CSV o XLSX *" />
                                <div
                                    className={clsx(
                                        'mt-1 flex flex-col items-center justify-center border-2 border-dashed rounded-lg p-6 cursor-pointer',
                                        csvFile ? 'border-blue-400 bg-blue-50' : 'border-gray-300 bg-gray-50 hover:border-blue-400 hover:bg-blue-50',
                                    )}
                                    onClick={() => fileInputRef.current?.click()}
                                    onKeyDown={(e) => e.key === 'Enter' && fileInputRef.current?.click()}
                                    role="button"
                                    tabIndex={0}
                                    aria-label="Carica file CSV o XLSX"
                                >
                                    {csvFile ? (
                                        <p className="text-sm font-medium text-blue-700">
                                            {isXlsx ? '📊' : '📄'} {csvFile.name}
                                        </p>
                                    ) : (
                                        <>
                                            <p className="text-sm text-gray-500">Trascina il file qui o clicca per selezionarlo</p>
                                            <p className="text-xs text-gray-400 mt-1">CSV, TXT, XLSX – max 5 MB</p>
                                        </>
                                    )}
                                </div>
                                <input
                                    id="csv_file"
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".csv,.txt,.xlsx"
                                    className="hidden"
                                    onChange={handleFileChange}
                                    aria-label="Seleziona file CSV o XLSX"
                                />
                            </div>

                            {/* Configuration */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                {/* Separatore e codifica: solo CSV/TXT */}
                                {!isXlsx && (
                                    <>
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
                                    </>
                                )}

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
                                    {isXlsx && (
                                        <p className="mt-1 text-xs text-gray-400">
                                            Usato solo se le date sono in formato testuale. Le date native Excel vengono lette automaticamente.
                                        </p>
                                    )}
                                </div>

                                <div className="flex items-center gap-3">
                                    <input
                                        id="has_header"
                                        type="checkbox"
                                        checked={hasHeader}
                                        onChange={(e) => setHasHeader(e.target.checked)}
                                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <InputLabel htmlFor="has_header" value="Il file ha una riga di intestazione" className="mb-[0px]" />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 2: Mappa colonne */}
                    {currentStep === 2 && (
                        <div className="space-y-4">
                            <h2 className="text-lg font-semibold text-gray-900">Mappa le colonne</h2>
                            <p className="text-sm text-gray-500">
                                Indica quale colonna del file corrisponde a ciascun campo.
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

                            {/* Salva layout */}
                            <div className="mt-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4">
                                <h3 className="text-sm font-medium text-gray-700 mb-1">Salva questa configurazione come layout</h3>
                                <p className="text-xs text-gray-500 mb-3">
                                    Potrai riutilizzarlo nelle prossime importazioni senza dover riconfigurare le colonne.
                                </p>
                                <div className="flex gap-2 items-start">
                                    <div className="flex-1">
                                        <InputLabel htmlFor="save_layout_name" value="Nome del layout" className="sr-only" />
                                        <input
                                            id="save_layout_name"
                                            type="text"
                                            value={saveLayoutName}
                                            onChange={(e) => { setSaveLayoutName(e.target.value); setSaveLayoutSuccess(null); setSaveLayoutError(null); }}
                                            placeholder="Es. Banca UniCredit – formato mensile"
                                            maxLength={100}
                                            className="block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                            aria-label="Nome del layout da salvare"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        onClick={saveCurrentLayout}
                                        disabled={savingLayout || !saveLayoutName.trim()}
                                        className={clsx(
                                            'inline-flex items-center px-4 py-2 rounded-md text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500',
                                            savingLayout || !saveLayoutName.trim()
                                                ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                : 'bg-blue-600 text-white hover:bg-blue-700',
                                        )}
                                    >
                                        {savingLayout ? 'Salvataggio…' : 'Salva layout'}
                                    </button>
                                </div>
                                {saveLayoutSuccess && (
                                    <p className="mt-2 text-xs text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">
                                        ✓ {saveLayoutSuccess}
                                    </p>
                                )}
                                {saveLayoutError && (
                                    <p className="mt-2 text-xs text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">
                                        {saveLayoutError}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Step 3: Conferma */}
                    {currentStep === 3 && (
                        <form onSubmit={(e) => e.preventDefault()} className="space-y-5">
                            <h2 className="text-lg font-semibold text-gray-900">Anteprima e conferma</h2>

                            {/* Account selector */}
                            <div>
                                <InputLabel htmlFor="account_id" value="Conto di destinazione *" />
                                <p className="mt-0.5 text-xs text-gray-500 mb-2">
                                    Le transazioni importate verranno aggiunte al conto selezionato.
                                </p>

                                {accounts.length <= 8 ? (
                                    /* Card picker per pochi conti */
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-1">
                                        {accounts.map((acc) => {
                                            const selected = String(data.account_id) === String(acc.id);
                                            return (
                                                <button
                                                    key={acc.id}
                                                    type="button"
                                                    onClick={() => setData('account_id', String(acc.id))}
                                                    className={clsx(
                                                        'flex items-center gap-3 rounded-lg border-2 px-4 py-3 text-left transition-all focus:outline-none focus:ring-2 focus:ring-blue-500',
                                                        selected
                                                            ? 'border-blue-500 bg-blue-50 text-blue-900'
                                                            : 'border-gray-200 bg-white text-gray-700 hover:border-blue-300 hover:bg-blue-50/50',
                                                    )}
                                                    aria-pressed={selected}
                                                >
                                                    <span className={clsx(
                                                        'flex h-9 w-9 flex-none items-center justify-center rounded-full text-sm font-bold',
                                                        selected ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-500',
                                                    )}>
                                                        {acc.name.charAt(0).toUpperCase()}
                                                    </span>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate font-medium text-sm">{acc.name}</p>
                                                        <p className={clsx('text-xs', selected ? 'text-blue-600' : 'text-gray-400')}>
                                                            {acc.currency_code}
                                                        </p>
                                                    </div>
                                                    {selected && (
                                                        <span className="flex-none text-blue-500">✓</span>
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    /* Dropdown per molti conti */
                                    <select
                                        id="account_id"
                                        value={data.account_id}
                                        onChange={(e) => setData('account_id', e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                        required
                                    >
                                        <option value="" disabled>— Seleziona un conto —</option>
                                        {accounts.map((acc) => (
                                            <option key={acc.id} value={acc.id}>
                                                {acc.name} ({acc.currency_code})
                                            </option>
                                        ))}
                                    </select>
                                )}

                                {!data.account_id && (
                                    <p className="mt-1.5 text-xs text-amber-600">
                                        ⚠️ Seleziona un conto per procedere con l&apos;importazione.
                                    </p>
                                )}
                                <InputError message={importErrors.account_id} className="mt-1" />
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

                            <InputError message={importErrors.rows} className="mt-1" />
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
                                    disabled={selectedRows.size === 0 || importProcessing || duplicateCheckLoading || !data.account_id}
                                >
                                    {importProcessing ? 'Importazione…' : duplicateCheckLoading ? 'Verifica duplicati…' : `Importa ${selectedRows.size} transazioni`}
                                </PrimaryButton>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal risoluzione duplicati */}
            {showDuplicateModal && (
                <div
                    className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="duplicate-modal-title"
                >
                    <div className="relative w-full max-w-2xl my-8 bg-white rounded-xl shadow-xl">
                        <div className="p-5 border-b border-gray-200">
                            <h2 id="duplicate-modal-title" className="text-lg font-semibold text-gray-900">
                                &#9888;&#65039; {duplicates.length} transazion{duplicates.length === 1 ? 'e potenzialmente duplicata' : 'i potenzialmente duplicate'}
                            </h2>
                            <p className="mt-1 text-sm text-gray-500">
                                Sono state trovate transazioni già presenti nel conto con la stessa data e importo. Scegli come gestire ognuna.
                            </p>
                            <div className="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-500">
                                <span><strong className="text-blue-700">Importa comunque</strong> — crea una nuova transazione (doppia)</span>
                                <span><strong className="text-gray-700">Ignora</strong> — non importare questa riga</span>
                                <span><strong className="text-red-700">Sostituisci</strong> — elimina la vecchia, crea la nuova</span>
                                <span><strong className="text-green-700">Aggiorna</strong> — sovrascrive i dati della transazione esistente</span>
                            </div>
                        </div>

                        <div className="p-5 space-y-4 max-h-[55vh] overflow-y-auto">
                            {duplicates.map((dup) => {
                                const res = duplicateResolutions[dup.row_index] ?? { action: 'import' as DuplicateAction, duplicate_transaction_id: dup.existing[0]?.id ?? null };
                                const showExistingSelect = (res.action === 'replace' || res.action === 'update') && dup.existing.length > 1;
                                return (
                                    <div key={dup.row_index} className="rounded-lg border border-amber-200 bg-amber-50 p-4">
                                        <div className="flex flex-col sm:flex-row gap-3 mb-3">
                                            <div className="flex-1 min-w-0">
                                                <p className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Dal file</p>
                                                <p className={clsx('text-sm font-bold', dup.amount >= 0 ? 'text-green-700' : 'text-red-700')}>{formatAmount(dup.amount)}</p>
                                                <p className="text-xs text-gray-600">{new Date(dup.date).toLocaleDateString('it-IT')}</p>
                                                <p className="text-xs text-gray-500 truncate">{dup.description}</p>
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <p className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Già presente</p>
                                                {dup.existing.map((ex) => (
                                                    <div key={ex.id} className="border-l-2 border-amber-400 pl-2 mb-1">
                                                        <p className={clsx('text-xs font-bold', Number(ex.amount) >= 0 ? 'text-green-700' : 'text-red-700')}>{formatAmount(Number(ex.amount))}</p>
                                                        <p className="text-xs text-gray-600">{new Date(ex.date).toLocaleDateString('it-IT')}</p>
                                                        <p className="text-xs text-gray-500 truncate">{ex.description}</p>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap gap-2">
                                            {(['import', 'ignore', 'replace', 'update'] as DuplicateAction[]).map((action) => {
                                                const labels: Record<DuplicateAction, string> = {
                                                    import: 'Importa comunque',
                                                    ignore: 'Ignora',
                                                    replace: 'Sostituisci',
                                                    update: 'Aggiorna',
                                                };
                                                const activeColors: Record<DuplicateAction, string> = {
                                                    import: 'bg-blue-600 text-white border-blue-600',
                                                    ignore: 'bg-gray-600 text-white border-gray-600',
                                                    replace: 'bg-red-600 text-white border-red-600',
                                                    update: 'bg-green-600 text-white border-green-600',
                                                };
                                                const inactiveColors: Record<DuplicateAction, string> = {
                                                    import: 'bg-white text-blue-700 border-blue-300 hover:bg-blue-50',
                                                    ignore: 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                                                    replace: 'bg-white text-red-700 border-red-300 hover:bg-red-50',
                                                    update: 'bg-white text-green-700 border-green-300 hover:bg-green-50',
                                                };
                                                return (
                                                    <button
                                                        key={action}
                                                        type="button"
                                                        onClick={() => setDuplicateResolutions((prev) => ({
                                                            ...prev,
                                                            [dup.row_index]: {
                                                                action,
                                                                duplicate_transaction_id: prev[dup.row_index]?.duplicate_transaction_id ?? dup.existing[0]?.id ?? null,
                                                            },
                                                        }))}
                                                        className={clsx(
                                                            'px-3 py-1.5 text-xs font-medium rounded-md border transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500',
                                                            res.action === action ? activeColors[action] : inactiveColors[action],
                                                        )}
                                                    >
                                                        {labels[action]}
                                                    </button>
                                                );
                                            })}
                                        </div>

                                        {showExistingSelect && (
                                            <div className="mt-2">
                                                <label className="text-xs text-gray-600 block mb-1">
                                                    Transazione da {res.action === 'replace' ? 'sostituire' : 'aggiornare'}:
                                                </label>
                                                <select
                                                    value={res.duplicate_transaction_id ?? ''}
                                                    onChange={(e) => setDuplicateResolutions((prev) => ({
                                                        ...prev,
                                                        [dup.row_index]: { ...prev[dup.row_index], duplicate_transaction_id: Number(e.target.value) },
                                                    }))}
                                                    className="block w-full text-xs border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                                >
                                                    {dup.existing.map((ex) => (
                                                        <option key={ex.id} value={ex.id}>
                                                            {formatAmount(Number(ex.amount))} — {new Date(ex.date).toLocaleDateString('it-IT')} — {ex.description}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>

                        <div className="p-5 border-t border-gray-200 flex items-center justify-between gap-3">
                            <button
                                type="button"
                                onClick={() => setShowDuplicateModal(false)}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                Annulla
                            </button>
                            <PrimaryButton type="button" onClick={handleConfirmImport} disabled={importProcessing}>
                                {importProcessing ? 'Importazione…' : 'Conferma e importa'}
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
