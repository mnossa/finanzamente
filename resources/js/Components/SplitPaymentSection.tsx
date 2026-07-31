import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { formatCurrency } from '@/utils/format';
import clsx from 'clsx';
import { useMemo } from 'react';

export interface SplitLine {
    account_id: string;
    amount: string;
}

interface Account {
    id: number;
    name: string;
    currency_code: string;
}

interface Props {
    enabled: boolean;
    onToggle: (enabled: boolean) => void;
    accounts: Account[];
    splits: SplitLine[];
    onSplitsChange: (splits: SplitLine[]) => void;
    totalAmount: string;
    errors?: Record<string, string>;
}

const AMOUNT_TOLERANCE = 0.02;

function parseAmount(value: string): number {
    return parseFloat(value) || 0;
}

function roundMoney(value: number): number {
    return Math.round(value * 100) / 100;
}

export default function SplitPaymentSection({
    enabled,
    onToggle,
    accounts,
    splits,
    onSplitsChange,
    totalAmount,
    errors,
}: Props) {
    const total = useMemo(() => parseAmount(totalAmount), [totalAmount]);

    const splitSum = useMemo(
        () => roundMoney(splits.reduce((sum, line) => sum + parseAmount(line.amount), 0)),
        [splits],
    );

    const remainingOverall = useMemo(() => roundMoney(total - splitSum), [total, splitSum]);

    const isTotalReached = total > 0 && remainingOverall <= AMOUNT_TOLERANCE;

    const remainingForLine = (index: number): number => {
        const others = splits.reduce(
            (sum, line, i) => (i === index ? sum : sum + parseAmount(line.amount)),
            0,
        );

        return roundMoney(total - others);
    };

    const updateLine = (index: number, field: keyof SplitLine, value: string) => {
        const next = [...splits];
        next[index] = { ...next[index], [field]: value };
        onSplitsChange(next);
    };

    const fillRemaining = (index: number) => {
        const remaining = remainingForLine(index);
        if (remaining <= AMOUNT_TOLERANCE) {
            return;
        }
        updateLine(index, 'amount', remaining.toFixed(2));
    };

    const addLine = () => {
        if (isTotalReached) {
            return;
        }
        onSplitsChange([
            ...splits,
            {
                account_id: accounts[0] ? String(accounts[0].id) : '',
                amount: '',
            },
        ]);
    };

    const removeLine = (index: number) => {
        if (splits.length <= 2) {
            return;
        }
        onSplitsChange(splits.filter((_, i) => i !== index));
    };

    return (
        <div className="rounded-xl border border-gray-200 dark:border-gray-700">
            <button
                type="button"
                onClick={() => onToggle(!enabled)}
                className="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-medium text-gray-900 dark:text-gray-100"
            >
                <span>Pagamento su più conti</span>
                <span className="text-emerald-600 dark:text-emerald-400">{enabled ? '▼' : '▶'}</span>
            </button>
            {enabled && (
                <div className="space-y-3 border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        Es. parte in contanti e parte con carta. La somma delle righe deve essere uguale all&apos;importo totale.
                    </p>
                    {splits.map((line, index) => {
                        const lineRemaining = remainingForLine(index);
                        const showRemainingLink = index >= 1 && lineRemaining > AMOUNT_TOLERANCE;

                        return (
                            <div key={index} className="grid gap-2 sm:grid-cols-[1fr_120px_auto] sm:items-end">
                                <div>
                                    <InputLabel value={`Conto ${index + 1}`} />
                                    <select
                                        value={line.account_id}
                                        onChange={(e) => updateLine(index, 'account_id', e.target.value)}
                                        className="mt-1 block w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900"
                                    >
                                        {accounts.map((acc) => (
                                            <option key={acc.id} value={acc.id}>
                                                {acc.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <div className="flex items-center justify-between gap-2">
                                        <InputLabel value="Importo" />
                                        {showRemainingLink && (
                                            <button
                                                type="button"
                                                onClick={() => fillRemaining(index)}
                                                className="text-xs font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300"
                                            >
                                                Saldo rimanente
                                            </button>
                                        )}
                                    </div>
                                    <TextInput
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={line.amount}
                                        onChange={(e) => updateLine(index, 'amount', e.target.value)}
                                        className="mt-1 block w-full"
                                    />
                                </div>
                                <button
                                    type="button"
                                    onClick={() => removeLine(index)}
                                    disabled={splits.length <= 2}
                                    className={clsx(
                                        'rounded-lg px-2 py-2 text-sm',
                                        splits.length <= 2
                                            ? 'text-gray-300'
                                            : 'text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20',
                                    )}
                                >
                                    Rimuovi
                                </button>
                            </div>
                        );
                    })}
                    {isTotalReached ? (
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Totale raggiunto: non puoi aggiungere altri conti.
                        </p>
                    ) : (
                        <button
                            type="button"
                            onClick={addLine}
                            className="text-sm font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
                        >
                            + Aggiungi conto
                        </button>
                    )}
                    <p
                        className={clsx(
                            'text-sm',
                            Math.abs(splitSum - total) > AMOUNT_TOLERANCE ? 'text-amber-600' : 'text-gray-500',
                        )}
                    >
                        Totale righe: {formatCurrency(splitSum)} / {formatCurrency(total)}
                    </p>
                    <InputError message={errors?.splits} />
                </div>
            )}
        </div>
    );
}
