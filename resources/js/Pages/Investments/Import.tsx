import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ImportWizardStep from '@/Components/ImportWizardStep';
import InvestmentColumnMapper, { InvestmentColumnMapping } from '@/Components/InvestmentColumnMapper';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LinkButton from '@/Components/LinkButton';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import clsx from 'clsx';
import { useRef, useState } from 'react';

// ────────────────────────────── Interfaces ──────────────────────────────────

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface Asset {
    id: number;
    name: string;
    symbol: string | null;
    isin: string | null;
    type: string;
    currency_code: string;
}

interface UserLayout {
    id: number;
    name: string;
    bank_name: string;
    icon: string | null;
    column_mapping: InvestmentColumnMapping;
    delimiter: string;
    date_format: string;
    has_header: boolean;
    encoding: string;
}

interface InvestmentImportRow {
    line_number: number;
    buy_date: string | null;
    quantity: number | null;
    buy_price: number | null;
    ticker: string | null;
    isin: string | null;
    fees: number | null;
    notes: string | null;
    raw: string;
    errors: string[];
    // Campi aggiunti dal backend dopo resolveAssets
    asset_id: number | null;
    asset_name: string | null;
    asset_symbol: string | null;
    asset_missing: boolean;
}

interface PreviewResponse {
    headers: string[];
    valid: InvestmentImportRow[];
    invalid: InvestmentImportRow[];
    total: number;
    valid_count: number;
    invalid_count: number;
    missing_asset_count: number;
}

/** Asset override scelto dall'utente per un ticker/ISIN non trovato */
type AssetOverrides = Record<number, number>; // line_number → asset_id

interface ImportProps {
    accounts: Account[];
    userLayouts: UserLayout[];
    assets: Asset[];
    assetTypes: Record<string, string>;
}

// ────────────────────────────── Costanti ────────────────────────────────────

const WIZARD_STEPS = [
    'Carica file',
    'Mappa colonne',
    'Anteprima',
    'Conferma',
];

const DELIMITER_OPTIONS = [
    { value: ';',  label: 'Punto e virgola (;)' },
    { value: ',',  label: 'Virgola (,)' },
    { value: '\t', label: 'Tab' },
];

const ENCODING_OPTIONS = [
    { value: 'UTF-8',        label: 'UTF-8' },
    { value: 'ISO-8859-1',   label: 'ISO-8859-1 (Latin-1)' },
    { value: 'Windows-1252', label: 'Windows-1252' },
];

const DATE_FORMAT_OPTIONS = [
    { value: 'd/m/Y', label: 'GG/MM/AAAA' },
    { value: 'Y-m-d', label: 'AAAA-MM-GG' },
    { value: 'm/d/Y', label: 'MM/GG/AAAA' },
    { value: 'd-m-Y', label: 'GG-MM-AAAA' },
];

const LAYOUT_ICONS = ['📊', '📈', '₿', '🏦', '💳', '💰', '🪙', '📉', '🏧', '💵', '🏛️', '⚙️'];

// ────────────────────────────── Componente ──────────────────────────────────

export default function Import({ accounts, userLayouts: initialLayouts, assets, assetTypes }: ImportProps) {
    const [currentStep, setCurrentStep] = useState(0);

    // Impostazioni file
    const [csvFile, setCsvFile] = useState<File | null>(null);
    const [delimiter, setDelimiter] = useState(';');
    const [dateFormat, setDateFormat] = useState('d/m/Y');
    const [hasHeader, setHasHeader] = useState(true);
    const [encoding, setEncoding] = useState('UTF-8');
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Column mapping
    const [columnMapping, setColumnMapping] = useState<InvestmentColumnMapping>({
        buy_date:  0,
        quantity:  1,
        buy_price: 2,
        ticker:    3,
        isin:      null,
        fees:      null,
        notes:     null,
    });

    // Preview
    const [previewData, setPreviewData] = useState<PreviewResponse | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [selectedRows, setSelectedRows] = useState<Set<number>>(new Set());

    // Asset overrides (per righe con asset_missing = true)
    const [assetOverrides, setAssetOverrides] = useState<AssetOverrides>({});

    // Import
    const [accountId, setAccountId] = useState<string>('');
    const [createCashTransaction, setCreateCashTransaction] = useState(false);
    const [importProcessing, setImportProcessing] = useState(false);
    const [importErrors, setImportErrors] = useState<Record<string, string>>({});

    // Layout salvati
    const [userLayouts, setUserLayouts] = useState<UserLayout[]>(initialLayouts);
    const [selectedLayoutId, setSelectedLayoutId] = useState<number | null>(null);
    const [saveLayoutName, setSaveLayoutName] = useState('');
    const [saveLayoutIcon, setSaveLayoutIcon] = useState('📊');
    const [savingLayout, setSavingLayout] = useState(false);
    const [saveLayoutSuccess, setSaveLayoutSuccess] = useState<string | null>(null);
    const [saveLayoutError, setSaveLayoutError] = useState<string | null>(null);

    const isXlsx = csvFile?.name.toLowerCase().endsWith('.xlsx') ?? false;

    // ─── Helpers ────────────────────────────────────────────────────────────

    const applyLayout = (layout: UserLayout) => {
        setSelectedLayoutId(layout.id);
        setDelimiter(layout.delimiter);
        setDateFormat(layout.date_format);
        setHasHeader(layout.has_header);
        setEncoding(layout.encoding);
        setColumnMapping(layout.column_mapping);
    };

    const isMappingValid = () => {
        const hasTicker = columnMapping.ticker !== null;
        const hasIsin   = columnMapping.isin    !== null;
        return (
            columnMapping.buy_date  !== null &&
            columnMapping.quantity  !== null &&
            columnMapping.buy_price !== null &&
            (hasTicker || hasIsin)
        );
    };

    const resolvedRows = (): InvestmentImportRow[] => {
        if (!previewData) return [];
        return previewData.valid.map((row) => {
            if (!row.asset_missing) return row;
            const overrideId = assetOverrides[row.line_number];
            if (overrideId) {
                const asset = assets.find((a) => a.id === overrideId);
                return {
                    ...row,
                    asset_id:      overrideId,
                    asset_name:    asset?.name    ?? null,
                    asset_symbol:  asset?.symbol  ?? null,
                    asset_missing: false,
                };
            }
            return row;
        });
    };

    const allAssetsResolved = (): boolean =>
        resolvedRows().every((r) => !r.asset_missing);

    const selectedValidRows = (): InvestmentImportRow[] =>
        resolvedRows().filter((r) => selectedRows.has(r.line_number) && !r.asset_missing);

    // ─── API Calls ──────────────────────────────────────────────────────────

    const runPreview = async () => {
        if (!csvFile) return;
        setPreviewLoading(true);
        setPreviewError(null);

        const formData = new FormData();
        formData.append('csv_file', csvFile);
        formData.append('delimiter', isXlsx ? ',' : delimiter);
        formData.append('date_format', dateFormat);
        formData.append('has_header', hasHeader ? '1' : '0');
        formData.append('encoding', encoding);
        // Append column_mapping fields
        (Object.entries(columnMapping) as [string, number | null][]).forEach(([k, v]) => {
            if (v !== null) formData.append(`column_mapping[${k}]`, String(v));
        });

        try {
            const response = await axios.post<PreviewResponse>(
                route('investments.import.preview'),
                formData,
                { headers: { 'Content-Type': 'multipart/form-data' } },
            );
            setPreviewData(response.data);
            setSelectedRows(
                new Set(response.data.valid.map((r) => r.line_number))
            );
            setAssetOverrides({});
            setCurrentStep(2);
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.message) {
                setPreviewError(err.response.data.message as string);
            } else {
                setPreviewError('Errore durante l\'anteprima. Verifica il file e le impostazioni.');
            }
        } finally {
            setPreviewLoading(false);
        }
    };

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
                route('investments.import.layouts.store'),
                {
                    name:           saveLayoutName.trim(),
                    bank_name:      'custom',
                    icon:           saveLayoutIcon,
                    delimiter:      isXlsx ? ',' : delimiter,
                    date_format:    dateFormat,
                    has_header:     hasHeader,
                    encoding:       encoding,
                    column_mapping: columnMapping,
                },
            );
            if (response.data.success) {
                setUserLayouts((prev) => [...prev, response.data.layout]);
                setSelectedLayoutId(response.data.layout.id);
                setSaveLayoutSuccess('Layout salvato con successo!');
                setSaveLayoutName('');
            }
        } catch {
            setSaveLayoutError('Errore nel salvataggio del layout.');
        } finally {
            setSavingLayout(false);
        }
    };

    const handleImport = () => {
        const rows = selectedValidRows();
        if (rows.length === 0) return;

        setImportProcessing(true);
        setImportErrors({});

        const payload = {
            account_id:              accountId || null,
            create_cash_transaction: createCashTransaction,
            rows:                    rows.map((r) => ({
                buy_date:   r.buy_date,
                quantity:   r.quantity,
                buy_price:  r.buy_price,
                asset_id:   r.asset_id,
                fees:       r.fees   ?? null,
                notes:      r.notes  ?? null,
                is_private: false,
            })),
        };

        router.post(route('investments.import.store'), payload, {
            onError: (errors) => {
                setImportErrors(errors as Record<string, string>);
                setImportProcessing(false);
            },
        });
    };

    // ─── Wizard step helpers ─────────────────────────────────────────────────

    const goNext = () => setCurrentStep((s) => Math.min(s + 1, WIZARD_STEPS.length - 1));
    const goBack = () => setCurrentStep((s) => Math.max(s - 1, 0));

    // ─── Render ──────────────────────────────────────────────────────────────

    const wizardSteps = WIZARD_STEPS.map((label, i) => ({
        label,
        completed: currentStep > i,
        active:    currentStep === i,
    }));

    return (
        <AuthenticatedLayout>
            <Head title="Importa Investimenti" />

            <div className="py-6">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Importa Investimenti da CSV"
                        subtitle="Carica un file CSV o XLSX per importare i tuoi investimenti in blocco."
                    />

                    {/* Wizard progress */}
                    <div className="mt-6 mb-8">
                        <ImportWizardStep steps={wizardSteps} />
                    </div>

                    {/* ── Step 0: Carica file ──────────────────────────────── */}
                    {currentStep === 0 && (
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow p-6 space-y-6">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Carica il file CSV / XLSX
                            </h2>

                            {/* Layout salvati */}
                            {userLayouts.length > 0 && (
                                <div>
                                    <InputLabel value="Layout salvati" />
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {userLayouts.map((layout) => (
                                            <button
                                                key={layout.id}
                                                type="button"
                                                onClick={() => applyLayout(layout)}
                                                className={clsx(
                                                    'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm border transition',
                                                    selectedLayoutId === layout.id
                                                        ? 'bg-indigo-600 text-white border-indigo-600'
                                                        : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 hover:border-indigo-400',
                                                )}
                                            >
                                                {layout.icon && <span>{layout.icon}</span>}
                                                {layout.name}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* File upload */}
                            <div>
                                <InputLabel value="File CSV o XLSX *" />
                                <div
                                    className={clsx(
                                        'mt-2 border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition',
                                        csvFile
                                            ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/20'
                                            : 'border-gray-300 hover:border-indigo-400',
                                    )}
                                    onClick={() => fileInputRef.current?.click()}
                                    onKeyDown={(e) => e.key === 'Enter' && fileInputRef.current?.click()}
                                    role="button"
                                    tabIndex={0}
                                    aria-label="Clicca per selezionare un file"
                                >
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept=".csv,.txt,.xlsx"
                                        className="hidden"
                                        onChange={(e) => setCsvFile(e.target.files?.[0] ?? null)}
                                    />
                                    {csvFile ? (
                                        <p className="text-indigo-700 dark:text-indigo-300 font-medium">
                                            📄 {csvFile.name} ({(csvFile.size / 1024).toFixed(1)} KB)
                                        </p>
                                    ) : (
                                        <p className="text-gray-500 dark:text-gray-400">
                                            Clicca o trascina il file CSV / XLSX qui
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Impostazioni CSV */}
                            {!isXlsx && (
                                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <InputLabel value="Separatore" />
                                        <select
                                            value={delimiter}
                                            onChange={(e) => setDelimiter(e.target.value)}
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                        >
                                            {DELIMITER_OPTIONS.map((o) => (
                                                <option key={o.value} value={o.value}>{o.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel value="Codifica" />
                                        <select
                                            value={encoding}
                                            onChange={(e) => setEncoding(e.target.value)}
                                            className="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                        >
                                            {ENCODING_OPTIONS.map((o) => (
                                                <option key={o.value} value={o.value}>{o.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="flex items-end gap-2">
                                        <label className="flex items-center gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={hasHeader}
                                                onChange={(e) => setHasHeader(e.target.checked)}
                                                className="rounded text-indigo-600"
                                            />
                                            <span className="text-sm text-gray-700 dark:text-gray-200">
                                                Prima riga = intestazione
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            )}

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Formato data" />
                                    <select
                                        value={dateFormat}
                                        onChange={(e) => setDateFormat(e.target.value)}
                                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                    >
                                        {DATE_FORMAT_OPTIONS.map((o) => (
                                            <option key={o.value} value={o.value}>{o.label}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="flex justify-between pt-2">
                                <LinkButton href={route('investments.index')}>
                                    ← Torna agli investimenti
                                </LinkButton>
                                <PrimaryButton
                                    disabled={!csvFile}
                                    onClick={goNext}
                                >
                                    Avanti →
                                </PrimaryButton>
                            </div>
                        </div>
                    )}

                    {/* ── Step 1: Mappa colonne ──────────────────────────── */}
                    {currentStep === 1 && (
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow p-6 space-y-6">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Mappatura colonne
                            </h2>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                Indica quale colonna del file corrisponde a ciascun campo dell'investimento.
                            </p>

                            {previewError && (
                                <div className="rounded-md bg-red-50 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-300">
                                    {previewError}
                                </div>
                            )}

                            <InvestmentColumnMapper
                                headers={[]}
                                columnCount={10}
                                mapping={columnMapping}
                                onChange={setColumnMapping}
                            />

                            {/* Salva layout */}
                            <div className="border-t pt-4 dark:border-gray-700">
                                <p className="text-sm font-medium text-gray-700 dark:text-gray-200 mb-3">
                                    Salva questa configurazione come layout
                                </p>
                                <div className="flex flex-wrap gap-2 mb-3">
                                    {LAYOUT_ICONS.map((icon) => (
                                        <button
                                            key={icon}
                                            type="button"
                                            onClick={() => setSaveLayoutIcon(icon)}
                                            className={clsx(
                                                'text-xl rounded-lg w-9 h-9 flex items-center justify-center border transition',
                                                saveLayoutIcon === icon
                                                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30'
                                                    : 'border-gray-200 dark:border-gray-600 hover:border-indigo-300',
                                            )}
                                            aria-label={`Icona ${icon}`}
                                        >
                                            {icon}
                                        </button>
                                    ))}
                                </div>
                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        placeholder="Nome layout (es. 'Interactive Brokers')"
                                        value={saveLayoutName}
                                        onChange={(e) => setSaveLayoutName(e.target.value)}
                                        className="flex-1 border-gray-300 rounded-md shadow-sm text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                    />
                                    <button
                                        type="button"
                                        onClick={saveCurrentLayout}
                                        disabled={savingLayout || !saveLayoutName.trim()}
                                        className="px-4 py-2 text-sm font-medium text-indigo-600 border border-indigo-300 rounded-md hover:bg-indigo-50 disabled:opacity-50 dark:text-indigo-400 dark:border-indigo-600 dark:hover:bg-indigo-900/20"
                                    >
                                        {savingLayout ? 'Salvataggio…' : 'Salva layout'}
                                    </button>
                                </div>
                                {saveLayoutSuccess && (
                                    <p className="mt-2 text-sm text-green-600 dark:text-green-400">{saveLayoutSuccess}</p>
                                )}
                                {saveLayoutError && (
                                    <p className="mt-2 text-sm text-red-600 dark:text-red-400">{saveLayoutError}</p>
                                )}
                            </div>

                            <div className="flex justify-between pt-2">
                                <button
                                    type="button"
                                    onClick={goBack}
                                    className="text-sm text-gray-600 dark:text-gray-300 hover:underline"
                                >
                                    ← Indietro
                                </button>
                                <PrimaryButton
                                    disabled={!isMappingValid() || previewLoading}
                                    onClick={runPreview}
                                >
                                    {previewLoading ? 'Analisi in corso…' : 'Anteprima →'}
                                </PrimaryButton>
                            </div>
                        </div>
                    )}

                    {/* ── Step 2: Anteprima + asset mancanti ───────────────── */}
                    {currentStep === 2 && previewData && (
                        <div className="space-y-6">
                            {/* Riepilogo statistiche */}
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                {[
                                    { label: 'Totale righe',    value: previewData.total,               color: 'bg-gray-100 dark:bg-gray-700' },
                                    { label: 'Valide',          value: previewData.valid_count,          color: 'bg-green-50 dark:bg-green-900/20' },
                                    { label: 'Non valide',      value: previewData.invalid_count,        color: 'bg-red-50 dark:bg-red-900/20' },
                                    { label: 'Asset mancanti',  value: previewData.missing_asset_count,  color: previewData.missing_asset_count > 0 ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-green-50 dark:bg-green-900/20' },
                                ].map(({ label, value, color }) => (
                                    <div key={label} className={clsx('rounded-xl p-4 text-center', color)}>
                                        <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{value}</p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{label}</p>
                                    </div>
                                ))}
                            </div>

                            {/* Righe con asset mancante */}
                            {resolvedRows().some((r) => r.asset_missing) && (
                                <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4">
                                    <h3 className="font-semibold text-amber-800 dark:text-amber-200 mb-3">
                                        ⚠️ Asset non trovati — seleziona un asset per ogni riga
                                    </h3>
                                    <div className="space-y-3">
                                        {resolvedRows()
                                            .filter((r) => r.asset_missing)
                                            .map((row) => (
                                                <div key={row.line_number} className="flex flex-wrap items-center gap-3 bg-white dark:bg-gray-800 rounded-lg p-3">
                                                    <span className="text-sm text-gray-700 dark:text-gray-300 flex-shrink-0">
                                                        Riga {row.line_number}:
                                                        {row.ticker && <strong> {row.ticker}</strong>}
                                                        {row.isin   && <strong> ({row.isin})</strong>}
                                                    </span>
                                                    <select
                                                        className="flex-1 min-w-[200px] border-gray-300 rounded-md text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                                        value={assetOverrides[row.line_number] ?? ''}
                                                        onChange={(e) => {
                                                            const val = e.target.value;
                                                            setAssetOverrides((prev) => {
                                                                const next = { ...prev };
                                                                if (val === '') {
                                                                    delete next[row.line_number];
                                                                } else {
                                                                    next[row.line_number] = Number(val);
                                                                }
                                                                return next;
                                                            });
                                                        }}
                                                    >
                                                        <option value="">— Seleziona asset esistente —</option>
                                                        {assets.map((a) => (
                                                            <option key={a.id} value={a.id}>
                                                                {a.symbol ? `[${a.symbol}] ` : ''}{a.name}
                                                                {a.isin ? ` — ISIN: ${a.isin}` : ''}
                                                            </option>
                                                        ))}
                                                    </select>
                                                    <a
                                                        href={route('investment-assets.create')}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline whitespace-nowrap"
                                                    >
                                                        + Crea nuovo asset ↗
                                                    </a>
                                                </div>
                                            ))}
                                    </div>
                                    {!allAssetsResolved() && (
                                        <p className="mt-3 text-sm text-amber-700 dark:text-amber-300">
                                            Risolvi tutti gli asset mancanti per procedere con l'importazione.
                                        </p>
                                    )}
                                </div>
                            )}

                            {/* Tabella righe valide */}
                            {previewData.valid_count > 0 && (
                                <div className="bg-white dark:bg-gray-800 rounded-xl shadow overflow-hidden">
                                    <div className="flex items-center justify-between px-4 py-3 border-b dark:border-gray-700">
                                        <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                            Righe valide ({previewData.valid_count})
                                        </h3>
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                className="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                                                onClick={() => setSelectedRows(new Set(resolvedRows().map((r) => r.line_number)))}
                                            >
                                                Seleziona tutto
                                            </button>
                                            <span className="text-xs text-gray-400">|</span>
                                            <button
                                                type="button"
                                                className="text-xs text-gray-500 hover:underline"
                                                onClick={() => setSelectedRows(new Set())}
                                            >
                                                Deseleziona tutto
                                            </button>
                                        </div>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                            <thead className="bg-gray-50 dark:bg-gray-900">
                                                <tr>
                                                    <th className="px-3 py-2 text-left w-8"></th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Riga</th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data</th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Asset</th>
                                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Quantità</th>
                                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Prezzo</th>
                                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Totale</th>
                                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Comm.</th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Stato</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                                {resolvedRows().map((row) => (
                                                    <tr
                                                        key={row.line_number}
                                                        className={clsx(
                                                            'hover:bg-gray-50 dark:hover:bg-gray-700/50',
                                                            row.asset_missing && 'opacity-60',
                                                        )}
                                                    >
                                                        <td className="px-3 py-2">
                                                            <input
                                                                type="checkbox"
                                                                checked={selectedRows.has(row.line_number) && !row.asset_missing}
                                                                disabled={row.asset_missing}
                                                                onChange={(e) => {
                                                                    setSelectedRows((prev) => {
                                                                        const next = new Set(prev);
                                                                        if (e.target.checked) next.add(row.line_number);
                                                                        else next.delete(row.line_number);
                                                                        return next;
                                                                    });
                                                                }}
                                                                className="rounded text-indigo-600"
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2 text-gray-500 dark:text-gray-400">{row.line_number}</td>
                                                        <td className="px-3 py-2 text-gray-900 dark:text-gray-100 whitespace-nowrap">{row.buy_date}</td>
                                                        <td className="px-3 py-2">
                                                            {row.asset_missing ? (
                                                                <span className="text-amber-600 dark:text-amber-400 text-xs">
                                                                    {row.ticker ?? row.isin ?? '?'} — non trovato
                                                                </span>
                                                            ) : (
                                                                <span className="text-gray-900 dark:text-gray-100">
                                                                    {row.asset_symbol && (
                                                                        <span className="font-mono text-xs text-gray-500 mr-1">[{row.asset_symbol}]</span>
                                                                    )}
                                                                    {row.asset_name}
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                                                            {row.quantity?.toLocaleString('it-IT', { maximumFractionDigits: 8 })}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-gray-900 dark:text-gray-100">
                                                            {row.buy_price?.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 8 })}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">
                                                            {row.quantity !== null && row.buy_price !== null
                                                                ? (row.quantity * row.buy_price).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                                                                : '—'}
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-gray-500 dark:text-gray-400">
                                                            {row.fees !== null
                                                                ? row.fees.toLocaleString('it-IT', { minimumFractionDigits: 2 })
                                                                : '—'}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {row.asset_missing ? (
                                                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                                                    Asset mancante
                                                                </span>
                                                            ) : (
                                                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                                                    ✓ Pronto
                                                                </span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}

                            {/* Righe non valide */}
                            {previewData.invalid_count > 0 && (
                                <details className="bg-red-50 dark:bg-red-900/20 rounded-xl p-4">
                                    <summary className="cursor-pointer font-medium text-red-700 dark:text-red-300">
                                        Righe non valide ({previewData.invalid_count}) — clicca per espandere
                                    </summary>
                                    <div className="mt-3 space-y-2">
                                        {previewData.invalid.map((row) => (
                                            <div key={row.line_number} className="text-sm bg-white dark:bg-gray-800 rounded-lg p-3">
                                                <span className="font-medium text-gray-700 dark:text-gray-200">
                                                    Riga {row.line_number}:
                                                </span>
                                                <ul className="mt-1 ml-4 list-disc text-red-600 dark:text-red-400">
                                                    {row.errors.map((e, i) => (
                                                        <li key={i}>{e}</li>
                                                    ))}
                                                </ul>
                                            </div>
                                        ))}
                                    </div>
                                </details>
                            )}

                            <div className="flex justify-between">
                                <button
                                    type="button"
                                    onClick={() => setCurrentStep(1)}
                                    className="text-sm text-gray-600 dark:text-gray-300 hover:underline"
                                >
                                    ← Indietro
                                </button>
                                <PrimaryButton
                                    disabled={selectedValidRows().length === 0 || !allAssetsResolved()}
                                    onClick={() => setCurrentStep(3)}
                                >
                                    Conferma ({selectedValidRows().length}) →
                                </PrimaryButton>
                            </div>
                        </div>
                    )}

                    {/* ── Step 3: Conferma ──────────────────────────────────── */}
                    {currentStep === 3 && (
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow p-6 space-y-6">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                Conferma importazione
                            </h2>

                            <div className="bg-indigo-50 dark:bg-indigo-900/20 rounded-xl p-4">
                                <p className="text-indigo-800 dark:text-indigo-200 font-medium">
                                    Stai per importare{' '}
                                    <strong>{selectedValidRows().length}</strong>{' '}
                                    {selectedValidRows().length === 1 ? 'investimento' : 'investimenti'}.
                                    L'operazione è atomica: in caso di errore nessun dato verrà salvato.
                                </p>
                                <p className="mt-2 text-sm text-indigo-600 dark:text-indigo-300">
                                    Valore totale acquistato:{' '}
                                    <strong>
                                        {selectedValidRows()
                                            .reduce((sum, r) => sum + (r.quantity ?? 0) * (r.buy_price ?? 0), 0)
                                            .toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €
                                    </strong>
                                </p>
                            </div>

                            {/* Conto (opzionale) */}
                            <div>
                                <InputLabel value="Conto associato (opzionale)" />
                                <select
                                    value={accountId}
                                    onChange={(e) => setAccountId(e.target.value)}
                                    className="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"
                                >
                                    <option value="">— Nessun conto —</option>
                                    {accounts.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.name} ({a.currency_code})
                                        </option>
                                    ))}
                                </select>
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Se selezioni un conto puoi anche registrare automaticamente la spesa in uscita.
                                </p>
                            </div>

                            {/* Opzione transazione cash */}
                            {accountId && (
                                <label className="flex items-start gap-3 p-4 border border-indigo-200 dark:border-indigo-700 rounded-xl cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={createCashTransaction}
                                        onChange={(e) => setCreateCashTransaction(e.target.checked)}
                                        className="mt-0.5 rounded text-indigo-600"
                                    />
                                    <div>
                                        <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            Registra la spesa nel conto selezionato
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                            Per ogni investimento importato verrà creata automaticamente una transazione
                                            di uscita nella categoria "Investimento".
                                        </p>
                                    </div>
                                </label>
                            )}

                            {Object.keys(importErrors).length > 0 && (
                                <div className="rounded-md bg-red-50 dark:bg-red-900/20 p-4">
                                    {Object.values(importErrors).map((e, i) => (
                                        <p key={i} className="text-sm text-red-700 dark:text-red-300">{e}</p>
                                    ))}
                                </div>
                            )}

                            <div className="flex justify-between pt-2">
                                <button
                                    type="button"
                                    onClick={() => setCurrentStep(2)}
                                    className="text-sm text-gray-600 dark:text-gray-300 hover:underline"
                                >
                                    ← Indietro
                                </button>
                                <PrimaryButton
                                    disabled={importProcessing || selectedValidRows().length === 0}
                                    onClick={handleImport}
                                >
                                    {importProcessing
                                        ? 'Importazione in corso…'
                                        : `Importa ${selectedValidRows().length} investiment${selectedValidRows().length === 1 ? 'o' : 'i'}`}
                                </PrimaryButton>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
