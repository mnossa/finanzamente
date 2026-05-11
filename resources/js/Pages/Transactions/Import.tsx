import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import ColumnMapper from '@/Components/ColumnMapper';
import GoogleDrivePicker, { type DriveFile } from '@/Components/GoogleDrivePicker';
import ImportWizardStep from '@/Components/ImportWizardStep';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LinkButton from '@/Components/LinkButton';
import PageHeader from '@/Components/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SheetSelector, { type SheetInfo } from '@/Components/SheetSelector';
import { Head, Link, router, useForm } from '@inertiajs/react';
import axios from 'axios';
import clsx from 'clsx';
import { useRef, useState } from 'react';

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface Category {
    id: number;
    name: string;
    type: 'income' | 'expense';
    color: string | null;
    icon: string | null;
}

interface UserLayout {
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
        currency?: number | null;
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
    category_name: string | null;
    account_name: string | null;
    currency_code: string | null;
    raw: string;
    errors: string[];
    warnings: string[];
}

interface PreviewResponse {
    headers: string[];
    valid: ImportRow[];
    invalid: ImportRow[];
    total: number;
    valid_count: number;
    invalid_count: number;
    unique_categories: string[];
    unique_accounts: string[];
    unique_currencies: string[];
}

interface ColumnMapping {
    date: number | null;
    amount: number | null;
    description: number | null;
    notes: number | null;
    category: number | null;
    account: number | null;
    currency: number | null;
}

interface CategoryMappingEntry {
    action: 'existing' | 'create' | 'none';
    category_id: number | null;
    type: 'income' | 'expense' | null;
    suggested?: boolean;
}

interface AccountMappingEntry {
    action: 'existing' | 'create';
    account_id: number | null;
    currency_code: string;
    type: 'bank' | 'cash' | 'card' | 'broker' | 'crypto' | 'other';
    suggested?: boolean;
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
    userLayouts: UserLayout[];
    categories: Category[];
    currencies: { code: string; name: string; symbol: string }[];
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

const LAYOUT_ICONS = ['🏦', '💳', '💰', '🪙', '📊', '📈', '🏧', '💵', '📮', '🏛️', '💹', '⚙️'];

// ─────────────────────────────────────────────────────────────────────────────
// Auto-suggest helpers per la mappatura categorie/conti
// ─────────────────────────────────────────────────────────────────────────────

/** Normalizza una stringa per il confronto: lowercase, senza accenti, senza simboli */
function normalizeForMatch(s: string): string {
    return s
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Gruppi di sinonimi per categorie finanziarie italiane.
 * Ogni array contiene varianti dello stesso concetto.
 */
const CATEGORY_SYNONYM_GROUPS: string[][] = [
    ['alimentari', 'spesa', 'supermercato', 'grocery', 'cibo', 'food', 'freschi', 'mercato', 'frutta', 'verdura'],
    ['ristorazione', 'ristorante', 'bar', 'caffe', 'pizza', 'pranzo', 'cena', 'trattoria', 'fast food', 'mensa', 'osteria', 'aperitivo'],
    ['trasporto', 'benzina', 'carburante', 'gasolio', 'auto', 'autobus', 'treno', 'taxi', 'uber', 'parcheggio', 'pedaggi', 'autostrada', 'mobilita', 'metro', 'bus'],
    ['salute', 'farmacia', 'medico', 'dottore', 'ospedale', 'visita', 'analisi', 'dentista', 'ottico', 'sanita', 'cure', 'medicina'],
    ['abbigliamento', 'vestiti', 'scarpe', 'moda', 'abbigliamento sportivo', 'calzature'],
    ['intrattenimento', 'cinema', 'teatro', 'concerti', 'fitness', 'palestra', 'hobby', 'svago', 'giochi', 'divertimento', 'leisure'],
    ['casa', 'affitto', 'mutuo', 'condominio', 'manutenzione', 'mobili', 'arredamento', 'elettrodomestici', 'abitazione', 'immobile'],
    ['utenze', 'luce', 'elettricita', 'gas', 'acqua', 'internet', 'telefono', 'bollette', 'bolletta', 'energia', 'wifi'],
    ['stipendio', 'salario', 'retribuzione', 'paga', 'compenso', 'reddito', 'entrate', 'guadagno', 'busta paga'],
    ['istruzione', 'libri', 'corso', 'scuola', 'universita', 'formazione', 'tasse scolastiche'],
    ['viaggi', 'hotel', 'aereo', 'volo', 'vacanza', 'albergo', 'airbnb', 'prenotazione', 'vacanze', 'turismo'],
    ['assicurazioni', 'polizza', 'rc auto', 'assicurazione'],
    ['banca', 'commissioni', 'spese bancarie', 'canone', 'interessi', 'addebito', 'spese conto'],
    ['regali', 'regalo', 'donazione', 'beneficenza', 'charity'],
    ['tecnologia', 'elettronica', 'informatica', 'software', 'app', 'abbonamento', 'streaming'],
    ['investimenti', 'azioni', 'fondi', 'etf', 'obbligazioni', 'dividendi', 'borsa'],
    ['tasse', 'imposte', 'f24', 'irpef', 'imu', 'bollo', 'tributi'],
    ['animali', 'animale domestico', 'pet', 'veterinario', 'cibo animali'],
    ['cura persona', 'parrucchiere', 'estetista', 'cosmetici', 'profumo', 'bellezza'],
];

/**
 * Genera varianti singolari/plurali italiane di una parola normalizzata.
 * Regole italiane di base: -o↔-i, -a↔-e, -e↔-i, -ca↔-che, -go↔-ghi, invariabili (già gestite).
 */
function italianForms(word: string): string[] {
    const forms = new Set([word]);
    // plurale → singolare e viceversa (estremità della parola)
    const last = word.slice(-1);
    const last2 = word.slice(-2);
    const last3 = word.slice(-3);
    const stem2 = word.slice(0, -2);
    const stem3 = word.slice(0, -3);
    if (last === 'i') {
        forms.add(word.slice(0, -1) + 'o');  // ristoranti → ristorante (via 'e')
        forms.add(word.slice(0, -1) + 'e');
    }
    if (last === 'e') {
        forms.add(word.slice(0, -1) + 'i');
        forms.add(word.slice(0, -1) + 'a');
    }
    if (last === 'o') {
        forms.add(word.slice(0, -1) + 'i');
    }
    if (last === 'a') {
        forms.add(word.slice(0, -1) + 'e');
        forms.add(word.slice(0, -1) + 'i');
    }
    // -che/-ghe → singolare -ca/-ga
    if (last3 === 'che') { forms.add(stem3 + 'ca'); }
    if (last3 === 'ghe') { forms.add(stem3 + 'ga'); }
    if (last2 === 'ca')  { forms.add(stem2 + 'che'); }
    if (last2 === 'ga')  { forms.add(stem2 + 'ghe'); }
    // -go/-ghi
    if (last3 === 'ghi') { forms.add(stem3 + 'go'); }
    if (last2 === 'go')  { forms.add(stem2 + 'ghi'); }
    return [...forms];
}

/** Cerca la Category più adatta a un nome proveniente dal CSV: exact → singolare/plurale → partial → sinonimi */
function suggestCategoryMatch(csvName: string, cats: Category[]): Category | null {
    if (!csvName.trim() || cats.length === 0) return null;
    const norm = normalizeForMatch(csvName);
    const normForms = italianForms(norm);

    // 1. Exact match (incluse varianti singolare/plurale)
    const exact = cats.find(c => {
        const cn = normalizeForMatch(c.name);
        return normForms.includes(cn) || italianForms(cn).includes(norm);
    });
    if (exact) return exact;

    // 2. Partial match (uno contiene l'altro, minimo 3 caratteri — incluse varianti morfologiche)
    const partial = cats.find(c => {
        const cn = normalizeForMatch(c.name);
        const cnForms = italianForms(cn);
        return cnForms.some(cf => norm.length >= 3 && cf.length >= 3 && (norm.includes(cf) || cf.includes(norm)))
            || normForms.some(nf => nf.length >= 3 && cn.length >= 3 && (nf.includes(cn) || cn.includes(nf)));
    });
    if (partial) return partial;

    // 3. Synonym match: trova il gruppo che include il nome CSV (o una sua forma), poi cerca una categoria in quel gruppo
    for (const group of CATEGORY_SYNONYM_GROUPS) {
        const normGroup = group.map(w => normalizeForMatch(w));
        const csvInGroup = normGroup.some(w => {
            const wForms = italianForms(w);
            return normForms.some(nf => nf === w || wForms.includes(norm) || (w.length >= 4 && (norm.includes(w) || w.includes(norm))));
        });
        if (csvInGroup) {
            const match = cats.find(c => {
                const cn = normalizeForMatch(c.name);
                const cnForms = italianForms(cn);
                return normGroup.some(w => cnForms.includes(w) || italianForms(w).some(wf => cn === wf || (w.length >= 4 && (cn.includes(w) || w.includes(cn)))));
            });
            if (match) return match;
        }
    }

    return null;
}

const ACCOUNT_SYNONYM_GROUPS: string[][] = [
    ['cassa', 'contanti', 'cash', 'portafoglio'],
    ['carta', 'carta di credito', 'carta di debito', 'prepagata', 'visa', 'mastercard', 'bancomat'],
    ['risparmio', 'salvadanaio', 'deposito', 'libretto'],
    ['paypal', 'pay pal'],
    ['crypto', 'bitcoin', 'ethereum', 'criptovalute'],
    ['broker', 'trading', 'investimenti', 'titoli'],
];

/** Cerca l'Account più adatto a un nome proveniente dal CSV: exact → partial → sinonimi */
function suggestAccountMatch(csvName: string, accs: Account[]): Account | null {
    if (!csvName.trim() || accs.length === 0) return null;
    const norm = normalizeForMatch(csvName);

    // 1. Exact match
    const exact = accs.find(a => normalizeForMatch(a.name) === norm);
    if (exact) return exact;

    // 2. Partial match
    const partial = accs.find(a => {
        const an = normalizeForMatch(a.name);
        return an.length >= 3 && norm.length >= 3 && (norm.includes(an) || an.includes(norm));
    });
    if (partial) return partial;

    // 3. Synonym match
    for (const group of ACCOUNT_SYNONYM_GROUPS) {
        const normGroup = group.map(w => normalizeForMatch(w));
        const csvInGroup = normGroup.some(w => norm === w || (w.length >= 4 && (norm.includes(w) || w.includes(norm))));
        if (csvInGroup) {
            const match = accs.find(a => {
                const an = normalizeForMatch(a.name);
                return normGroup.some(w => an === w || (w.length >= 4 && (an.includes(w) || w.includes(an))));
            });
            if (match) return match;
        }
    }

    return null;
}

export default function Import({ accounts, userLayouts: initialUserLayouts, categories, currencies }: ImportProps) {
    const [currentStep, setCurrentStep] = useState(0);
    const [selectedBank, setSelectedBank] = useState(() => initialUserLayouts.length === 0 ? 'custom' : '');
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
        category: null,
        account: null,
        currency: null,
    });
    const [defaultCurrency, setDefaultCurrency] = useState('EUR');
    const [categoryMappings, setCategoryMappings] = useState<Record<string, CategoryMappingEntry>>({});
    const [accountMappings, setAccountMappings] = useState<Record<string, AccountMappingEntry>>({});
    const [showInvalidRows, setShowInvalidRows] = useState(false);
    const [previewData, setPreviewData] = useState<PreviewResponse | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const [selectedRows, setSelectedRows] = useState<Set<number>>(new Set());
    const fileInputRef = useRef<HTMLInputElement>(null);

    // ── Sorgente file ────────────────────────────────────────────────────────
    /** 'local' = upload dal dispositivo, 'gdrive' = Google Drive */
    const [fileSource, setFileSource] = useState<'local' | 'gdrive'>('local');
    const [driveFile, setDriveFile] = useState<DriveFile | null>(null);
    const [driveAccessToken, setDriveAccessToken] = useState<string | null>(null);
    const [driveError, setDriveError] = useState<string | null>(null);

    // ── Multi-sheet ──────────────────────────────────────────────────────────
    const [xlsxSheets, setXlsxSheets] = useState<SheetInfo[]>([]);
    const [selectedSheetIndex, setSelectedSheetIndex] = useState(0);
    const [sheetsLoading, setSheetsLoading] = useState(false);

    /** true se il file selezionato è un XLSX (locale o Drive) */
    const isXlsx = fileSource === 'gdrive'
        ? driveFile?.mimeType === 'application/vnd.google-apps.spreadsheet' ||
          driveFile?.name?.toLowerCase().endsWith('.xlsx') === true
        : csvFile?.name.toLowerCase().endsWith('.xlsx') ?? false;

    const { data, setData } = useForm({
        account_id: '',
    });
    const [importProcessing, setImportProcessing] = useState(false);
    const [importErrors, setImportErrors] = useState<Record<string, string>>({});

    // Gestione duplicati
    const [duplicateCheckLoading, setDuplicateCheckLoading] = useState(false);
    const [duplicateCheckError, setDuplicateCheckError] = useState<string | null>(null);
    const [duplicates, setDuplicates] = useState<DuplicateInfo[]>([]);
    const [duplicateResolutions, setDuplicateResolutions] = useState<Record<number, DuplicateResolution>>({});
    const [showDuplicateModal, setShowDuplicateModal] = useState(false);

    // Layout salvati (gestito come stato per poter aggiungere nuovi layout dinamicamente)
    const [userLayouts, setUserLayouts] = useState<UserLayout[]>(initialUserLayouts);
    const [selectedLayoutId, setSelectedLayoutId] = useState<number | null>(null);
    const [saveLayoutName, setSaveLayoutName] = useState('');
    const [saveLayoutIcon, setSaveLayoutIcon] = useState('🏦');
    const [savingLayout, setSavingLayout] = useState(false);
    const [saveLayoutSuccess, setSaveLayoutSuccess] = useState<string | null>(null);
    const [saveLayoutError, setSaveLayoutError] = useState<string | null>(null);
    /** Snapshot del mapping nel momento in cui è stato applicato un layout — per rilevare modifiche */
    const [appliedLayoutMapping, setAppliedLayoutMapping] = useState<UserLayout | null>(null);
    /** true = mostra il banner "vuoi aggiornare il layout?" prima di avanzare */
    const [showUpdateLayoutPrompt, setShowUpdateLayoutPrompt] = useState(false);
    const [pendingNextStep, setPendingNextStep] = useState(false);

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
                    icon: saveLayoutIcon,
                    delimiter: isXlsx ? ',' : delimiter,
                    date_format: dateFormat,
                    has_header: hasHeader,
                    encoding: isXlsx ? 'UTF-8' : encoding,
                    column_mapping: {
                        date: columnMapping.date ?? 0,
                        amount: columnMapping.amount ?? 1,
                        description: columnMapping.description ?? 2,
                        notes: columnMapping.notes ?? null,
                        category: columnMapping.category ?? null,
                        currency: columnMapping.currency ?? null,
                    },
                },
                { headers: { Accept: 'application/json' } },
            );
            setSaveLayoutSuccess(response.data.message);
            setSaveLayoutName('');
            setSaveLayoutIcon('🏦');
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
            category: layout.column_mapping.category ?? null,
            account: null,
            currency: layout.column_mapping.currency ?? null,
        });
        // Pre-compila il campo "Salva layout" con i dati del layout applicato
        setSaveLayoutName(layout.name);
        setSaveLayoutIcon(layout.icon ?? '🏦');
        setSaveLayoutSuccess(null);
        setSaveLayoutError(null);
        // Registra snapshot per rilevare eventuali modifiche
        setAppliedLayoutMapping(layout);
        setShowUpdateLayoutPrompt(false);
    };

    const handleSelectCustom = () => {
        setSelectedBank('custom');
        setSelectedLayoutId(null);
        setSaveLayoutName('');
        setSaveLayoutIcon('🏦');
        setAppliedLayoutMapping(null);
        setShowUpdateLayoutPrompt(false);
    };

    /** Aggiorna il layout esistente via PATCH */
    const updateCurrentLayout = async (): Promise<boolean> => {
        if (!selectedLayoutId) return false;
        setSavingLayout(true);
        setSaveLayoutError(null);
        setSaveLayoutSuccess(null);
        try {
            const response = await axios.patch<{ success: boolean; message: string; layout: UserLayout }>(
                route('bank-import-layouts.update', selectedLayoutId),
                {
                    name: saveLayoutName.trim() || appliedLayoutMapping?.name,
                    bank_name: selectedBank || 'custom',
                    icon: saveLayoutIcon,
                    delimiter: isXlsx ? ',' : delimiter,
                    date_format: dateFormat,
                    has_header: hasHeader,
                    encoding: isXlsx ? 'UTF-8' : encoding,
                    column_mapping: {
                        date: columnMapping.date ?? 0,
                        amount: columnMapping.amount ?? 1,
                        description: columnMapping.description ?? 2,
                        notes: columnMapping.notes ?? null,
                        category: columnMapping.category ?? null,
                        currency: columnMapping.currency ?? null,
                    },
                },
                { headers: { Accept: 'application/json' } },
            );
            setSaveLayoutSuccess(response.data.message ?? 'Layout aggiornato.');
            setUserLayouts((prev) => prev.map(l => l.id === selectedLayoutId ? response.data.layout : l));
            setAppliedLayoutMapping(response.data.layout);
            return true;
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data?.message) {
                setSaveLayoutError(err.response.data.message);
            } else {
                setSaveLayoutError('Errore durante l’aggiornamento del layout.');
            }
            return false;
        } finally {
            setSavingLayout(false);
        }
    };

    const steps = WIZARD_STEPS.map((label, index) => ({
        label,
        completed: index < currentStep,
        active: index === currentStep,
    }));

    /** Rileva i fogli di un file XLSX locale */
    const detectSheetsFromFile = async (file: File) => {
        if (!file.name.toLowerCase().endsWith('.xlsx')) {
            setXlsxSheets([]);
            return;
        }
        setSheetsLoading(true);
        try {
            const formData = new FormData();
            formData.append('csv_file', file);
            const response = await axios.post<{ sheets: SheetInfo[] }>(
                route('transactions.import.sheets'),
                formData,
                { headers: { 'Content-Type': 'multipart/form-data' } },
            );
            setXlsxSheets(response.data.sheets ?? []);
            setSelectedSheetIndex(0);
        } catch {
            setXlsxSheets([]);
        } finally {
            setSheetsLoading(false);
        }
    };

    /** Rileva i fogli di un file XLSX da Google Drive */
    const detectSheetsFromDrive = async (file: DriveFile, token: string) => {
        const mightBeXlsx = file.mimeType === 'application/vnd.google-apps.spreadsheet' ||
            file.mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
            file.name.toLowerCase().endsWith('.xlsx');
        if (!mightBeXlsx) {
            setXlsxSheets([]);
            return;
        }
        setSheetsLoading(true);
        try {
            const response = await axios.post<{ sheets: SheetInfo[] }>(
                route('transactions.import.sheets'),
                {
                    google_drive_file_id:      file.id,
                    google_drive_access_token: token,
                    google_drive_mime_type:    file.mimeType,
                },
            );
            setXlsxSheets(response.data.sheets ?? []);
            setSelectedSheetIndex(0);
        } catch {
            setXlsxSheets([]);
        } finally {
            setSheetsLoading(false);
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setCsvFile(file);
        setPreviewData(null);
        setAccountMappings({});
        setXlsxSheets([]);
        setSelectedSheetIndex(0);
        if (file) {
            void detectSheetsFromFile(file);
        }
    };

    /** Chiamato dal GoogleDrivePicker quando l'utente seleziona un file */
    const handleDriveFileSelected = (file: DriveFile, token: string) => {
        setFileSource('gdrive');
        setDriveFile(file);
        setDriveAccessToken(token);
        setDriveError(null);
        setPreviewData(null);
        setAccountMappings({});
        setXlsxSheets([]);
        setSelectedSheetIndex(0);
        void detectSheetsFromDrive(file, token);
    };

    const callPreview = async (): Promise<boolean> => {
        const hasLocalFile = fileSource === 'local' && csvFile !== null;
        const hasDriveFile = fileSource === 'gdrive' && driveFile !== null && driveAccessToken !== null;
        if (!hasLocalFile && !hasDriveFile) return false;

        setPreviewLoading(true);
        setPreviewError(null);

        const formData = new FormData();

        if (fileSource === 'gdrive' && driveFile && driveAccessToken) {
            formData.append('google_drive_file_id',      driveFile.id);
            formData.append('google_drive_access_token', driveAccessToken);
            formData.append('google_drive_mime_type',    driveFile.mimeType);
        } else if (csvFile) {
            formData.append('csv_file', csvFile);
        }

        formData.append('bank_name', selectedBank || 'custom');
        formData.append('date_format', dateFormat);
        formData.append('has_header', hasHeader ? '1' : '0');
        formData.append('sheet_index', String(selectedSheetIndex));
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
        if (columnMapping.category !== null) {
            formData.append('column_mapping[category]', String(columnMapping.category));
        }
        if (columnMapping.account != null) {
            formData.append('column_mapping[account]', String(columnMapping.account));
        }
        if (columnMapping.currency != null) {
            formData.append('column_mapping[currency]', String(columnMapping.currency));
        }

        try {
            const response = await axios.post<PreviewResponse>(route('transactions.import.preview'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setPreviewData(response.data);
            const allIndices = new Set(response.data.valid.map((_, i) => i));
            setSelectedRows(allIndices);
            // Inizializza i mapping delle categorie per quelle nuove trovate nel file
            if (response.data.unique_categories.length > 0) {
                setCategoryMappings((prev) => {
                    const updated = { ...prev };
                    response.data.unique_categories.forEach((name) => {
                        if (!updated[name]) {
                            const match = suggestCategoryMatch(name, categories);
                            updated[name] = match
                                ? { action: 'existing', category_id: match.id, type: match.type, suggested: true }
                                : { action: 'none', category_id: null, type: null };
                        }
                    });
                    return updated;
                });
            }
            // Inizializza i mapping dei conti per quelli trovati nel file
            const uniqueAccounts = response.data.unique_accounts ?? [];
            setAccountMappings((prev) => {
                const updated: Record<string, AccountMappingEntry> = {};
                uniqueAccounts.forEach((name) => {
                    if (prev[name]) {
                        updated[name] = prev[name];
                    } else {
                        const match = suggestAccountMatch(name, accounts);
                        updated[name] = match
                            ? { action: 'existing', account_id: match.id, currency_code: match.currency_code, type: 'bank', suggested: true }
                            : { action: 'existing', account_id: null, currency_code: 'EUR', type: 'bank' };
                    }
                });
                return updated;
            });
            return true;
        } catch (err: unknown) {
            if (axios.isAxiosError(err) && err.response?.data) {
                const payload = err.response.data as { message?: string; error?: string };
                setPreviewError(payload.message ?? payload.error ?? 'Errore durante la lettura del file. Verifica la configurazione.');
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
        // Uscendo dallo step 2 verso la conferma
        if (currentStep === 2) {
            // Rileva se il mapping è stato modificato rispetto al layout applicato
            if (appliedLayoutMapping && selectedLayoutId && !showUpdateLayoutPrompt) {
                const orig = appliedLayoutMapping.column_mapping;
                const changed =
                    columnMapping.date !== orig.date ||
                    columnMapping.amount !== orig.amount ||
                    columnMapping.description !== orig.description ||
                    (columnMapping.notes ?? null) !== (orig.notes ?? null) ||
                    (columnMapping.category ?? null) !== (orig.category ?? null) ||
                    delimiter !== appliedLayoutMapping.delimiter ||
                    dateFormat !== appliedLayoutMapping.date_format ||
                    hasHeader !== appliedLayoutMapping.has_header;
                if (changed) {
                    setShowUpdateLayoutPrompt(true);
                    setPendingNextStep(true);
                    return; // blocca l'avanzamento — user deve rispondere al prompt
                }
            }
            const ok = await callPreview();
            if (!ok) return;
            setShowUpdateLayoutPrompt(false);
            setPendingNextStep(false);
        }
        setCurrentStep((s) => Math.min(s + 1, WIZARD_STEPS.length - 1));
    };

    /** Risposta al prompt: aggiorna il layout e poi avanza */
    const handleUpdateAndProceed = async () => {
        await updateCurrentLayout();
        setShowUpdateLayoutPrompt(false);
        setPendingNextStep(false);
        const ok = await callPreview();
        if (ok) setCurrentStep((s) => Math.min(s + 1, WIZARD_STEPS.length - 1));
    };

    /** Risposta al prompt: ignora e avanza senza salvare */
    const handleSkipUpdateAndProceed = async () => {
        setShowUpdateLayoutPrompt(false);
        setPendingNextStep(false);
        const ok = await callPreview();
        if (ok) setCurrentStep((s) => Math.min(s + 1, WIZARD_STEPS.length - 1));
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
                ...(row.category_name ? { category_name: row.category_name } : {}),
                ...(columnMapping.account != null && row.account_name ? { account_name: row.account_name } : {}),
                ...(row.currency_code ? { currency_code: row.currency_code } : {}),
            }));
    };

    const doImport = (rows: ReturnType<typeof getRowsToImport>) => {
        const rowsWithResolutions = rows.map((row, idx) => {
            const res = duplicateResolutions[idx];
            if (!res) return row;
            return {
                ...row,
                duplicate_action: res.action,
                ...(res.duplicate_transaction_id !== null ? { duplicate_transaction_id: res.duplicate_transaction_id } : {}),
            };
        });
        // Costruisce l'array category_mappings da inviare al server
        const categoryMappingsArray = Object.entries(categoryMappings)
            .filter(([, entry]) => entry.action !== 'none')
            .map(([name, entry]) => ({
                name,
                action: entry.action,
                ...(entry.action === 'existing' && entry.category_id ? { category_id: entry.category_id } : {}),
                ...(entry.action === 'create' && entry.type ? { type: entry.type } : {}),
            }));
        // Costruisce l'array account_mappings da inviare al server
        const accountMappingsArray = columnMapping.account != null
            ? Object.entries(accountMappings).map(([name, entry]) => ({
                name,
                action: entry.action,
                ...(entry.action === 'existing' && entry.account_id ? { account_id: entry.account_id } : {}),
                ...(entry.action === 'create' ? { currency_code: entry.currency_code, type: entry.type } : {}),
            }))
            : [];
        setImportProcessing(true);
        setImportErrors({});
        router.post(
            route('transactions.import.store'),
            {
                ...(columnMapping.account === null ? { account_id: data.account_id } : {}),
                default_currency: defaultCurrency,
                rows: rowsWithResolutions,
                category_mappings: categoryMappingsArray,
                ...(accountMappingsArray.length > 0 ? { account_mappings: accountMappingsArray } : {}),
            },
            {
                onFinish: () => setImportProcessing(false),
                onError:  (errs) => setImportErrors(errs as Record<string, string>),
            },
        );
    };

    const handleImport = async () => {
        // Serve account_id globale SOLO se la colonna conto non è mappata nel file
        if (!previewData || (columnMapping.account == null && !data.account_id)) return;
        const rows = getRowsToImport();
        setDuplicateCheckLoading(true);
        setImportErrors({});
        setDuplicateCheckError(null);
        try {
            const resp = await axios.post<{ duplicates: DuplicateInfo[] }>(
                route('transactions.import.check-duplicates'),
                {
                    ...(columnMapping.account === null ? { account_id: data.account_id } : {}),
                    rows,
                },
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
        } catch (err: unknown) {
            // Non procedere silenziosamente: mostra il banner e lascia scegliere all'utente
            const msg = axios.isAxiosError(err) && err.response?.data?.message
                ? err.response.data.message
                : 'Impossibile verificare i duplicati. Vuoi importare comunque?';
            setDuplicateCheckError(msg);
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
        if (currentStep === 1) {
            const hasFile = fileSource === 'local' ? csvFile !== null : (driveFile !== null);
            return hasFile && !sheetsLoading;
        }
        if (currentStep === 2) return columnMapping.date !== null && columnMapping.amount !== null && columnMapping.description !== null;
        if (currentStep === 3) {
            if (!selectedRows.size) return false;
            if (columnMapping.account != null) {
                const uniqueAccounts = previewData?.unique_accounts ?? [];
                return uniqueAccounts.length > 0
                    ? uniqueAccounts.every((name) => {
                        const entry = accountMappings[name];
                        if (!entry) return false;
                        if (entry.action === 'existing') return entry.account_id != null;
                        if (entry.action === 'create') return entry.currency_code !== '';
                        return false;
                    })
                    : false;
            }
            return data.account_id !== '';
        }
        return false;
    };

    const formatAmount = (amount: number) => {
        return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(amount);
    };

    const resolveRowType = (row: ImportRow): 'income' | 'expense' | null => {
        if (!row.category_name) return null;

        const mapped = categoryMappings[row.category_name];
        if (mapped) {
            if (mapped.action === 'create') {
                return mapped.type ?? null;
            }
            if (mapped.action === 'existing' && mapped.category_id != null) {
                return categories.find((cat) => cat.id === mapped.category_id)?.type ?? null;
            }
        }

        const normalizedRowCategory = normalizeForMatch(row.category_name);
        return categories.find((cat) => normalizeForMatch(cat.name) === normalizedRowCategory)?.type ?? null;
    };

    const getRowAmountStyle = (row: ImportRow) => {
        const rowType = resolveRowType(row);

        if (rowType === 'income') return { className: 'text-green-700', label: 'Entrata' };
        if (rowType === 'expense') return { className: 'text-red-700', label: 'Uscita' };
        if (row.amount < 0) return { className: 'text-red-700', label: 'Uscita' };
        if (row.amount > 0) return { className: 'text-green-700', label: 'Entrata' };

        return { className: 'text-gray-600', label: 'Neutro' };
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

            <PageContent maxWidth="4xl">
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
                                            <span className="text-2xl" aria-hidden="true">{layout.icon ?? '🏦'}</span>
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

                            {/* Selezione sorgente */}
                            <div className="flex gap-2" role="radiogroup" aria-label="Sorgente del file">
                                <button
                                    type="button"
                                    role="radio"
                                    aria-checked={fileSource === 'local'}
                                    onClick={() => { setFileSource('local'); setDriveFile(null); setDriveAccessToken(null); setXlsxSheets([]); }}
                                    className={clsx(
                                        'inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 text-sm font-medium transition-all focus:outline-none focus:ring-2 focus:ring-blue-500',
                                        fileSource === 'local'
                                            ? 'border-blue-500 bg-blue-50 text-blue-700 shadow-sm'
                                            : 'border-gray-200 bg-white text-gray-700 hover:border-blue-400 hover:bg-blue-50',
                                    )}
                                >
                                    <span aria-hidden="true">📂</span> Dal dispositivo
                                </button>
                                <GoogleDrivePicker
                                    onFileSelected={handleDriveFileSelected}
                                    onError={(msg) => setDriveError(msg)}
                                    disabled={sheetsLoading}
                                    className={clsx(
                                        'border-2',
                                        fileSource === 'gdrive' && driveFile
                                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                                            : '',
                                    )}
                                />
                            </div>
                            {driveError && (
                                <p className="text-sm text-red-600">{driveError}</p>
                            )}

                            {/* File locale */}
                            {fileSource === 'local' && (
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
                            )}

                            {/* File Google Drive */}
                            {fileSource === 'gdrive' && driveFile && (
                                <div className="flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    {driveFile.iconUrl && (
                                        <img src={driveFile.iconUrl} alt="" className="w-6 h-6 flex-shrink-0" aria-hidden="true" />
                                    )}
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-blue-800 truncate">{driveFile.name}</p>
                                        <p className="text-xs text-blue-600">Google Drive</p>
                                    </div>
                                </div>
                            )}

                            {/* Rilevamento fogli in corso */}
                            {sheetsLoading && (
                                <p className="text-sm text-gray-500">Rilevamento fogli in corso…</p>
                            )}

                            {/* Selezione foglio (multi-sheet) */}
                            {!sheetsLoading && xlsxSheets.length > 1 && (
                                <SheetSelector
                                    sheets={xlsxSheets}
                                    selectedIndex={selectedSheetIndex}
                                    onChange={setSelectedSheetIndex}
                                />
                            )}

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
                                onChange={(m) => setColumnMapping({ ...m, category: m.category ?? null, account: m.account ?? null, currency: m.currency ?? null })}
                            />
                            <PrimaryButton
                                type="button"
                                onClick={callPreview}
                                disabled={previewLoading || (fileSource === 'local' ? !csvFile : !driveFile)}
                                className="mt-2"
                            >
                                {previewLoading ? 'Lettura in corso…' : 'Aggiorna anteprima'}
                            </PrimaryButton>
                            {previewError && (
                                <div className="mt-2 text-sm text-red-600 bg-red-50 border border-red-200 rounded-md p-3">
                                    {previewError}
                                </div>
                            )}

                            {/* Anteprima righe dopo "Aggiorna anteprima" */}
                            {previewData && !previewError && (
                                <div className="mt-3 rounded-lg border border-green-200 bg-green-50 p-3 space-y-2">
                                    <p className="text-sm font-medium text-green-800">
                                        ✓ {previewData.valid_count} transazioni trovate
                                        {previewData.invalid_count > 0 && (
                                            <span className="ml-2 text-orange-600 font-normal">
                                                · {previewData.invalid_count} righe ignorate
                                            </span>
                                        )}
                                    </p>
                                    {previewData.valid.length > 0 && (
                                        <div className="overflow-x-auto">
                                            <table className="min-w-full text-xs text-gray-700">
                                                <thead>
                                                    <tr className="text-left text-gray-500 border-b border-green-200">
                                                        <th className="pr-3 pb-1 font-medium">Data</th>
                                                        <th className="pr-3 pb-1 font-medium">Importo</th>
                                                        <th className="pb-1 font-medium">Descrizione</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {previewData.valid.slice(0, 5).map((row) => {
                                                        const amountStyle = getRowAmountStyle(row);

                                                        return (
                                                            <tr key={row.line_number} className="border-b border-green-100 last:border-0">
                                                                <td className="pr-3 py-1 whitespace-nowrap">{row.date}</td>
                                                                <td className={clsx('pr-3 py-1 whitespace-nowrap font-medium', amountStyle.className)}>
                                                                    {formatAmount(row.amount)}
                                                                    <span className="ml-1 text-[11px] font-medium text-gray-500">{amountStyle.label}</span>
                                                                </td>
                                                                <td className="py-1 truncate max-w-[200px]">{row.description}</td>
                                                            </tr>
                                                        );
                                                    })}
                                                </tbody>
                                            </table>
                                            {previewData.valid.length > 5 && (
                                                <p className="mt-1 text-xs text-gray-500">… e altre {previewData.valid.length - 5} righe</p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Banner: aggiorna layout esistente? */}
                            {showUpdateLayoutPrompt && selectedLayoutId && (
                                <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
                                    <p className="text-sm font-medium text-amber-800">
                                        Hai modificato la mappatura rispetto al layout <strong>«{appliedLayoutMapping?.name}»</strong>. Vuoi aggiornarlo?
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            onClick={handleUpdateAndProceed}
                                            disabled={savingLayout}
                                            className="inline-flex items-center gap-1.5 rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-amber-500"
                                        >
                                            {savingLayout ? 'Salvataggio…' : 'Aggiorna e continua'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={handleSkipUpdateAndProceed}
                                            disabled={savingLayout}
                                            className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            Continua senza salvare
                                        </button>
                                    </div>
                                    {saveLayoutError && (
                                        <p className="mt-2 text-xs text-red-600">{saveLayoutError}</p>
                                    )}
                                </div>
                            )}

                            {/* Salva layout */}
                            <div className="mt-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4">
                                <h3 className="text-sm font-medium text-gray-700 mb-1">
                                    {selectedLayoutId ? 'Aggiorna layout' : 'Salva questa configurazione come layout'}
                                </h3>
                                <p className="text-xs text-gray-500 mb-3">
                                    {selectedLayoutId
                                        ? 'Sovrascrive il layout esistente con la configurazione attuale delle colonne.'
                                        : 'Potrai riutilizzarlo nelle prossime importazioni senza dover riconfigurare le colonne.'}
                                </p>
                                {/* Selezione icona */}
                                <div className="mb-3">
                                    <p className="text-xs text-gray-500 mb-1.5">Icona</p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {LAYOUT_ICONS.map((emoji) => (
                                            <button
                                                key={emoji}
                                                type="button"
                                                onClick={() => setSaveLayoutIcon(emoji)}
                                                className={clsx(
                                                    'text-xl w-9 h-9 flex items-center justify-center rounded-lg border-2 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500',
                                                    saveLayoutIcon === emoji
                                                        ? 'border-blue-500 bg-blue-50'
                                                        : 'border-gray-200 bg-white hover:border-blue-300',
                                                )}
                                                aria-label={`Icona ${emoji}`}
                                                aria-pressed={saveLayoutIcon === emoji}
                                            >
                                                {emoji}
                                            </button>
                                        ))}
                                    </div>
                                </div>
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
                                        onClick={selectedLayoutId ? updateCurrentLayout : saveCurrentLayout}
                                        disabled={savingLayout || !saveLayoutName.trim()}
                                        className={clsx(
                                            'inline-flex items-center px-4 py-2 rounded-md text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500',
                                            savingLayout || !saveLayoutName.trim()
                                                ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                : 'bg-blue-600 text-white hover:bg-blue-700',
                                        )}
                                    >
                                        {savingLayout ? 'Salvataggio…' : (selectedLayoutId ? 'Aggiorna layout' : 'Salva layout')}
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

                            {/* Account selector (solo se NON mappata la colonna conto) */}
                            {columnMapping.account === null && (
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
                            )}

                            {/* Valuta predefinita / riepilogo valute */}
                            <div className="rounded-lg border border-amber-100 bg-amber-50/50 p-4">
                                <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                    <div className="flex-1">
                                        <h3 className="text-sm font-semibold text-gray-800">
                                            💱 Valuta {columnMapping.currency != null ? 'predefinita' : 'delle transazioni'}
                                        </h3>
                                        <p className="mt-0.5 text-xs text-gray-500">
                                            {columnMapping.currency != null
                                                ? 'Usata per le righe senza valuta specificata nel file. Le altre righe useranno la valuta indicata nella colonna mappata.'
                                                : 'Tutte le transazioni importate saranno registrate con questa valuta.'}
                                        </p>
                                    </div>
                                    <select
                                        value={defaultCurrency}
                                        onChange={(e) => setDefaultCurrency(e.target.value)}
                                        className="w-full sm:w-auto rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                        aria-label="Valuta predefinita"
                                    >
                                        {currencies.map((c) => (
                                            <option key={c.code} value={c.code}>{c.symbol} {c.code} – {c.name}</option>
                                        ))}
                                    </select>
                                </div>
                                {columnMapping.currency != null && previewData && (previewData.unique_currencies ?? []).length > 0 && (
                                    <div className="mt-3 flex flex-wrap gap-1.5">
                                        <span className="text-xs text-gray-500 mr-1">Valute nel file:</span>
                                        {(previewData.unique_currencies ?? []).map((code) => (
                                            <span key={code} className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                                {code}
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Mappatura conti da colonna file */}
                            {columnMapping.account != null && previewData && (previewData.unique_accounts ?? []).length > 0 && (
                                <div className="rounded-lg border border-purple-100 bg-purple-50 p-4">
                                    <h3 className="mb-1 text-sm font-semibold text-gray-800">
                                        🏦 Conti trovati nel file ({(previewData.unique_accounts ?? []).length})
                                    </h3>
                                    <p className="mb-3 text-xs text-gray-500">
                                        Associa ogni nome conto dal file a uno dei tuoi conti oppure creane uno nuovo.
                                    </p>
                                    <div className="space-y-2">
                                        {(previewData.unique_accounts ?? []).map((accName) => {
                                            const entry = accountMappings[accName] ?? { action: 'existing' as const, account_id: null, currency_code: 'EUR', type: 'bank' as const };
                                            const isSuggested = (entry.suggested === true) && entry.action === 'existing' && entry.account_id != null;
                                            return (
                                                <div key={accName} className={clsx(
                                                    'flex flex-wrap items-center gap-2 rounded-lg border px-3 py-2',
                                                    isSuggested ? 'border-emerald-200 bg-emerald-50/40' : 'border-gray-200 bg-white',
                                                )}>
                                                    <span className="min-w-[80px] flex-shrink-0 text-sm font-medium text-gray-800">{accName}</span>
                                                    {isSuggested && (
                                                        <span className="rounded-full border border-emerald-200 bg-emerald-100 px-1.5 py-0.5 text-xs text-emerald-700" title="Abbinato automaticamente in base al nome">
                                                            ✨ suggerito
                                                        </span>
                                                    )}
                                                    <select
                                                        value={entry.action}
                                                        onChange={(e) => {
                                                            const action = e.target.value as 'existing' | 'create';
                                                            setAccountMappings((prev) => ({
                                                                ...prev,
                                                                [accName]: { ...entry, action, account_id: null, suggested: false },
                                                            }));
                                                        }}
                                                        className="rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                                    >
                                                        <option value="existing">Usa conto esistente</option>
                                                        <option value="create">Crea nuovo conto</option>
                                                    </select>
                                                    {entry.action === 'existing' && (
                                                        <select
                                                            value={entry.account_id ?? ''}
                                                            onChange={(e) => setAccountMappings((prev) => ({
                                                                ...prev,
                                                                [accName]: { ...entry, account_id: Number(e.target.value) || null },
                                                            }))}
                                                            className="rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                                        >
                                                            <option value="">Seleziona conto…</option>
                                                            {accounts.map((acc) => (
                                                                <option key={acc.id} value={acc.id}>
                                                                    {acc.name} ({acc.currency_code})
                                                                </option>
                                                            ))}
                                                        </select>
                                                    )}
                                                    {entry.action === 'create' && (
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <select
                                                                value={entry.type}
                                                                onChange={(e) => setAccountMappings((prev) => ({
                                                                    ...prev,
                                                                    [accName]: { ...entry, type: e.target.value as AccountMappingEntry['type'] },
                                                                }))}
                                                                className="rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                                            >
                                                                <option value="bank">Conto bancario</option>
                                                                <option value="cash">Contanti</option>
                                                                <option value="card">Carta</option>
                                                                <option value="broker">Broker</option>
                                                                <option value="crypto">Crypto</option>
                                                                <option value="other">Altro</option>
                                                            </select>
                                                            <select
                                                                value={entry.currency_code}
                                                                onChange={(e) => setAccountMappings((prev) => ({
                                                                    ...prev,
                                                                    [accName]: { ...entry, currency_code: e.target.value },
                                                                }))}
                                                                className="rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                                            >
                                                                {currencies.map((c) => (
                                                                    <option key={c.code} value={c.code}>{c.code} – {c.name}</option>
                                                                ))}
                                                            </select>
                                                        </div>
                                                    )}
                                                    {entry.action === 'existing' && !entry.account_id && (
                                                        <span className="text-xs text-amber-600">⚠️ non assegnato</span>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* Stats */}
                            {previewData && (function() {
                                const warnCount = previewData.valid.filter(r => r.warnings?.length > 0).length;
                                return (
                                <div className="flex flex-wrap gap-3 text-sm">
                                    <span className="inline-flex items-center px-3 py-1 rounded-full bg-green-100 text-green-800">
                                        ✓ {previewData.valid_count} transazioni valide
                                    </span>
                                    {warnCount > 0 && (
                                        <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-amber-100 text-amber-800">
                                            ⚠️ {warnCount} con avvisi
                                        </span>
                                    )}
                                    {previewData.invalid_count > 0 && (
                                        <button
                                            type="button"
                                            onClick={() => setShowInvalidRows((v) => !v)}
                                            className="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-red-100 text-red-800 hover:bg-red-200 transition-colors"
                                        >
                                            ✗ {previewData.invalid_count} righe non valide
                                            <span className="text-xs">{showInvalidRows ? '▲' : '▼'}</span>
                                        </button>
                                    )}
                                    <span className="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-800">
                                        Selezionate: {selectedRows.size}
                                    </span>
                                </div>);
                            })()}
                            {previewData && (
                                <div className="space-y-1 text-xs text-gray-500">
                                    <p>Le righe completamente vuote vengono ignorate automaticamente.</p>
                                    <p>Le righe con descrizione vuota sono comunque importabili.</p>
                                </div>
                            )}

                            {/* Righe non valide */}
                            {previewData && showInvalidRows && previewData.invalid.length > 0 && (
                                <div className="rounded-lg border border-red-200 bg-red-50 p-3">
                                    <p className="text-xs font-semibold text-red-700 mb-2">Righe non importabili</p>
                                    <div className="space-y-1.5 max-h-48 overflow-y-auto">
                                        {previewData.invalid.map((row) => (
                                            <div key={row.line_number} className="rounded border border-red-100 bg-white px-2 py-1.5 text-xs">
                                                <span className="font-medium text-red-700 mr-2">Riga {row.line_number}</span>
                                                <span className="text-gray-500 mr-2 font-mono truncate">{row.raw}</span>
                                                <span className="text-red-600">{row.errors.join(' · ')}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Risoluzione categorie */}
                            {previewData && previewData.unique_categories.length > 0 && (
                                <div className="rounded-lg border border-blue-100 bg-blue-50 p-4">
                                    <h3 className="mb-1 text-sm font-semibold text-gray-800">
                                        🏷️ Categorie trovate nel file ({previewData.unique_categories.length})
                                    </h3>
                                    <p className="mb-3 text-xs text-gray-500">
                                        Indica come assegnare ogni categoria presente nel file importato.
                                        Le voci con il badge <span className="text-emerald-600">✨</span> sono state abbinate automaticamente — verifica e correggi se necessario.
                                    </p>
                                    <div className="space-y-2">
                                        {previewData.unique_categories.map((catName) => {
                                            const entry = categoryMappings[catName] ?? { action: 'none' as const, category_id: null, type: null };
                                            const isSuggested = (entry.suggested === true) && entry.action === 'existing' && entry.category_id != null;
                                            return (
                                                <div key={catName} className={clsx(
                                                    'flex flex-wrap items-center gap-2 rounded-lg border px-3 py-2',
                                                    isSuggested ? 'border-emerald-200 bg-emerald-50/40' : 'border-gray-200 bg-white',
                                                )}>
                                                    <span className="min-w-[80px] flex-shrink-0 text-sm font-medium text-gray-800">{catName}</span>
                                                    {isSuggested && (
                                                        <span className="rounded-full border border-emerald-200 bg-emerald-100 px-1.5 py-0.5 text-xs text-emerald-700" title="Abbinato automaticamente in base al nome">
                                                            ✨ suggerito
                                                        </span>
                                                    )}
                                                    <select
                                                        value={entry.action}
                                                        onChange={(e) => {
                                                            const action = e.target.value as 'none' | 'existing' | 'create';
                                                            setCategoryMappings((prev) => ({
                                                                ...prev,
                                                                [catName]: { action, category_id: null, type: null, suggested: false },
                                                            }));
                                                        }}
                                                        className="rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                                    >
                                                        <option value="none">— Non assegnare —</option>
                                                        <option value="existing">Mappa su categoria esistente</option>
                                                        <option value="create">Crea nuova categoria</option>
                                                    </select>
                                                    {entry.action === 'existing' && (
                                                        <select
                                                            value={entry.category_id ?? ''}
                                                            onChange={(e) => setCategoryMappings((prev) => ({
                                                                ...prev,
                                                                [catName]: { ...prev[catName], category_id: Number(e.target.value) || null },
                                                            }))}
                                                            className="rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                                        >
                                                            <option value="">Seleziona categoria…</option>
                                                            {categories.map((cat) => (
                                                                <option key={cat.id} value={cat.id}>
                                                                    {cat.icon ? cat.icon + ' ' : ''}{cat.name} ({cat.type === 'income' ? 'Entrata' : 'Uscita'})
                                                                </option>
                                                            ))}
                                                        </select>
                                                    )}
                                                    {entry.action === 'create' && (
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-xs text-gray-500">Tipo:</span>
                                                            <select
                                                                value={entry.type ?? ''}
                                                                onChange={(e) => setCategoryMappings((prev) => ({
                                                                    ...prev,
                                                                    [catName]: { ...prev[catName], type: (e.target.value as 'income' | 'expense') || null },
                                                                }))}
                                                                className="rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                                            >
                                                                <option value="">Seleziona tipo…</option>
                                                                <option value="expense">Uscita</option>
                                                                <option value="income">Entrata</option>
                                                            </select>
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}

                            {/* Preview table */}
                            {previewData && previewData.valid.length > 0 && (
                                <div className="overflow-x-auto -mx-6 max-h-[800px]">
                                    <table className="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-3 py-1.5 text-left">
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedRows.size === previewData.valid.length}
                                                        onChange={toggleAllRows}
                                                        className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                        aria-label="Seleziona tutte"
                                                    />
                                                </th>
                                                <th className="px-3 py-1.5 text-left text-xs font-medium text-gray-600">Data</th>
                                                <th className="px-3 py-1.5 text-right text-xs font-medium text-gray-600">Importo</th>
                                                <th className="px-3 py-1.5 text-left text-xs font-medium text-gray-600">Descrizione</th>
                                                <th className="px-3 py-1.5 text-left text-xs font-medium text-gray-600 hidden sm:table-cell">Note</th>
                                                {previewData.unique_categories.length > 0 && (
                                                    <th className="px-3 py-1.5 text-left text-xs font-medium text-gray-600 hidden md:table-cell">Categoria</th>
                                                )}
                                                {columnMapping.account != null && (
                                                    <th className="px-3 py-1.5 text-left text-xs font-medium text-gray-600 hidden md:table-cell">Conto</th>
                                                )}
                                                {columnMapping.currency != null && (
                                                    <th className="px-3 py-1.5 text-left text-xs font-medium text-gray-600 hidden sm:table-cell">Valuta</th>
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-100 text-sm">
                                            {previewData.valid.map((row, index) => {
                                                const amountStyle = getRowAmountStyle(row);

                                                return (
                                                <tr
                                                    key={index}
                                                    className={clsx(
                                                        'transition-colors',
                                                        !selectedRows.has(index) ? 'opacity-40 hover:bg-gray-50' : (row.warnings?.length > 0 ? 'bg-amber-50 hover:bg-amber-100' : 'hover:bg-gray-50'),
                                                    )}
                                                >
                                                    <td className="px-3 py-1">
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedRows.has(index)}
                                                            onChange={() => toggleRow(index)}
                                                            className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                            aria-label={`Seleziona riga ${index + 1}`}
                                                        />
                                                    </td>
                                                    <td className="px-3 py-1 text-gray-700 whitespace-nowrap">
                                                        {new Date(row.date).toLocaleDateString('it-IT')}
                                                    </td>
                                                    <td className={clsx(
                                                        'px-3 py-1 text-right font-medium whitespace-nowrap',
                                                        amountStyle.className,
                                                    )}>
                                                        {formatAmount(row.amount)}
                                                        <span className="ml-1 text-[11px] font-medium text-gray-500">
                                                            {amountStyle.label}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-1 text-gray-700 max-w-xs truncate">
                                                        {row.description || (
                                                            <span className="inline-flex items-center gap-1 text-amber-600 text-xs">
                                                                <svg className="h-3 w-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z" /></svg>
                                                                vuota
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-1 text-gray-500 max-w-xs truncate hidden sm:table-cell">
                                                        {row.notes ?? '—'}
                                                    </td>
                                                    {previewData.unique_categories.length > 0 && (
                                                        <td className="px-3 py-1 hidden md:table-cell">
                                                            {row.category_name ? (
                                                                <span className="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                                    {row.category_name}
                                                                </span>
                                                            ) : '—'}
                                                        </td>
                                                    )}
                                                    {columnMapping.account != null && (
                                                        <td className="px-3 py-1 hidden md:table-cell">
                                                            {row.account_name ? (
                                                                <span className="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">
                                                                    {row.account_name}
                                                                </span>
                                                            ) : '—'}
                                                        </td>
                                                    )}
                                                    {columnMapping.currency != null && (
                                                        <td className="px-3 py-1 hidden sm:table-cell">
                                                            {row.currency_code ? (
                                                                <span className={clsx(
                                                                    'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                                    row.currency_code === 'EUR' ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-800',
                                                                )}>
                                                                    {row.currency_code}
                                                                </span>
                                                            ) : (
                                                                <span className="text-xs text-gray-400">{defaultCurrency}</span>
                                                            )}
                                                        </td>
                                                    )}
                                                </tr>
                                                );
                                            })}
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

                            {/* Errore verifica duplicati */}
                            {duplicateCheckError && (
                                <div className="mt-4 rounded-lg border border-red-200 bg-red-50 p-4">
                                    <p className="text-sm font-medium text-red-800">
                                        ⚠️ {duplicateCheckError}
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            onClick={() => { setDuplicateCheckError(null); doImport(getRowsToImport()); }}
                                            className="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                                        >
                                            Importa comunque
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => { setDuplicateCheckError(null); handleImport(); }}
                                            className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                            Riprova verifica
                                        </button>
                                    </div>
                                </div>
                            )}
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
                                    disabled={selectedRows.size === 0 || importProcessing || duplicateCheckLoading || (columnMapping.account == null && !data.account_id)}
                                >
                                    {importProcessing ? 'Avvio in corso…' : duplicateCheckLoading ? 'Verifica duplicati…' : `Importa ${selectedRows.size} transazioni`}
                                </PrimaryButton>
                            )}
                        </div>
                    </div>
                </div>
            </PageContent>

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
                            {/* Azioni globali veloci */}
                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <span className="text-xs text-gray-400">Applica a tutte:</span>
                                <button
                                    type="button"
                                    onClick={() => {
                                        const resolutions: Record<number, DuplicateResolution> = {};
                                        duplicates.forEach((d) => {
                                            resolutions[d.row_index] = { action: 'ignore', duplicate_transaction_id: null };
                                        });
                                        setDuplicateResolutions(resolutions);
                                    }}
                                    className="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    Ignora tutte
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        const resolutions: Record<number, DuplicateResolution> = {};
                                        duplicates.forEach((d) => {
                                            resolutions[d.row_index] = { action: 'import', duplicate_transaction_id: d.existing[0]?.id ?? null };
                                        });
                                        setDuplicateResolutions(resolutions);
                                    }}
                                    className="inline-flex items-center gap-1 rounded-md border border-blue-300 bg-white px-3 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    Importa tutte comunque
                                </button>
                            </div>
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
                                {importProcessing ? 'Avvio in corso…' : 'Conferma e importa'}
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
