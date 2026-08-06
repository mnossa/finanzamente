import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import { IndexPageHeaderActions, IndexPageMobileToolbar, MobileCreateActionButton } from '@/Components/IndexPageListToolbars';
import EmptyState from '@/Components/EmptyState';
import { Head, router } from '@inertiajs/react';
import { type FormDataConvertible } from '@inertiajs/core';
import clsx from 'clsx';
import { formatCurrency, formatDate } from '@/utils/format';
import InvestmentHubNav from '@/Components/InvestmentHubNav';
import { useMobileFabAction } from '@/hooks/useMobileFabAction';
import { MOBILE_FAB_ACTION_INVESTMENT_ANALYSES_NEW } from '@/utils/mobilePrimaryFab';

// ─── Tipi ────────────────────────────────────────────────────────────────────

type TemplateType =
    | 'fotovoltaico'
    | 'auto_elettrica'
    | 'cappotto_termico'
    | 'pompa_calore'
    | 'personalizzato';

interface InvestmentAnalysis {
    id: number;
    name: string;
    template_type: TemplateType;
    start_date: string | null;
    initial_cost: number;
    total_annual_savings: number | null;
    breakeven_years: number | null;
    roi_percentage: number | null;
    template_data: Record<string, unknown> | null;
    created_at: string;
}

interface IndexProps {
    analyses: InvestmentAnalysis[];
}

// ─── Template definitions ─────────────────────────────────────────────────────

interface TemplateDefinition {
    id: TemplateType;
    label: string;
    emoji: string;
    description: string;
    color: string;
}

const TEMPLATES: TemplateDefinition[] = [
    {
        id: 'fotovoltaico',
        label: 'Fotovoltaico',
        emoji: '☀️',
        description: 'Impianto solare fotovoltaico per la produzione di energia elettrica',
        color: 'bg-yellow-50 border-yellow-200 hover:border-yellow-400 dark:bg-yellow-900/10 dark:border-yellow-800',
    },
    {
        id: 'auto_elettrica',
        label: 'Auto Elettrica / Ibrida',
        emoji: '⚡',
        description: 'Passaggio a veicolo elettrico o ibrido con risparmio su carburante e manutenzione',
        color: 'bg-blue-50 border-blue-200 hover:border-blue-400 dark:bg-blue-900/10 dark:border-blue-800',
    },
    {
        id: 'cappotto_termico',
        label: 'Cappotto Termico',
        emoji: '🏠',
        description: 'Isolamento termico dell\'edificio per ridurre i consumi di riscaldamento e raffrescamento',
        color: 'bg-orange-50 border-orange-200 hover:border-orange-400 dark:bg-orange-900/10 dark:border-orange-800',
    },
    {
        id: 'pompa_calore',
        label: 'Pompa di Calore',
        emoji: '♨️',
        description: 'Sostituzione di caldaia tradizionale con pompa di calore ad alta efficienza',
        color: 'bg-green-50 border-green-200 hover:border-green-400 dark:bg-green-900/10 dark:border-green-800',
    },
    {
        id: 'personalizzato',
        label: 'Personalizzato',
        emoji: '✏️',
        description: 'Inserisci manualmente costi e risparmi per qualsiasi tipo di investimento',
        color: 'bg-slate-50 border-slate-200 hover:border-slate-400 dark:bg-slate-900/10 dark:border-slate-700',
    },
];

// ─── Calcoli per template ─────────────────────────────────────────────────────

interface CalcResult {
    totalAnnualSavings: number;
    breakevenYears: number | null;
    roiPercentage: number | null;
}

interface FotovoltaicoData {
    potenza_kw: number;
    ore_sole_anno: number;
    autoconsumo_percentuale: number;
    tariffa_energia: number;
    tariffa_vendita: number;
    incentivo_detrazione: number;
}

interface AutoElettricaData {
    km_anno: number;
    consumo_vecchio_litri: number;
    prezzo_carburante: number;
    consumo_nuovo_kwh: number;
    prezzo_kwh_casa: number;
    risparmio_bollo: number;
    risparmio_assicurazione: number;
    risparmio_manutenzione: number;
}

interface CappottoTermicoData {
    bolletta_annua_riscaldamento: number;
    risparmio_percentuale: number;
    incentivo_detrazione: number;
}

interface PompaCaloreData {
    bolletta_annua_gas: number;
    cop_medio: number;
    tariffa_energia: number;
    incentivo_detrazione: number;
}

interface PersonalizzatoData {
    risparmio_annuo_manuale: number;
    incentivo_totale: number;
}

type TemplateData =
    | FotovoltaicoData
    | AutoElettricaData
    | CappottoTermicoData
    | PompaCaloreData
    | PersonalizzatoData;

function calcFotovoltaico(d: FotovoltaicoData, cost: number): CalcResult {
    const produzioneAnnua = d.potenza_kw * d.ore_sole_anno * 0.85;
    const autoconsumo = produzioneAnnua * (d.autoconsumo_percentuale / 100);
    const surplus = produzioneAnnua - autoconsumo;
    const risparmioAutoconsumo = autoconsumo * d.tariffa_energia;
    const risparmioVendita = surplus * d.tariffa_vendita;
    const totalAnnualSavings = risparmioAutoconsumo + risparmioVendita;
    const costoNetto = cost * (1 - d.incentivo_detrazione / 100);
    const breakevenYears = totalAnnualSavings > 0 ? costoNetto / totalAnnualSavings : null;
    const roiPercentage = costoNetto > 0 ? (totalAnnualSavings / costoNetto) * 100 : null;
    return { totalAnnualSavings, breakevenYears, roiPercentage };
}

function calcAutoElettrica(d: AutoElettricaData, cost: number): CalcResult {
    const costoCarburanteAnno = (d.km_anno / 100) * d.consumo_vecchio_litri * d.prezzo_carburante;
    const costoEnergiaAnno = (d.km_anno / 100) * d.consumo_nuovo_kwh * d.prezzo_kwh_casa;
    const risparmioCarburante = costoCarburanteAnno - costoEnergiaAnno;
    const totalAnnualSavings =
        risparmioCarburante + d.risparmio_bollo + d.risparmio_assicurazione + d.risparmio_manutenzione;
    const breakevenYears = totalAnnualSavings > 0 ? cost / totalAnnualSavings : null;
    const roiPercentage = cost > 0 ? (totalAnnualSavings / cost) * 100 : null;
    return { totalAnnualSavings, breakevenYears, roiPercentage };
}

function calcCappottoTermico(d: CappottoTermicoData, cost: number): CalcResult {
    const totalAnnualSavings = d.bolletta_annua_riscaldamento * (d.risparmio_percentuale / 100);
    const costoNetto = cost * (1 - d.incentivo_detrazione / 100);
    const breakevenYears = totalAnnualSavings > 0 ? costoNetto / totalAnnualSavings : null;
    const roiPercentage = costoNetto > 0 ? (totalAnnualSavings / costoNetto) * 100 : null;
    return { totalAnnualSavings, breakevenYears, roiPercentage };
}

function calcPompaCalore(d: PompaCaloreData, cost: number): CalcResult {
    const consumoEquivalenteKwh = d.bolletta_annua_gas / 0.1; // stima gas -> kWh termici
    const kwh_elettrici = consumoEquivalenteKwh / d.cop_medio;
    const costoNuovoAnno = kwh_elettrici * d.tariffa_energia;
    const totalAnnualSavings = Math.max(0, d.bolletta_annua_gas - costoNuovoAnno);
    const costoNetto = cost * (1 - d.incentivo_detrazione / 100);
    const breakevenYears = totalAnnualSavings > 0 ? costoNetto / totalAnnualSavings : null;
    const roiPercentage = costoNetto > 0 ? (totalAnnualSavings / costoNetto) * 100 : null;
    return { totalAnnualSavings, breakevenYears, roiPercentage };
}

function calcPersonalizzato(d: PersonalizzatoData, cost: number): CalcResult {
    const totalAnnualSavings = d.risparmio_annuo_manuale;
    const costoNetto = cost - d.incentivo_totale;
    const breakevenYears = totalAnnualSavings > 0 ? costoNetto / totalAnnualSavings : null;
    const roiPercentage = costoNetto > 0 ? (totalAnnualSavings / costoNetto) * 100 : null;
    return { totalAnnualSavings, breakevenYears, roiPercentage };
}

function calculateResults(templateType: TemplateType, templateData: TemplateData, cost: number): CalcResult {
    switch (templateType) {
        case 'fotovoltaico':
            return calcFotovoltaico(templateData as FotovoltaicoData, cost);
        case 'auto_elettrica':
            return calcAutoElettrica(templateData as AutoElettricaData, cost);
        case 'cappotto_termico':
            return calcCappottoTermico(templateData as CappottoTermicoData, cost);
        case 'pompa_calore':
            return calcPompaCalore(templateData as PompaCaloreData, cost);
        case 'personalizzato':
            return calcPersonalizzato(templateData as PersonalizzatoData, cost);
        default:
            return { totalAnnualSavings: 0, breakevenYears: null, roiPercentage: null };
    }
}

// ─── Valori di default per template ──────────────────────────────────────────

const DEFAULT_TEMPLATE_DATA: Record<TemplateType, TemplateData> = {
    fotovoltaico: {
        potenza_kw: 4.5,
        ore_sole_anno: 1200,
        autoconsumo_percentuale: 40,
        tariffa_energia: 0.25,
        tariffa_vendita: 0.09,
        incentivo_detrazione: 50,
    } as FotovoltaicoData,
    auto_elettrica: {
        km_anno: 15000,
        consumo_vecchio_litri: 6,
        prezzo_carburante: 1.8,
        consumo_nuovo_kwh: 15,
        prezzo_kwh_casa: 0.25,
        risparmio_bollo: 200,
        risparmio_assicurazione: 0,
        risparmio_manutenzione: 300,
    } as AutoElettricaData,
    cappotto_termico: {
        bolletta_annua_riscaldamento: 1500,
        risparmio_percentuale: 35,
        incentivo_detrazione: 65,
    } as CappottoTermicoData,
    pompa_calore: {
        bolletta_annua_gas: 1200,
        cop_medio: 3.5,
        tariffa_energia: 0.25,
        incentivo_detrazione: 50,
    } as PompaCaloreData,
    personalizzato: {
        risparmio_annuo_manuale: 0,
        incentivo_totale: 0,
    } as PersonalizzatoData,
};

const DEFAULT_INITIAL_COST: Record<TemplateType, number> = {
    fotovoltaico: 8000,
    auto_elettrica: 5000,
    cappotto_termico: 15000,
    pompa_calore: 10000,
    personalizzato: 0,
};

// ─── Componenti form per ogni template ───────────────────────────────────────

function InputField({
    label,
    hint,
    value,
    onChange,
    type = 'number',
    suffix,
    step,
    min,
}: {
    label: string;
    hint?: string;
    value: number;
    onChange: (v: number) => void;
    type?: string;
    suffix?: string;
    step?: string;
    min?: string;
}) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {label}
            </label>
            {hint && <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">{hint}</p>}
            <div className="flex items-center gap-2">
                <input
                    type={type}
                    value={value}
                    min={min ?? '0'}
                    step={step ?? 'any'}
                    onChange={(e) => onChange(parseFloat(e.target.value) || 0)}
                    className="form-input flex-1"
                />
                {suffix && <span className="text-sm text-gray-500 dark:text-gray-400 shrink-0">{suffix}</span>}
            </div>
        </div>
    );
}

function FotovoltaicoForm({ data, onChange }: { data: FotovoltaicoData; onChange: (d: FotovoltaicoData) => void }) {
    const set = (key: keyof FotovoltaicoData) => (v: number) => onChange({ ...data, [key]: v });
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <InputField label="Potenza impianto" hint="Kilowatt di picco installati" value={data.potenza_kw} onChange={set('potenza_kw')} suffix="kWp" />
            <InputField label="Ore di sole / anno" hint="Ore equivalenti nella tua zona (Nord ~1100, Centro ~1300, Sud ~1500)" value={data.ore_sole_anno} onChange={set('ore_sole_anno')} suffix="h/anno" />
            <InputField label="Autoconsumo" hint="Percentuale energia consumata direttamente" value={data.autoconsumo_percentuale} onChange={set('autoconsumo_percentuale')} suffix="%" min="0" />
            <InputField label="Tariffa energia" hint="Costo per kWh dalla bolletta" value={data.tariffa_energia} onChange={set('tariffa_energia')} suffix="€/kWh" step="0.01" />
            <InputField label="Tariffa vendita surplus" hint="Prezzo di vendita kWh al GSE (Ritiro Dedicato)" value={data.tariffa_vendita} onChange={set('tariffa_vendita')} suffix="€/kWh" step="0.01" />
            <InputField label="Detrazione fiscale" hint="50% per abitazione principale (Ecobonus)" value={data.incentivo_detrazione} onChange={set('incentivo_detrazione')} suffix="%" />
        </div>
    );
}

function AutoElettricaForm({ data, onChange }: { data: AutoElettricaData; onChange: (d: AutoElettricaData) => void }) {
    const set = (key: keyof AutoElettricaData) => (v: number) => onChange({ ...data, [key]: v });
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <InputField label="Km percorsi / anno" hint="Chilometri medi percorsi all'anno" value={data.km_anno} onChange={set('km_anno')} suffix="km" />
            <InputField label="Consumo auto attuale" hint="Litri per 100 km" value={data.consumo_vecchio_litri} onChange={set('consumo_vecchio_litri')} suffix="l/100km" step="0.1" />
            <InputField label="Prezzo carburante" hint="Euro per litro" value={data.prezzo_carburante} onChange={set('prezzo_carburante')} suffix="€/l" step="0.01" />
            <InputField label="Consumo auto elettrica" hint="kWh per 100 km" value={data.consumo_nuovo_kwh} onChange={set('consumo_nuovo_kwh')} suffix="kWh/100km" step="0.1" />
            <InputField label="Prezzo kWh domestico" hint="Tariffa elettrica per la ricarica" value={data.prezzo_kwh_casa} onChange={set('prezzo_kwh_casa')} suffix="€/kWh" step="0.01" />
            <InputField label="Risparmio bollo / anno" hint="Veicoli elettrici esenti in molte regioni" value={data.risparmio_bollo} onChange={set('risparmio_bollo')} suffix="€" />
            <InputField label="Risparmio assicurazione / anno" hint="Stima differenza premio RC auto" value={data.risparmio_assicurazione} onChange={set('risparmio_assicurazione')} suffix="€" />
            <InputField label="Risparmio manutenzione / anno" hint="Meno tagliandi, pastiglie freni, ecc." value={data.risparmio_manutenzione} onChange={set('risparmio_manutenzione')} suffix="€" />
        </div>
    );
}

function CappottoTermicoForm({ data, onChange }: { data: CappottoTermicoData; onChange: (d: CappottoTermicoData) => void }) {
    const set = (key: keyof CappottoTermicoData) => (v: number) => onChange({ ...data, [key]: v });
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <InputField label="Bolletta riscaldamento / anno" hint="Spesa annua attuale per riscaldamento" value={data.bolletta_annua_riscaldamento} onChange={set('bolletta_annua_riscaldamento')} suffix="€/anno" />
            <InputField label="Risparmio atteso" hint="Tipicamente 30-50% dopo isolamento (classe C → A)" value={data.risparmio_percentuale} onChange={set('risparmio_percentuale')} suffix="%" />
            <InputField label="Detrazione fiscale" hint="65% Ecobonus, 110% Superbonus (verificare disponibilità)" value={data.incentivo_detrazione} onChange={set('incentivo_detrazione')} suffix="%" />
        </div>
    );
}

function PompaCaloreForm({ data, onChange }: { data: PompaCaloreData; onChange: (d: PompaCaloreData) => void }) {
    const set = (key: keyof PompaCaloreData) => (v: number) => onChange({ ...data, [key]: v });
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <InputField label="Bolletta gas / anno" hint="Spesa annua attuale per riscaldamento a gas" value={data.bolletta_annua_gas} onChange={set('bolletta_annua_gas')} suffix="€/anno" />
            <InputField label="COP medio pompa di calore" hint="Efficienza media (tipicamente 3-4,5 per pompe moderne)" value={data.cop_medio} onChange={set('cop_medio')} suffix="" step="0.1" />
            <InputField label="Tariffa energia elettrica" hint="Costo per kWh dalla bolletta" value={data.tariffa_energia} onChange={set('tariffa_energia')} suffix="€/kWh" step="0.01" />
            <InputField label="Detrazione fiscale" hint="50% Ecobonus per sostituzione caldaia" value={data.incentivo_detrazione} onChange={set('incentivo_detrazione')} suffix="%" />
        </div>
    );
}

function PersonalizzatoForm({ data, onChange }: { data: PersonalizzatoData; onChange: (d: PersonalizzatoData) => void }) {
    const set = (key: keyof PersonalizzatoData) => (v: number) => onChange({ ...data, [key]: v });
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <InputField label="Risparmio annuo stimato" hint="Stima del risparmio netto annuo" value={data.risparmio_annuo_manuale} onChange={set('risparmio_annuo_manuale')} suffix="€/anno" />
            <InputField label="Incentivi / detrazioni totali" hint="Valore totale di incentivi, bonus o detrazioni applicabili" value={data.incentivo_totale} onChange={set('incentivo_totale')} suffix="€" />
        </div>
    );
}

function TemplateForm({
    templateType,
    data,
    onChange,
}: {
    templateType: TemplateType;
    data: TemplateData;
    onChange: (d: TemplateData) => void;
}) {
    switch (templateType) {
        case 'fotovoltaico':
            return <FotovoltaicoForm data={data as FotovoltaicoData} onChange={onChange as (d: FotovoltaicoData) => void} />;
        case 'auto_elettrica':
            return <AutoElettricaForm data={data as AutoElettricaData} onChange={onChange as (d: AutoElettricaData) => void} />;
        case 'cappotto_termico':
            return <CappottoTermicoForm data={data as CappottoTermicoData} onChange={onChange as (d: CappottoTermicoData) => void} />;
        case 'pompa_calore':
            return <PompaCaloreForm data={data as PompaCaloreData} onChange={onChange as (d: PompaCaloreData) => void} />;
        case 'personalizzato':
            return <PersonalizzatoForm data={data as PersonalizzatoData} onChange={onChange as (d: PersonalizzatoData) => void} />;
        default:
            return null;
    }
}

// ─── Wizard ───────────────────────────────────────────────────────────────────

interface WizardState {
    step: 1 | 2 | 3;
    name: string;
    templateType: TemplateType | null;
    startDate: string;
    initialCost: number;
    templateData: TemplateData | null;
}

function ResultCard({ label, value, sub }: { label: string; value: string; sub?: string }) {
    return (
        <div className="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4 text-center">
            <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">{label}</p>
            <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{value}</p>
            {sub && <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{sub}</p>}
        </div>
    );
}

function AnalysisWizard({ onClose }: { onClose: () => void }) {
    const [state, setState] = useState<WizardState>({
        step: 1,
        name: '',
        templateType: null,
        startDate: new Date().toISOString().slice(0, 10),
        initialCost: 0,
        templateData: null,
    });
    const [isSubmitting, setIsSubmitting] = useState(false);

    const selectedTemplate = TEMPLATES.find((t) => t.id === state.templateType);

    const results =
        state.templateType && state.templateData
            ? calculateResults(state.templateType, state.templateData, state.initialCost)
            : null;

    const handleSelectTemplate = (id: TemplateType) => {
        setState((s) => ({
            ...s,
            templateType: id,
            initialCost: DEFAULT_INITIAL_COST[id],
            templateData: DEFAULT_TEMPLATE_DATA[id],
            name: s.name || TEMPLATES.find((t) => t.id === id)?.label || '',
        }));
    };

    const handleSubmit = () => {
        if (!state.templateType || !state.templateData || !state.name || !results) return;
        setIsSubmitting(true);

        router.post(
            route('investment-analyses.store'),
            {
                name: state.name,
                template_type: state.templateType,
                start_date: state.startDate || null,
                initial_cost: state.initialCost,
                template_data: state.templateData as unknown as { [key: string]: FormDataConvertible },
                total_annual_savings: results.totalAnnualSavings,
                breakeven_years: results.breakevenYears,
                roi_percentage: results.roiPercentage,
            },
            {
                onFinish: () => setIsSubmitting(false),
                onSuccess: () => onClose(),
            }
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 overflow-y-auto">
            <div className="relative w-full max-w-2xl my-8 rounded-2xl bg-gray-50 dark:bg-gray-900 shadow-2xl">
                {/* Header */}
                <div className="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                        Nuova analisi — Step {state.step} di 3
                    </h2>
                    <button
                        onClick={onClose}
                        className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700"
                        aria-label="Chiudi"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M18 6 6 18" /><path d="m6 6 12 12" />
                        </svg>
                    </button>
                </div>

                {/* Progress bar */}
                <div className="h-1 bg-gray-200 dark:bg-gray-700">
                    <div
                        className="h-1 bg-emerald-500 transition-all duration-300"
                        style={{ width: `${(state.step / 3) * 100}%` }}
                    />
                </div>

                {/* Body */}
                <div className="px-6 py-6">
                    {/* Step 1: Selezione template */}
                    {state.step === 1 && (
                        <div>
                            <h3 className="mb-4 text-base font-medium text-gray-900 dark:text-white">
                                Scegli il tipo di investimento
                            </h3>
                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {TEMPLATES.map((t) => (
                                    <button
                                        key={t.id}
                                        onClick={() => handleSelectTemplate(t.id)}
                                        className={clsx(
                                            'rounded-xl border-2 p-4 text-left transition-all duration-150',
                                            t.color,
                                            state.templateType === t.id
                                                ? 'ring-2 ring-emerald-500 ring-offset-2'
                                                : ''
                                        )}
                                    >
                                        <div className="mb-2 text-2xl">{t.emoji}</div>
                                        <div className="font-medium text-gray-900 dark:text-white">{t.label}</div>
                                        <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">{t.description}</div>
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Step 2: Input specifici */}
                    {state.step === 2 && state.templateType && state.templateData && (
                        <div>
                            <div className="mb-5 flex items-center gap-3">
                                <span className="text-3xl">{selectedTemplate?.emoji}</span>
                                <div>
                                    <h3 className="font-semibold text-gray-900 dark:text-white">{selectedTemplate?.label}</h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">{selectedTemplate?.description}</p>
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Nome analisi
                                        </label>
                                        <input
                                            type="text"
                                            value={state.name}
                                            onChange={(e) => setState((s) => ({ ...s, name: e.target.value }))}
                                            className="form-input w-full"
                                            placeholder="Es. Fotovoltaico 5kWp casa"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Data inizio stimata
                                        </label>
                                        <input
                                            type="date"
                                            value={state.startDate}
                                            onChange={(e) => setState((s) => ({ ...s, startDate: e.target.value }))}
                                            className="form-input w-full"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Costo iniziale totale (€)
                                    </label>
                                    <input
                                        type="number"
                                        value={state.initialCost}
                                        min="0"
                                        step="100"
                                        onChange={(e) =>
                                            setState((s) => ({ ...s, initialCost: parseFloat(e.target.value) || 0 }))
                                        }
                                        className="form-input w-full"
                                    />
                                </div>

                                <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                                    <h4 className="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Parametri specifici
                                    </h4>
                                    <TemplateForm
                                        templateType={state.templateType}
                                        data={state.templateData}
                                        onChange={(d) => setState((s) => ({ ...s, templateData: d }))}
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 3: Riepilogo e risultati */}
                    {state.step === 3 && results && (
                        <div>
                            <div className="mb-5 flex items-center gap-3">
                                <span className="text-3xl">{selectedTemplate?.emoji}</span>
                                <div>
                                    <h3 className="font-semibold text-gray-900 dark:text-white">{state.name}</h3>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">{selectedTemplate?.label}</p>
                                </div>
                            </div>

                            <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <ResultCard
                                    label="Risparmio annuo stimato"
                                    value={formatCurrency(results.totalAnnualSavings)}
                                />
                                <ResultCard
                                    label="Ammortamento"
                                    value={
                                        results.breakevenYears !== null
                                            ? `${results.breakevenYears.toFixed(1)} anni`
                                            : 'N/D'
                                    }
                                    sub="anni per rientrare dell'investimento"
                                />
                                <ResultCard
                                    label="ROI annuo"
                                    value={
                                        results.roiPercentage !== null
                                            ? `${results.roiPercentage.toFixed(1)}%`
                                            : 'N/D'
                                    }
                                    sub="ritorno sull'investimento netto"
                                />
                            </div>

                            <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 text-sm">
                                <dl className="grid grid-cols-2 gap-x-4 gap-y-2">
                                    <dt className="text-gray-500 dark:text-gray-400">Costo iniziale</dt>
                                    <dd className="font-medium text-gray-900 dark:text-white text-right">
                                        {formatCurrency(state.initialCost)}
                                    </dd>
                                    {state.startDate && (
                                        <>
                                            <dt className="text-gray-500 dark:text-gray-400">Data inizio stimata</dt>
                                            <dd className="font-medium text-gray-900 dark:text-white text-right">
                                                {formatDate(state.startDate)}
                                            </dd>
                                        </>
                                    )}
                                </dl>
                            </div>

                            <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">
                                * I calcoli sono stime indicative basate sui parametri inseriti. I risultati reali possono
                                variare in base a fattori locali, variazioni tariffarie e condizioni d'uso.
                            </p>
                        </div>
                    )}
                </div>

                {/* Footer navigation */}
                <div className="flex items-center justify-between border-t border-gray-200 dark:border-gray-700 px-6 py-4">
                    <button
                        onClick={() => {
                            if (state.step === 1) {
                                onClose();
                            } else {
                                setState((s) => ({ ...s, step: (s.step - 1) as 1 | 2 | 3 }));
                            }
                        }}
                        className="btn btn-secondary"
                    >
                        {state.step === 1 ? 'Annulla' : '← Indietro'}
                    </button>

                    {state.step < 3 ? (
                        <button
                            disabled={
                                (state.step === 1 && !state.templateType) ||
                                (state.step === 2 && !state.name.trim())
                            }
                            onClick={() => setState((s) => ({ ...s, step: (s.step + 1) as 1 | 2 | 3 }))}
                            className="btn btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Avanti →
                        </button>
                    ) : (
                        <button
                            onClick={handleSubmit}
                            disabled={isSubmitting}
                            className="btn btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {isSubmitting ? 'Salvataggio...' : 'Salva analisi'}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

// ─── Card analisi ─────────────────────────────────────────────────────────────

const TEMPLATE_LABEL: Record<TemplateType, string> = {
    fotovoltaico: 'Fotovoltaico',
    auto_elettrica: 'Auto Elettrica / Ibrida',
    cappotto_termico: 'Cappotto Termico',
    pompa_calore: 'Pompa di Calore',
    personalizzato: 'Personalizzato',
};

const TEMPLATE_EMOJI: Record<TemplateType, string> = {
    fotovoltaico: '☀️',
    auto_elettrica: '⚡',
    cappotto_termico: '🏠',
    pompa_calore: '♨️',
    personalizzato: '✏️',
};

function AnalysisCard({
    analysis,
    onDelete,
}: {
    analysis: InvestmentAnalysis;
    onDelete: (id: number) => void;
}) {
    const [confirmDelete, setConfirmDelete] = useState(false);

    return (
        <div className="rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                    <span className="text-2xl mt-0.5">{TEMPLATE_EMOJI[analysis.template_type]}</span>
                    <div>
                        <h3 className="font-semibold text-gray-900 dark:text-white">{analysis.name}</h3>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {TEMPLATE_LABEL[analysis.template_type]}
                            {analysis.start_date && ` · ${formatDate(analysis.start_date)}`}
                        </p>
                    </div>
                </div>
                {!confirmDelete ? (
                    <button
                        onClick={() => setConfirmDelete(true)}
                        className="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20 transition-colors"
                        aria-label="Elimina"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M3 6h18" /><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" /><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                        </svg>
                    </button>
                ) : (
                    <div className="flex items-center gap-2 shrink-0">
                        <span className="text-xs text-red-600 dark:text-red-400">Eliminare?</span>
                        <button
                            onClick={() => onDelete(analysis.id)}
                            className="text-xs px-2 py-1 rounded bg-red-600 text-white hover:bg-red-700"
                        >
                            Sì
                        </button>
                        <button
                            onClick={() => setConfirmDelete(false)}
                            className="text-xs px-2 py-1 rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300"
                        >
                            No
                        </button>
                    </div>
                )}
            </div>

            <div className="mt-4 grid grid-cols-3 gap-3 text-center">
                <div className="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 p-2">
                    <p className="text-xs text-gray-500 dark:text-gray-400">Risparmio annuo</p>
                    <p className="text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                        {analysis.total_annual_savings !== null
                            ? formatCurrency(analysis.total_annual_savings)
                            : '—'}
                    </p>
                </div>
                <div className="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-2">
                    <p className="text-xs text-gray-500 dark:text-gray-400">Ammortamento</p>
                    <p className="text-sm font-semibold text-blue-700 dark:text-blue-400">
                        {analysis.breakeven_years !== null
                            ? `${analysis.breakeven_years.toFixed(1)} anni`
                            : '—'}
                    </p>
                </div>
                <div className="rounded-lg bg-purple-50 dark:bg-purple-900/20 p-2">
                    <p className="text-xs text-gray-500 dark:text-gray-400">ROI annuo</p>
                    <p className="text-sm font-semibold text-purple-700 dark:text-purple-400">
                        {analysis.roi_percentage !== null
                            ? `${analysis.roi_percentage.toFixed(1)}%`
                            : '—'}
                    </p>
                </div>
            </div>

            <div className="mt-3 flex items-center justify-between text-xs text-gray-400 dark:text-gray-500">
                <span>Costo: {formatCurrency(analysis.initial_cost)}</span>
                <span>Creata il {formatDate(analysis.created_at)}</span>
            </div>
        </div>
    );
}

// ─── Pagina principale ────────────────────────────────────────────────────────

export default function Index({ analyses }: IndexProps) {
    const [showWizard, setShowWizard] = useState(false);

    const openWizard = () => setShowWizard(true);

    useMobileFabAction(MOBILE_FAB_ACTION_INVESTMENT_ANALYSES_NEW, openWizard);

    const handleDelete = (id: number) => {
        router.delete(route('investment-analyses.destroy', id), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Analisi Investimenti"
                    mobileTitle="Analisi"
                    backLink={route('investments.index')}
                    hideSubtitleOnMobile
                    subtitle="Calcola risparmio e ammortamento dei tuoi investimenti energetici e tecnologici"
                    actions={
                        <IndexPageHeaderActions>
                            <button
                                type="button"
                                onClick={openWizard}
                                className="btn btn-primary"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2">
                                    <path d="M5 12h14" /><path d="M12 5v14" />
                                </svg>
                                Nuova analisi
                            </button>
                        </IndexPageHeaderActions>
                    }
                />
            }
        >
            <Head title="Analisi Investimenti" />

            <PageContent>
                <InvestmentHubNav active="analyses" />
                <IndexPageMobileToolbar>
                    <MobileCreateActionButton
                        actionId={MOBILE_FAB_ACTION_INVESTMENT_ANALYSES_NEW}
                        onClick={openWizard}
                        className="btn btn-primary justify-center"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mr-2">
                            <path d="M5 12h14" /><path d="M12 5v14" />
                        </svg>
                        Nuova analisi
                    </MobileCreateActionButton>
                </IndexPageMobileToolbar>
                {analyses.length === 0 ? (
                    <EmptyState
                        icon="📊"
                        title="Nessuna analisi ancora"
                        description="Crea la tua prima analisi per calcolare il risparmio e l'ammortamento di un investimento come un impianto fotovoltaico, un cappotto termico o un'auto elettrica."
                        showCreateButton={false}
                    >
                        <MobileCreateActionButton
                            actionId={MOBILE_FAB_ACTION_INVESTMENT_ANALYSES_NEW}
                            onClick={openWizard}
                            className="btn btn-primary mt-4"
                        >
                            Crea la prima analisi
                        </MobileCreateActionButton>
                    </EmptyState>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {analyses.map((analysis) => (
                            <AnalysisCard key={analysis.id} analysis={analysis} onDelete={handleDelete} />
                        ))}
                    </div>
                )}
            </PageContent>

            {showWizard && <AnalysisWizard onClose={() => setShowWizard(false)} />}
        </AuthenticatedLayout>
    );
}
