import React, { useState, useMemo } from 'react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageContent from '@/Components/PageContent';
import PageHeader from '@/Components/PageHeader';
import CardBox from '@/Components/CardBox';
import clsx from 'clsx';
import {
    ResponsiveContainer,
    AreaChart,
    Area,
    CartesianGrid,
    XAxis,
    YAxis,
    Tooltip,
    Legend,
    LineChart,
    Line,
    ReferenceLine,
} from 'recharts';
import {
    formatEuro,
    useChartDarkMode,
    getChartTooltipStyle,
    getChartMutedTextColor,
} from '@/Components/Charts/chartConfig';

// ─── Tipi ────────────────────────────────────────────────────────────────────

interface PresetScenario {
    id: string;
    name: string;
    return: number;
    description: string;
}

interface HistoricalData {
    sp500_avg_return: number;
    avg_inflation_italy: number;
    avg_bond_return: number;
    avg_savings_account: number;
}

interface CrisisScenario {
    id: string;
    name: string;
    description: string;
    peak_drop: number;
    recovery_months: number;
    monthly_returns: number[];
    labels: string[];
}

interface SimulationsProps {
    presetScenarios: PresetScenario[];
    historicalData: HistoricalData;
    crisisScenarios: CrisisScenario[];
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const yAxisFormatter = (value: number): string => {
    const abs = Math.abs(value);
    if (abs >= 1_000_000) return `€${(value / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `€${(value / 1_000).toFixed(0)}k`;
    return `€${value.toFixed(0)}`;
};

function SliderField({
    label,
    value,
    min,
    max,
    step,
    onChange,
    format,
    className,
}: {
    label: string;
    value: number;
    min: number;
    max: number;
    step: number;
    onChange: (v: number) => void;
    format: (v: number) => string;
    className?: string;
}) {
    return (
        <div className={clsx('space-y-1', className)}>
            <div className="flex justify-between text-sm">
                <span className="font-medium text-gray-700 dark:text-gray-300">{label}</span>
                <span className="font-bold text-emerald-600 dark:text-emerald-400">{format(value)}</span>
            </div>
            <input
                type="range"
                min={min}
                max={max}
                step={step}
                value={value}
                onChange={(e) => onChange(Number(e.target.value))}
                className="h-2 w-full cursor-pointer appearance-none rounded-full bg-gray-200 accent-emerald-500 dark:bg-gray-700"
                aria-label={label}
            />
            <div className="flex justify-between text-xs text-gray-400 dark:text-gray-500">
                <span>{format(min)}</span>
                <span>{format(max)}</span>
            </div>
        </div>
    );
}

function InsightPill({
    icon,
    text,
    variant = 'info',
}: {
    icon: string;
    text: string;
    variant?: 'info' | 'warning' | 'success' | 'danger';
}) {
    const classes = {
        info: 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
        warning: 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
        success: 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
        danger: 'bg-red-50 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800',
    };

    return (
        <div className={clsx('flex items-start gap-2 rounded-xl border px-4 py-3 text-sm', classes[variant])}>
            <span className="mt-0.5 shrink-0 text-base">{icon}</span>
            <span>{text}</span>
        </div>
    );
}

function SimTooltip({
    active,
    payload,
    label,
    valueKeys,
    colors,
}: {
    active?: boolean;
    payload?: Array<{ dataKey?: string; name?: string; value?: number }>;
    label?: string;
    valueKeys?: string[];
    colors?: Record<string, string>;
}) {
    const isDark = useChartDarkMode();
    const tooltipStyle = getChartTooltipStyle(isDark);

    if (!active || !payload?.length) return null;

    return (
        <div style={{ ...tooltipStyle, minWidth: '180px' }}>
            <p style={{ fontWeight: 600, marginBottom: '4px', color: getChartMutedTextColor(isDark) }}>{label}</p>
            {payload.map((entry) => (
                <p key={String(entry.dataKey)} style={{ margin: '2px 0', color: colors?.[String(entry.dataKey)] ?? '#64748b' }}>
                    {entry.name}: <strong>{formatEuro(Number(entry.value))}</strong>
                </p>
            ))}
        </div>
    );
}

// ─── Tab A: Interesse Composto ────────────────────────────────────────────────

function CompoundInterestSimulator({ presetScenarios }: { presetScenarios: PresetScenario[] }) {
    const isDark = useChartDarkMode();

    const [initialCapital, setInitialCapital] = useState(10000);
    const [monthlyContribution, setMonthlyContribution] = useState(300);
    const [annualReturn, setAnnualReturn] = useState(7);
    const [years, setYears] = useState(20);
    const [inflationEnabled, setInflationEnabled] = useState(false);
    const [inflationRate, setInflationRate] = useState(2.5);

    const data = useMemo(() => {
        const points = [];
        let balance = initialCapital;
        let totalContributed = initialCapital;

        for (let y = 0; y <= years; y++) {
            const realBalance = inflationEnabled
                ? balance / Math.pow(1 + inflationRate / 100, y)
                : balance;

            points.push({
                anno: `Anno ${y}`,
                'Capitale Versato': Math.round(totalContributed),
                'Interessi Maturati': Math.round(balance - totalContributed),
                'Valore Reale': inflationEnabled ? Math.round(realBalance) : undefined,
            });

            const monthlyRate = annualReturn / 100 / 12;
            for (let m = 0; m < 12; m++) {
                balance = balance * (1 + monthlyRate) + monthlyContribution;
                if (y < years) totalContributed += monthlyContribution;
            }
        }

        return points;
    }, [initialCapital, monthlyContribution, annualReturn, years, inflationEnabled, inflationRate]);

    const finalValue = data[data.length - 1];
    const totalContributed = finalValue?.['Capitale Versato'] ?? 0;
    const totalInterest = finalValue?.['Interessi Maturati'] ?? 0;
    const totalValue = totalContributed + totalInterest;
    const realValue = finalValue?.['Valore Reale'] ?? totalValue;

    const insights = useMemo(() => {
        const list: { icon: string; text: string; variant: 'info' | 'warning' | 'success' | 'danger' }[] = [];

        const multiplier = totalValue / (totalContributed || 1);
        if (multiplier > 2) {
            list.push({
                icon: '🚀',
                text: `Il tuo denaro si moltiplicherà per ${multiplier.toFixed(1)}x grazie all'interesse composto.`,
                variant: 'success',
            });
        }

        if (monthlyContribution > 0 && totalValue > 0 && annualReturn > 0) {
            const extraMonthly = 50;
            const rateM = annualReturn / 100 / 12;
            const extraFV = extraMonthly * ((Math.pow(1 + rateM, years * 12) - 1) / rateM);
            const yearsSaved = Math.log(1 + extraFV / totalValue) / Math.log(1 + annualReturn / 100);
            if (yearsSaved > 0.5) {
                list.push({
                    icon: '💡',
                    text: `Aumentando il risparmio di soli €50/mese, raggiungeresti il tuo obiettivo circa ${yearsSaved.toFixed(1)} anni prima.`,
                    variant: 'info',
                });
            }
        }

        if (inflationEnabled && inflationRate > 0) {
            const halvingYears = Math.log(2) / Math.log(1 + inflationRate / 100);
            list.push({
                icon: '⚠️',
                text: `Con un'inflazione al ${inflationRate}%, il tuo potere d'acquisto si dimezzerà in circa ${halvingYears.toFixed(0)} anni.`,
                variant: 'warning',
            });
        }

        if (totalInterest > totalContributed) {
            list.push({
                icon: '✨',
                text: `Gli interessi maturati (${formatEuro(totalInterest)}) superano il capitale versato. L'interesse composto lavora per te!`,
                variant: 'success',
            });
        }

        return list;
    }, [totalValue, totalContributed, totalInterest, monthlyContribution, annualReturn, years, inflationEnabled, inflationRate]);

    return (
        <div className="space-y-6">
            <div className="grid gap-6 lg:grid-cols-2">
                {/* Parametri */}
                <CardBox className="space-y-4">
                    <h3 className="font-semibold text-gray-900 dark:text-white">⚙️ Parametri</h3>

                    <SliderField
                        label="Capitale iniziale"
                        value={initialCapital}
                        min={0}
                        max={100000}
                        step={500}
                        onChange={setInitialCapital}
                        format={(v) => formatEuro(v)}
                    />
                    <SliderField
                        label="Versamento mensile"
                        value={monthlyContribution}
                        min={0}
                        max={3000}
                        step={50}
                        onChange={setMonthlyContribution}
                        format={(v) => formatEuro(v)}
                    />
                    <SliderField
                        label="Rendimento annuo atteso"
                        value={annualReturn}
                        min={1}
                        max={15}
                        step={0.5}
                        onChange={setAnnualReturn}
                        format={(v) => `${v}%`}
                    />
                    <SliderField
                        label="Orizzonte temporale"
                        value={years}
                        min={5}
                        max={40}
                        step={1}
                        onChange={setYears}
                        format={(v) => `${v} anni`}
                    />

                    {/* Scenari predefiniti */}
                    <div>
                        <p className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Scenari predefiniti</p>
                        <div className="flex flex-wrap gap-2">
                            {presetScenarios.map((s) => (
                                <button
                                    key={s.id}
                                    onClick={() => setAnnualReturn(s.return)}
                                    title={s.description}
                                    className={clsx(
                                        'rounded-lg px-3 py-1.5 text-xs font-medium transition-all',
                                        annualReturn === s.return
                                            ? 'bg-emerald-500 text-white'
                                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
                                    )}
                                >
                                    {s.name} ({s.return}%)
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Toggle inflazione */}
                    <div className="rounded-xl border border-gray-100 p-4 dark:border-gray-700">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-700 dark:text-gray-300">Correggi per inflazione</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400">Mostra il valore reale del tuo capitale</p>
                            </div>
                            <button
                                role="switch"
                                aria-label="Correggi per inflazione"
                                aria-checked={inflationEnabled}
                                onClick={() => setInflationEnabled(!inflationEnabled)}
                                className={clsx(
                                    'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
                                    inflationEnabled ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-600',
                                )}
                            >
                                <span
                                    className={clsx(
                                        'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition duration-200',
                                        inflationEnabled ? 'translate-x-5' : 'translate-x-0',
                                    )}
                                />
                            </button>
                        </div>
                        {inflationEnabled && (
                            <div className="mt-3">
                                <SliderField
                                    label="Tasso d'inflazione annuo"
                                    value={inflationRate}
                                    min={0.5}
                                    max={8}
                                    step={0.5}
                                    onChange={setInflationRate}
                                    format={(v) => `${v}%`}
                                />
                            </div>
                        )}
                    </div>
                </CardBox>

                {/* Risultati */}
                <CardBox className="space-y-4">
                    <h3 className="font-semibold text-gray-900 dark:text-white">📊 Risultato dopo {years} anni</h3>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Valore nominale</p>
                            <p className="mt-1 text-xl font-bold text-gray-900 dark:text-white">{formatEuro(totalValue)}</p>
                        </div>
                        <div className="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Capitale versato</p>
                            <p className="mt-1 text-xl font-bold text-blue-600 dark:text-blue-400">{formatEuro(totalContributed)}</p>
                        </div>
                        <div className="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Interessi maturati</p>
                            <p className="mt-1 text-xl font-bold text-emerald-600 dark:text-emerald-400">{formatEuro(totalInterest)}</p>
                        </div>
                        {inflationEnabled && (
                            <div className="rounded-xl bg-amber-50 p-4 dark:bg-amber-900/20">
                                <p className="text-xs text-amber-600 dark:text-amber-400">Valore reale (odierno)</p>
                                <p className="mt-1 text-xl font-bold text-amber-700 dark:text-amber-300">{formatEuro(realValue)}</p>
                            </div>
                        )}
                    </div>

                    {/* Pillole di insight */}
                    <div className="space-y-2">
                        {insights.map((ins, i) => (
                            <InsightPill key={i} icon={ins.icon} text={ins.text} variant={ins.variant} />
                        ))}
                    </div>
                </CardBox>
            </div>

            {/* Grafico */}
            <CardBox>
                <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">📈 Crescita del capitale nel tempo</h3>
                <div>
                    <ResponsiveContainer width="99%" height={288}>
                        <AreaChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 4 }}>
                            <defs>
                                <linearGradient id="gradContributed" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="#3b82f6" stopOpacity={isDark ? 0.5 : 0.35} />
                                    <stop offset="95%" stopColor="#3b82f6" stopOpacity={0.05} />
                                </linearGradient>
                                <linearGradient id="gradInterest" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="#10b981" stopOpacity={isDark ? 0.5 : 0.35} />
                                    <stop offset="95%" stopColor="#10b981" stopOpacity={0.05} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" stroke={isDark ? '#334155' : '#e2e8f0'} />
                            <XAxis
                                dataKey="anno"
                                tick={{ fill: isDark ? '#94a3b8' : '#64748b', fontSize: 11 }}
                                interval={Math.floor(years / 8)}
                            />
                            <YAxis
                                width={72}
                                tickFormatter={yAxisFormatter}
                                tick={{ fill: isDark ? '#94a3b8' : '#64748b', fontSize: 11 }}
                            />
                            <Tooltip
                                content={
                                    <SimTooltip
                                        colors={{
                                            'Capitale Versato': '#3b82f6',
                                            'Interessi Maturati': '#10b981',
                                            'Valore Reale': '#f59e0b',
                                        }}
                                    />
                                }
                            />
                            <Legend wrapperStyle={{ fontSize: 12, color: isDark ? '#cbd5e1' : '#334155' }} />
                            <Area
                                type="monotone"
                                dataKey="Capitale Versato"
                                stackId="1"
                                stroke="#3b82f6"
                                strokeWidth={2}
                                fill="url(#gradContributed)"
                            />
                            <Area
                                type="monotone"
                                dataKey="Interessi Maturati"
                                stackId="1"
                                stroke="#10b981"
                                strokeWidth={2}
                                fill="url(#gradInterest)"
                            />
                            {inflationEnabled && (
                                <Area
                                    type="monotone"
                                    dataKey="Valore Reale"
                                    stroke="#f59e0b"
                                    strokeWidth={2}
                                    strokeDasharray="5 5"
                                    fill="none"
                                    dot={false}
                                />
                            )}
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            </CardBox>
        </div>
    );
}

// ─── Tab B: Debito vs Investimento ────────────────────────────────────────────

function DebtVsInvestmentSimulator() {
    const isDark = useChartDarkMode();

    const [capital, setCapital] = useState(10000);
    const [debtRate, setDebtRate] = useState(5);
    const [investReturn, setInvestReturn] = useState(7);
    const [taxRate, setTaxRate] = useState(26);
    const [years, setYears] = useState(10);

    const data = useMemo(() => {
        const netInvestReturn = investReturn * (1 - taxRate / 100);
        const points = [];

        for (let y = 0; y <= years; y++) {
            const debtSaving = capital * Math.pow(1 + debtRate / 100, y) - capital;
            const investValue = capital * Math.pow(1 + netInvestReturn / 100, y);
            const investGain = investValue - capital;

            points.push({
                anno: `Anno ${y}`,
                'Risparmio su Debito': Math.round(debtSaving),
                'Guadagno Investimento (netto)': Math.round(investGain),
            });
        }

        return points;
    }, [capital, debtRate, investReturn, taxRate, years]);

    const finalPoint = data[data.length - 1];
    const debtBenefit = finalPoint?.['Risparmio su Debito'] ?? 0;
    const investBenefit = finalPoint?.['Guadagno Investimento (netto)'] ?? 0;
    const betterOption = investBenefit > debtBenefit ? 'invest' : 'debt';
    const diff = Math.abs(investBenefit - debtBenefit);

    return (
        <div className="space-y-6">
            <div className="grid gap-6 lg:grid-cols-2">
                <CardBox className="space-y-4">
                    <h3 className="font-semibold text-gray-900 dark:text-white">⚙️ Parametri</h3>

                    <SliderField
                        label="Capitale disponibile"
                        value={capital}
                        min={1000}
                        max={100000}
                        step={1000}
                        onChange={setCapital}
                        format={(v) => formatEuro(v)}
                    />
                    <SliderField
                        label="Tasso d'interesse del debito"
                        value={debtRate}
                        min={1}
                        max={20}
                        step={0.5}
                        onChange={setDebtRate}
                        format={(v) => `${v}%`}
                    />
                    <SliderField
                        label="Rendimento atteso dell'investimento"
                        value={investReturn}
                        min={1}
                        max={15}
                        step={0.5}
                        onChange={setInvestReturn}
                        format={(v) => `${v}%`}
                    />
                    <SliderField
                        label="Tassazione plusvalenze"
                        value={taxRate}
                        min={0}
                        max={43}
                        step={1}
                        onChange={setTaxRate}
                        format={(v) => `${v}%`}
                    />
                    <SliderField
                        label="Orizzonte temporale"
                        value={years}
                        min={1}
                        max={30}
                        step={1}
                        onChange={setYears}
                        format={(v) => `${v} anni`}
                    />
                </CardBox>

                <CardBox className="space-y-4">
                    <h3 className="font-semibold text-gray-900 dark:text-white">⚖️ Il Verdetto</h3>

                    <div
                        className={clsx(
                            'rounded-2xl border-2 p-6 text-center',
                            betterOption === 'invest'
                                ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-900/20'
                                : 'border-blue-300 bg-blue-50 dark:border-blue-700 dark:bg-blue-900/20',
                        )}
                    >
                        <p className="text-3xl">{betterOption === 'invest' ? '📈' : '💳'}</p>
                        <p className="mt-2 text-lg font-bold text-gray-900 dark:text-white">
                            {betterOption === 'invest' ? 'Conviene Investire' : 'Conviene Estinguere il Debito'}
                        </p>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Vantaggio netto: <strong>{formatEuro(diff)}</strong> in {years} anni
                        </p>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Risparmio su debito</p>
                            <p className="mt-1 text-lg font-bold text-blue-600 dark:text-blue-400">{formatEuro(debtBenefit)}</p>
                        </div>
                        <div className="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Guadagno investimento</p>
                            <p className="mt-1 text-lg font-bold text-emerald-600 dark:text-emerald-400">{formatEuro(investBenefit)}</p>
                        </div>
                    </div>

                    <div className="space-y-2">
                        <InsightPill
                            icon="ℹ️"
                            text={`Rendimento netto dell'investimento (dopo tasse): ${(investReturn * (1 - taxRate / 100)).toFixed(2)}%`}
                            variant="info"
                        />
                        {betterOption === 'debt' && debtRate > investReturn && (
                            <InsightPill
                                icon="⚠️"
                                text="Il tasso del tuo debito supera il rendimento atteso. Estinguere il debito è la scelta più sicura e redditizia."
                                variant="warning"
                            />
                        )}
                        {betterOption === 'invest' && (
                            <InsightPill
                                icon="💡"
                                text="Il rendimento dell'investimento supera il costo del debito. Considera però che l'investimento comporta rischio, il debito no."
                                variant="info"
                            />
                        )}
                    </div>
                </CardBox>
            </div>

            <CardBox>
                <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">📊 Confronto beneficio netto nel tempo</h3>
                <div>
                    <ResponsiveContainer width="99%" height={288}>
                        <LineChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 4 }}>
                            <CartesianGrid strokeDasharray="3 3" stroke={isDark ? '#334155' : '#e2e8f0'} />
                            <XAxis dataKey="anno" tick={{ fill: isDark ? '#94a3b8' : '#64748b', fontSize: 11 }} interval={Math.floor(years / 6)} />
                            <YAxis width={72} tickFormatter={yAxisFormatter} tick={{ fill: isDark ? '#94a3b8' : '#64748b', fontSize: 11 }} />
                            <Tooltip
                                content={
                                    <SimTooltip
                                        colors={{
                                            'Risparmio su Debito': '#3b82f6',
                                            'Guadagno Investimento (netto)': '#10b981',
                                        }}
                                    />
                                }
                            />
                            <Legend wrapperStyle={{ fontSize: 12, color: isDark ? '#cbd5e1' : '#334155' }} />
                            <Line type="monotone" dataKey="Risparmio su Debito" stroke="#3b82f6" strokeWidth={2.5} dot={false} />
                            <Line type="monotone" dataKey="Guadagno Investimento (netto)" stroke="#10b981" strokeWidth={2.5} dot={false} />
                        </LineChart>
                    </ResponsiveContainer>
                </div>
            </CardBox>
        </div>
    );
}

// ─── Tab C: Fondo di Emergenza ────────────────────────────────────────────────

function EmergencyFundSimulator() {
    const [monthlyExpenses, setMonthlyExpenses] = useState(2000);
    const [safetyMonths, setSafetyMonths] = useState(6);
    const [currentFund, setCurrentFund] = useState(3000);
    const [monthlySaving, setMonthlySaving] = useState(300);

    const targetAmount = monthlyExpenses * safetyMonths;
    const remaining = Math.max(0, targetAmount - currentFund);
    const progressPercent = Math.min(100, (currentFund / targetAmount) * 100);
    const monthsToReach = monthlySaving > 0 ? Math.ceil(remaining / monthlySaving) : null;

    const coverageDate = useMemo(() => {
        if (progressPercent >= 100) return null;
        if (!monthsToReach) return null;
        const date = new Date();
        date.setMonth(date.getMonth() + monthsToReach);
        return date.toLocaleDateString('it-IT', { month: 'long', year: 'numeric' });
    }, [progressPercent, monthsToReach]);

    const coverageUntil = useMemo(() => {
        if (currentFund <= 0) return null;
        const coveredMonths = currentFund / monthlyExpenses;
        const date = new Date();
        date.setMonth(date.getMonth() + Math.floor(coveredMonths));
        return date.toLocaleDateString('it-IT', { month: 'long', year: 'numeric' });
    }, [currentFund, monthlyExpenses]);

    const safetyLevels = [
        { months: 3, label: '3 mesi', icon: '🟡', desc: 'Minimo consigliato' },
        { months: 6, label: '6 mesi', icon: '🟢', desc: 'Standard raccomandato' },
        { months: 12, label: '12 mesi', icon: '🔵', desc: 'Alta sicurezza' },
    ];

    const progressBarColor = progressPercent >= 100
        ? 'bg-emerald-500'
        : progressPercent >= 50
            ? 'bg-amber-500'
            : 'bg-red-500';

    return (
        <div className="space-y-6">
            <div className="grid gap-6 lg:grid-cols-2">
                <CardBox className="space-y-4">
                    <h3 className="font-semibold text-gray-900 dark:text-white">⚙️ Parametri</h3>

                    <SliderField
                        label="Spese fisse mensili"
                        value={monthlyExpenses}
                        min={500}
                        max={10000}
                        step={100}
                        onChange={setMonthlyExpenses}
                        format={(v) => formatEuro(v)}
                    />
                    <SliderField
                        label="Fondo di emergenza attuale"
                        value={currentFund}
                        min={0}
                        max={50000}
                        step={500}
                        onChange={setCurrentFund}
                        format={(v) => formatEuro(v)}
                    />
                    <SliderField
                        label="Risparmio mensile dedicato"
                        value={monthlySaving}
                        min={0}
                        max={2000}
                        step={50}
                        onChange={setMonthlySaving}
                        format={(v) => formatEuro(v)}
                    />

                    {/* Livelli di sicurezza */}
                    <div>
                        <p className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Livello di sicurezza</p>
                        <div className="grid grid-cols-3 gap-2">
                            {safetyLevels.map((level) => (
                                <button
                                    key={level.months}
                                    onClick={() => setSafetyMonths(level.months)}
                                    className={clsx(
                                        'rounded-xl border p-3 text-center transition-all',
                                        safetyMonths === level.months
                                            ? 'border-emerald-400 bg-emerald-50 dark:border-emerald-600 dark:bg-emerald-900/20'
                                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600',
                                    )}
                                >
                                    <div className="text-xl">{level.icon}</div>
                                    <div className="mt-1 text-xs font-bold text-gray-900 dark:text-white">{level.label}</div>
                                    <div className="text-xs text-gray-500 dark:text-gray-400">{level.desc}</div>
                                </button>
                            ))}
                        </div>
                    </div>
                </CardBox>

                <CardBox className="space-y-4">
                    <h3 className="font-semibold text-gray-900 dark:text-white">🛡️ Stato del Fondo</h3>

                    {/* Obiettivo */}
                    <div className="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-500 dark:text-gray-400">Obiettivo ({safetyMonths} mesi)</span>
                            <span className="font-bold text-gray-900 dark:text-white">{formatEuro(targetAmount)}</span>
                        </div>
                        <div className="mt-2 flex justify-between text-sm">
                            <span className="text-gray-500 dark:text-gray-400">Fondo attuale</span>
                            <span className="font-bold text-emerald-600 dark:text-emerald-400">{formatEuro(currentFund)}</span>
                        </div>
                    </div>

                    {/* Barra di progresso */}
                    <div>
                        <div className="flex justify-between mb-1 text-sm text-gray-500 dark:text-gray-400">
                            <span>Progresso</span>
                            <span className="font-bold">{progressPercent.toFixed(1)}%</span>
                        </div>
                        <div className="h-4 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div
                                className={clsx('h-4 rounded-full transition-all duration-500', progressBarColor)}
                                style={{ width: `${progressPercent}%` }}
                                role="progressbar"
                                aria-label="Progresso"
                                aria-valuenow={progressPercent}
                                aria-valuemin={0}
                                aria-valuemax={100}
                            />
                        </div>
                    </div>

                    {/* Simulazione imprevisto */}
                    {coverageUntil && (
                        <div className="rounded-xl border-2 border-dashed border-amber-300 p-4 dark:border-amber-700">
                            <p className="text-sm font-semibold text-amber-700 dark:text-amber-400">🚨 Scenario: perdi il lavoro oggi</p>
                            <p className="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                Saresti coperto fino a <strong>{coverageUntil}</strong> ({(currentFund / monthlyExpenses).toFixed(1)} mesi di spese).
                            </p>
                        </div>
                    )}

                    <div className="space-y-2">
                        {progressPercent >= 100 && (
                            <InsightPill icon="✅" text="Ottimo! Il tuo fondo di emergenza è completo. Ora puoi concentrarti sugli investimenti." variant="success" />
                        )}
                        {progressPercent < 100 && monthsToReach !== null && (
                            <InsightPill
                                icon="📅"
                                text={`Con ${formatEuro(monthlySaving)}/mese raggiungerai l'obiettivo entro ${coverageDate} (${monthsToReach} mesi).`}
                                variant="info"
                            />
                        )}
                        {progressPercent < 100 && monthlySaving === 0 && (
                            <InsightPill icon="⚠️" text="Imposta un risparmio mensile per calcolare quando raggiungerai il tuo obiettivo." variant="warning" />
                        )}
                        {progressPercent < 30 && (
                            <InsightPill icon="🔴" text="Il tuo fondo di emergenza è insufficiente. Evita investimenti rischiosi finché non raggiungi almeno 3 mesi di copertura." variant="danger" />
                        )}
                    </div>
                </CardBox>
            </div>
        </div>
    );
}

// ─── Tab D: Stress Test Cigno Nero ────────────────────────────────────────────

function StressTestSimulator({ crisisScenarios }: { crisisScenarios: CrisisScenario[] }) {
    const isDark = useChartDarkMode();

    const [portfolioValue, setPortfolioValue] = useState(50000);
    const [equityPercent, setEquityPercent] = useState(70);
    const [selectedCrisisId, setSelectedCrisisId] = useState(crisisScenarios[0]?.id ?? '');

    const selectedCrisis = crisisScenarios.find((c) => c.id === selectedCrisisId) ?? crisisScenarios[0];

    const data = useMemo(() => {
        if (!selectedCrisis) return [];

        // Mix portafoglio: azioni con rendimento crisi, obbligazioni con rendimento stabile (~3%/anno → 0.25%/mese)
        const bondMonthlyReturnPct = 0.25;

        let value = portfolioValue;
        const initialValue = portfolioValue;

        return selectedCrisis.monthly_returns.map((monthReturn, i) => {
            const equityReturn = monthReturn / 100;
            const bondReturn = bondMonthlyReturnPct / 100;
            const blendedReturn = (equityPercent / 100) * equityReturn + ((100 - equityPercent) / 100) * bondReturn;

            value = value * (1 + blendedReturn);

            return {
                periodo: selectedCrisis.labels[i],
                'Valore Portafoglio': Math.round(value),
                'Valore Iniziale': initialValue,
            };
        });
    }, [selectedCrisis, portfolioValue, equityPercent]);

    const minValue = data.reduce((min, d) => Math.min(min, d['Valore Portafoglio']), portfolioValue);
    const maxDrop = ((minValue - portfolioValue) / portfolioValue) * 100;
    const finalValue = data[data.length - 1]?.['Valore Portafoglio'] ?? portfolioValue;
    const finalGain = finalValue - portfolioValue;

    return (
        <div className="space-y-6">
            <div className="grid gap-6 lg:grid-cols-2">
                <CardBox className="space-y-4">
                    <h3 className="font-semibold text-gray-900 dark:text-white">⚙️ Parametri</h3>

                    <SliderField
                        label="Valore attuale del portafoglio"
                        value={portfolioValue}
                        min={1000}
                        max={500000}
                        step={1000}
                        onChange={setPortfolioValue}
                        format={(v) => formatEuro(v)}
                    />
                    <SliderField
                        label="Quota azionaria del portafoglio"
                        value={equityPercent}
                        min={0}
                        max={100}
                        step={10}
                        onChange={setEquityPercent}
                        format={(v) => `${v}% Azioni / ${100 - v}% Obbligazioni`}
                    />

                    {/* Selezione crisi */}
                    <div>
                        <p className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Seleziona la crisi da simulare</p>
                        <div className="space-y-2">
                            {crisisScenarios.map((crisis) => (
                                <button
                                    key={crisis.id}
                                    onClick={() => setSelectedCrisisId(crisis.id)}
                                    className={clsx(
                                        'w-full rounded-xl border p-3 text-left transition-all',
                                        selectedCrisisId === crisis.id
                                            ? 'border-red-400 bg-red-50 dark:border-red-600 dark:bg-red-900/20'
                                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600',
                                    )}
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="font-medium text-sm text-gray-900 dark:text-white">{crisis.name}</span>
                                        <span className="text-sm font-bold text-red-600 dark:text-red-400">{crisis.peak_drop}%</span>
                                    </div>
                                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{crisis.description}</p>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Recupero storico: ~{crisis.recovery_months} mesi
                                    </p>
                                </button>
                            ))}
                        </div>
                    </div>
                </CardBox>

                <CardBox className="space-y-4">
                    <h3 className="font-semibold text-gray-900 dark:text-white">💥 Impatto sul tuo portafoglio</h3>

                    {selectedCrisis && (
                        <>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="rounded-xl bg-red-50 p-4 dark:bg-red-900/20">
                                    <p className="text-xs text-red-600 dark:text-red-400">Perdita massima simulata</p>
                                    <p className="mt-1 text-xl font-bold text-red-700 dark:text-red-300">
                                        {formatEuro(minValue - portfolioValue)}
                                    </p>
                                    <p className="text-xs text-red-500 dark:text-red-400">({maxDrop.toFixed(1)}%)</p>
                                </div>
                                <div className="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                                    <p className="text-xs text-gray-500 dark:text-gray-400">Valore minimo raggiunto</p>
                                    <p className="mt-1 text-xl font-bold text-gray-900 dark:text-white">{formatEuro(minValue)}</p>
                                </div>
                                <div className="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                                    <p className="text-xs text-gray-500 dark:text-gray-400">Valore a fine periodo</p>
                                    <p className={clsx('mt-1 text-xl font-bold', finalGain >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400')}>
                                        {formatEuro(finalValue)}
                                    </p>
                                </div>
                                <div className="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                                    <p className="text-xs text-gray-500 dark:text-gray-400">Recupero storico</p>
                                    <p className="mt-1 text-xl font-bold text-gray-900 dark:text-white">~{selectedCrisis.recovery_months} mesi</p>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <InsightPill
                                    icon="🧘"
                                    text="La lezione più importante: chi non ha venduto durante il panico ha recuperato tutte le perdite e spesso guadagnato di più."
                                    variant="info"
                                />
                                {equityPercent > 80 && (
                                    <InsightPill
                                        icon="⚠️"
                                        text="Con oltre l'80% in azioni, il tuo portafoglio è molto esposto alle crisi. Assicurati di avere un orizzonte temporale lungo (>10 anni)."
                                        variant="warning"
                                    />
                                )}
                                {equityPercent < 40 && (
                                    <InsightPill
                                        icon="💡"
                                        text="Un portafoglio conservativo limita le perdite nelle crisi ma potrebbe non crescere abbastanza nel lungo periodo."
                                        variant="info"
                                    />
                                )}
                                <InsightPill
                                    icon="📚"
                                    text="Diversificare e mantenere il fondo di emergenza sono le due difese principali contro i 'cigni neri'."
                                    variant="success"
                                />
                            </div>
                        </>
                    )}
                </CardBox>
            </div>

            {/* Grafico */}
            {data.length > 0 && (
                <CardBox>
                    <h3 className="mb-4 font-semibold text-gray-900 dark:text-white">
                        📉 Andamento portafoglio durante: {selectedCrisis?.name}
                    </h3>
                    <div>
                        <ResponsiveContainer width="99%" height={288}>
                            <LineChart data={data} margin={{ top: 8, right: 8, left: 0, bottom: 4 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke={isDark ? '#334155' : '#e2e8f0'} />
                                <XAxis
                                    dataKey="periodo"
                                    tick={{ fill: isDark ? '#94a3b8' : '#64748b', fontSize: 10 }}
                                    interval={Math.floor(data.length / 10)}
                                    angle={-30}
                                    textAnchor="end"
                                    height={48}
                                />
                                <YAxis
                                    width={72}
                                    tickFormatter={yAxisFormatter}
                                    tick={{ fill: isDark ? '#94a3b8' : '#64748b', fontSize: 11 }}
                                    domain={['auto', 'auto']}
                                />
                                <Tooltip
                                    content={
                                        <SimTooltip
                                            colors={{
                                                'Valore Portafoglio': '#3b82f6',
                                                'Valore Iniziale': '#94a3b8',
                                            }}
                                        />
                                    }
                                />
                                <Legend wrapperStyle={{ fontSize: 12, color: isDark ? '#cbd5e1' : '#334155' }} />
                                <ReferenceLine
                                    y={portfolioValue}
                                    stroke="#94a3b8"
                                    strokeDasharray="4 4"
                                    strokeWidth={1.5}
                                    label={{ value: 'Valore iniziale', fill: isDark ? '#94a3b8' : '#64748b', fontSize: 11 }}
                                />
                                <Line
                                    type="monotone"
                                    dataKey="Valore Portafoglio"
                                    stroke="#3b82f6"
                                    strokeWidth={2.5}
                                    dot={false}
                                    activeDot={{ r: 5, fill: '#3b82f6' }}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                    <p className="mt-2 text-center text-xs text-gray-400 dark:text-gray-500">
                        * Simulazione basata sui rendimenti mensili storici. Passato non garanzia di futuro.
                    </p>
                </CardBox>
            )}
        </div>
    );
}

// ─── Pagina principale ────────────────────────────────────────────────────────

const TABS = [
    { id: 'compound', label: 'Interesse Composto', icon: '📈' },
    { id: 'debt_vs_invest', label: 'Debito vs Investimento', icon: '⚖️' },
    { id: 'emergency', label: 'Fondo di Emergenza', icon: '🛡️' },
    { id: 'stress_test', label: 'Stress Test', icon: '💥' },
] as const;

type TabId = typeof TABS[number]['id'];

export default function SimulationsIndex({ presetScenarios, historicalData, crisisScenarios }: SimulationsProps) {
    const [activeTab, setActiveTab] = useState<TabId>('compound');

    return (
        <AuthenticatedLayout header={<PageHeader title="Simulazioni Finanziarie" />}>
            <Head title="Simulazioni Finanziarie" />

            <PageContent>
                {/* Intro */}
                <CardBox>
                    <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-lg font-bold text-gray-900 dark:text-white">🔮 Cosa succederebbe se...?</h2>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Esplora scenari finanziari interattivi. Muovi i cursori e osserva come cambiano i risultati in tempo reale.
                                Tutti i calcoli sono stime e non costituiscono consulenza finanziaria.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2 text-xs text-gray-400 dark:text-gray-500 sm:text-right">
                            <span>S&amp;P 500 storico: ~{historicalData.sp500_avg_return}%/anno</span>
                            <span>·</span>
                            <span>Inflazione IT: ~{historicalData.avg_inflation_italy}%/anno</span>
                        </div>
                    </div>
                </CardBox>

                {/* Tabs */}
                <div className="overflow-x-auto">
                    <div className="flex min-w-max gap-1 rounded-2xl bg-gray-100 p-1.5 dark:bg-gray-800">
                        {TABS.map((tab) => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={clsx(
                                    'flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium whitespace-nowrap transition-all',
                                    activeTab === tab.id
                                        ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                                )}
                                aria-pressed={activeTab === tab.id}
                            >
                                <span aria-hidden="true">{tab.icon}</span>
                                {tab.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Contenuto tab */}
                {activeTab === 'compound' && (
                    <CompoundInterestSimulator presetScenarios={presetScenarios} />
                )}
                {activeTab === 'debt_vs_invest' && (
                    <DebtVsInvestmentSimulator />
                )}
                {activeTab === 'emergency' && (
                    <EmergencyFundSimulator />
                )}
                {activeTab === 'stress_test' && (
                    <StressTestSimulator crisisScenarios={crisisScenarios} />
                )}

                {/* Disclaimer */}
                <p className="text-center text-xs text-gray-400 dark:text-gray-600">
                    ⚠️ Le simulazioni sono a scopo educativo. Non costituiscono consulenza finanziaria. I rendimenti passati non garantiscono quelli futuri.
                </p>
            </PageContent>
        </AuthenticatedLayout>
    );
}
