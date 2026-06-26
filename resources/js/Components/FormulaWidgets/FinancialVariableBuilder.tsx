import FormulaStringInput, { type FormulaSuggestion } from '@/Components/FormulaWidgets/FormulaStringInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import {
    FINANCIAL_VARIABLE_SCENARIO_CATEGORIES,
    FINANCIAL_VARIABLE_SCENARIOS,
    type FinancialVariableScenarioCategory,
} from '@/utils/financialVariableScenarios';
import { systemVariableToFormulaSuggestion } from '@/utils/formulaVariableHints';
import clsx from 'clsx';
import { useMemo, useState } from 'react';
import type { FinancialVariableSummary, SystemVariableMeta } from '@/types/formulaWidget';

export interface FinancialVariableDraft {
    name: string;
    code: string;
    type: 'formula' | 'static';
    formula_string: string;
    static_value: string;
}

type BuilderTab = 'explore' | 'compose' | 'advanced';

type ComposerOperator = '+' | '-' | '*' | '/';

type ComposerToken =
    | { type: 'variable'; code: string; label: string }
    | { type: 'operator'; value: ComposerOperator };

interface FinancialVariableBuilderProps {
    draft: FinancialVariableDraft;
    onChange: (draft: FinancialVariableDraft) => void;
    systemVariables: SystemVariableMeta[];
    userVariables?: FinancialVariableSummary[];
    errors?: Record<string, string>;
    idPrefix?: string;
}

const DRAG_MIME = 'application/x-finanzamente-formula-var';

const OPERATORS: ComposerOperator[] = ['+', '-', '*', '/'];

function tokensToFormula(tokens: ComposerToken[]): string {
    return tokens
        .map((token) => (token.type === 'variable' ? `[${token.code}]` : ` ${token.value} `))
        .join('')
        .replace(/\s+/g, ' ')
        .trim();
}

function formulaToTokens(formula: string, suggestions: FormulaSuggestion[]): ComposerToken[] {
    const tokens: ComposerToken[] = [];
    const regex = /\[([a-z0-9_]+)\]|([+\-*/])/gi;
    let match: RegExpExecArray | null;

    while ((match = regex.exec(formula)) !== null) {
        if (match[1]) {
            const code = match[1];
            const suggestion = suggestions.find((item) => item.code === code);
            tokens.push({ type: 'variable', code, label: suggestion?.label ?? code });
        } else if (match[2]) {
            tokens.push({ type: 'operator', value: match[2] as ComposerOperator });
        }
    }

    return tokens;
}

function paletteGroups(systemVariables: SystemVariableMeta[]) {
    const financial = systemVariables.filter((variable) => variable.category !== 'context');
    const context = systemVariables.filter((variable) => variable.category === 'context');

    return [
        { id: 'financial', label: 'Finanziarie', variables: financial },
        { id: 'context', label: 'Contesto', variables: context },
    ];
}

export default function FinancialVariableBuilder({
    draft,
    onChange,
    systemVariables,
    userVariables = [],
    errors = {},
    idPrefix = 'var',
}: FinancialVariableBuilderProps) {
    const [tab, setTab] = useState<BuilderTab>('explore');
    const [activeCategory, setActiveCategory] = useState<FinancialVariableScenarioCategory>('bilancio_conto');
    const [composerTokens, setComposerTokens] = useState<ComposerToken[]>([]);
    const [composerSynced, setComposerSynced] = useState(false);

    const formulaSuggestions = useMemo<FormulaSuggestion[]>(
        () => [
            ...systemVariables.map(systemVariableToFormulaSuggestion),
            ...userVariables.map((variable) => ({
                code: variable.code,
                label: variable.name,
                hint: 'Tua variabile',
            })),
        ],
        [systemVariables, userVariables],
    );

    const palette = useMemo(() => paletteGroups(systemVariables), [systemVariables]);

    const syncComposerFromFormula = () => {
        if (composerSynced) {
            return;
        }

        setComposerTokens(formulaToTokens(draft.formula_string, formulaSuggestions));
        setComposerSynced(true);
    };

    const updateDraft = (patch: Partial<FinancialVariableDraft>) => {
        onChange({ ...draft, ...patch });
    };

    const applyScenario = (scenarioId: string) => {
        const scenario = FINANCIAL_VARIABLE_SCENARIOS.find((entry) => entry.id === scenarioId);
        if (!scenario) {
            return;
        }

        if (scenario.id === 'custom') {
            setTab('compose');
            syncComposerFromFormula();
            return;
        }

        updateDraft({
            type: 'formula',
            name: scenario.name,
            code: scenario.suggestedCode ?? draft.code,
            formula_string: scenario.formula,
        });
        setComposerTokens(formulaToTokens(scenario.formula, formulaSuggestions));
        setComposerSynced(true);
        setTab('compose');
    };

    const pushComposerToken = (token: ComposerToken) => {
        syncComposerFromFormula();
        const next = [...composerTokens, token];
        setComposerTokens(next);
        updateDraft({ type: 'formula', formula_string: tokensToFormula(next) });
    };

    const removeComposerToken = (index: number) => {
        const next = composerTokens.filter((_, tokenIndex) => tokenIndex !== index);
        setComposerTokens(next);
        updateDraft({ formula_string: tokensToFormula(next) });
    };

    const handlePaletteDragStart = (code: string, label: string) => (event: React.DragEvent) => {
        event.dataTransfer.setData(DRAG_MIME, JSON.stringify({ code, label }));
        event.dataTransfer.effectAllowed = 'copy';
    };

    const handleComposerDrop = (event: React.DragEvent) => {
        event.preventDefault();
        const raw = event.dataTransfer.getData(DRAG_MIME);
        if (!raw) {
            return;
        }

        try {
            const { code, label } = JSON.parse(raw) as { code: string; label: string };
            pushComposerToken({ type: 'variable', code, label });
        } catch {
            // ignore malformed drag payload
        }
    };

    const tabs: Array<{ id: BuilderTab; label: string }> = [
        { id: 'explore', label: 'Esplora scenari' },
        { id: 'compose', label: 'Componi' },
        { id: 'advanced', label: 'Formula avanzata' },
    ];

    return (
        <div className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor={`${idPrefix}-name`} value="Nome" />
                    <TextInput
                        id={`${idPrefix}-name`}
                        className="mt-1 block w-full"
                        value={draft.name}
                        onChange={(e) => updateDraft({ name: e.target.value })}
                        required
                    />
                    <InputError message={errors.name} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor={`${idPrefix}-code`} value="Codice (opzionale)" />
                    <TextInput
                        id={`${idPrefix}-code`}
                        className="mt-1 block w-full font-mono"
                        value={draft.code}
                        onChange={(e) => updateDraft({ code: e.target.value })}
                        placeholder="es. bilancio_conto"
                    />
                    <InputError message={errors.code} className="mt-1" />
                </div>
            </div>

            <div>
                <InputLabel htmlFor={`${idPrefix}-type`} value="Tipo" />
                <select
                    id={`${idPrefix}-type`}
                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-gray-600 dark:bg-gray-800"
                    value={draft.type}
                    onChange={(e) => updateDraft({ type: e.target.value as 'formula' | 'static' })}
                >
                    <option value="formula">Formula</option>
                    <option value="static">Valore statico</option>
                </select>
            </div>

            {draft.type === 'static' ? (
                <div>
                    <InputLabel htmlFor={`${idPrefix}-static`} value="Valore" />
                    <TextInput
                        id={`${idPrefix}-static`}
                        type="number"
                        step="0.01"
                        className="mt-1 block w-full"
                        value={draft.static_value}
                        onChange={(e) => updateDraft({ static_value: e.target.value })}
                        required
                    />
                    <InputError message={errors.static_value} className="mt-1" />
                </div>
            ) : (
                <>
                    <div className="flex flex-wrap gap-2 border-b border-gray-200 pb-2 dark:border-gray-700">
                        {tabs.map((entry) => (
                            <button
                                key={entry.id}
                                type="button"
                                onClick={() => {
                                    if (entry.id === 'compose') {
                                        syncComposerFromFormula();
                                    }
                                    setTab(entry.id);
                                }}
                                className={clsx(
                                    'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                                    tab === entry.id
                                        ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-100'
                                        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
                                )}
                            >
                                {entry.label}
                            </button>
                        ))}
                    </div>

                    {tab === 'explore' && (
                        <div className="space-y-4">
                            <div className="flex flex-wrap gap-2">
                                {(Object.keys(FINANCIAL_VARIABLE_SCENARIO_CATEGORIES) as FinancialVariableScenarioCategory[]).map(
                                    (category) => (
                                        <button
                                            key={category}
                                            type="button"
                                            onClick={() => setActiveCategory(category)}
                                            className={clsx(
                                                'rounded-full px-3 py-1 text-xs font-medium',
                                                activeCategory === category
                                                    ? 'bg-primary-600 text-white'
                                                    : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                            )}
                                        >
                                            {FINANCIAL_VARIABLE_SCENARIO_CATEGORIES[category].label}
                                        </button>
                                    ),
                                )}
                            </div>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                {FINANCIAL_VARIABLE_SCENARIO_CATEGORIES[activeCategory].description}
                            </p>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {FINANCIAL_VARIABLE_SCENARIOS.filter((scenario) => scenario.category === activeCategory).map(
                                    (scenario) => (
                                        <button
                                            key={scenario.id}
                                            type="button"
                                            onClick={() => applyScenario(scenario.id)}
                                            className="rounded-xl border border-gray-200 p-4 text-left transition hover:border-primary-300 hover:bg-primary-50/50 dark:border-gray-700 dark:hover:border-primary-700 dark:hover:bg-primary-950/20"
                                        >
                                            <p className="font-medium text-gray-900 dark:text-white">{scenario.name}</p>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{scenario.description}</p>
                                            {scenario.formula && (
                                                <p className="mt-2 font-mono text-xs text-primary-700 dark:text-primary-300">
                                                    {scenario.formula}
                                                </p>
                                            )}
                                        </button>
                                    ),
                                )}
                            </div>
                        </div>
                    )}

                    {tab === 'compose' && (
                        <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
                            <div className="space-y-3">
                                <p className="text-sm font-medium text-gray-900 dark:text-white">Palette variabili</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Trascina una variabile nell&apos;area formula oppure clicca per inserirla.
                                </p>
                                {palette.map((group) => (
                                    <div key={group.id}>
                                        <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            {group.label}
                                        </p>
                                        <div className="flex flex-wrap gap-1.5">
                                            {group.variables.map((variable) => (
                                                <button
                                                    key={variable.code}
                                                    type="button"
                                                    draggable
                                                    onDragStart={handlePaletteDragStart(variable.code, variable.label)}
                                                    onClick={() =>
                                                        pushComposerToken({
                                                            type: 'variable',
                                                            code: variable.code,
                                                            label: variable.label,
                                                        })
                                                    }
                                                    className="cursor-grab rounded-full border border-gray-200 bg-white px-2.5 py-1 font-mono text-xs text-gray-800 active:cursor-grabbing dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                                    title={variable.label}
                                                >
                                                    [{variable.code}]
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                                {userVariables.length > 0 && (
                                    <div>
                                        <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Tue variabili
                                        </p>
                                        <div className="flex flex-wrap gap-1.5">
                                            {userVariables.map((variable) => (
                                                <button
                                                    key={variable.id}
                                                    type="button"
                                                    draggable
                                                    onDragStart={handlePaletteDragStart(variable.code, variable.name)}
                                                    onClick={() =>
                                                        pushComposerToken({
                                                            type: 'variable',
                                                            code: variable.code,
                                                            label: variable.name,
                                                        })
                                                    }
                                                    className="cursor-grab rounded-full border border-primary-200 bg-primary-50 px-2.5 py-1 font-mono text-xs text-primary-900 active:cursor-grabbing dark:border-primary-800 dark:bg-primary-950/40 dark:text-primary-100"
                                                >
                                                    [{variable.code}]
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="space-y-3">
                                <p className="text-sm font-medium text-gray-900 dark:text-white">Formula</p>
                                <div
                                    onDragOver={(e) => e.preventDefault()}
                                    onDrop={handleComposerDrop}
                                    className="min-h-[7rem] rounded-xl border-2 border-dashed border-gray-300 bg-gray-50/80 p-3 dark:border-gray-600 dark:bg-gray-900/40"
                                >
                                    {composerTokens.length === 0 ? (
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            Trascina qui le variabili o usale dalla palette.
                                        </p>
                                    ) : (
                                        <div className="flex flex-wrap items-center gap-2">
                                            {composerTokens.map((token, index) => (
                                                <span key={`${token.type}-${index}`} className="inline-flex items-center gap-1">
                                                    {token.type === 'variable' ? (
                                                        <span className="inline-flex items-center gap-1 rounded-lg bg-white px-2 py-1 font-mono text-sm shadow-sm dark:bg-gray-800">
                                                            [{token.code}]
                                                            <button
                                                                type="button"
                                                                className="text-gray-400 hover:text-red-500"
                                                                onClick={() => removeComposerToken(index)}
                                                                aria-label={`Rimuovi ${token.code}`}
                                                            >
                                                                ×
                                                            </button>
                                                        </span>
                                                    ) : (
                                                        <span className="rounded-lg bg-gray-200 px-2 py-1 font-mono text-sm dark:bg-gray-700">
                                                            {token.value}
                                                        </span>
                                                    )}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    {OPERATORS.map((operator) => (
                                        <button
                                            key={operator}
                                            type="button"
                                            onClick={() => pushComposerToken({ type: 'operator', value: operator })}
                                            className="rounded-lg border border-gray-200 px-3 py-1.5 font-mono text-sm hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800"
                                        >
                                            {operator}
                                        </button>
                                    ))}
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setComposerTokens([]);
                                            updateDraft({ formula_string: '' });
                                        }}
                                        className="rounded-lg px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        Svuota
                                    </button>
                                </div>

                                <p className="font-mono text-xs text-gray-600 dark:text-gray-400">
                                    {draft.formula_string || '—'}
                                </p>
                            </div>
                        </div>
                    )}

                    {tab === 'advanced' && (
                        <div>
                            <InputLabel htmlFor={`${idPrefix}-formula`} value="Formula" />
                            <div className="mt-1">
                                <FormulaStringInput
                                    id={`${idPrefix}-formula`}
                                    value={draft.formula_string}
                                    onChange={(value) => {
                                        setComposerSynced(false);
                                        updateDraft({ formula_string: value });
                                    }}
                                    suggestions={formulaSuggestions}
                                    required
                                    placeholder="es. [period_income] - [period_expenses]"
                                />
                            </div>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Digita <span className="font-mono">[</span> per l&apos;autocomplete. Per il bilancio conto usa{' '}
                                <span className="font-mono">[period_net]</span> e attiva il filtro conto nel widget.
                            </p>
                        </div>
                    )}

                    <InputError message={errors.formula_string} className="mt-1" />
                </>
            )}
        </div>
    );
}
