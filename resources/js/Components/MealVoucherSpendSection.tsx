import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import clsx from 'clsx';
import { useEffect, useMemo } from 'react';

export interface MealVoucherLotOption {
    id: number;
    unit_value: number;
    quantity_remaining: number;
    acquired_on: string;
    euro_value: number;
}

export interface MealVoucherLine {
    lot_id: number;
    quantity: number;
}

interface Props {
    lots: MealVoucherLotOption[];
    lines: MealVoucherLine[];
    onChange: (lines: MealVoucherLine[], euroAmount: number) => void;
    amount: string;
    onAmountChange: (amount: string) => void;
    currencyCode?: string;
    error?: string;
}

function formatCurrency(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency,
    }).format(amount);
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('it-IT');
}

function euroFromLines(lots: MealVoucherLotOption[], lines: MealVoucherLine[]): number {
    return round2(
        lines.reduce((sum, line) => {
            const lot = lots.find((l) => l.id === line.lot_id);
            if (!lot || line.quantity < 1) {
                return sum;
            }
            return sum + lot.unit_value * line.quantity;
        }, 0),
    );
}

function round2(n: number): number {
    return Math.round(n * 100) / 100;
}

function suggestFifo(lots: MealVoucherLotOption[], euroAmount: number): MealVoucherLine[] {
    let remaining = round2(euroAmount);
    const lines: MealVoucherLine[] = [];
    const sorted = [...lots].sort((a, b) => a.acquired_on.localeCompare(b.acquired_on) || a.id - b.id);

    for (const lot of sorted) {
        if (remaining <= 0) {
            break;
        }
        const maxByEuro = Math.floor(remaining / lot.unit_value + 1e-9);
        const qty = Math.min(lot.quantity_remaining, maxByEuro);
        if (qty < 1) {
            continue;
        }
        lines.push({ lot_id: lot.id, quantity: qty });
        remaining = round2(remaining - qty * lot.unit_value);
    }

    return lines;
}

export default function MealVoucherSpendSection({
    lots,
    lines,
    onChange,
    amount,
    onAmountChange,
    currencyCode = 'EUR',
    error,
}: Props) {
    const computedEuro = useMemo(() => euroFromLines(lots, lines), [lots, lines]);

    useEffect(() => {
        if (lots.length === 0) {
            return;
        }
        // Prima selezione: FIFO sul amount corrente se non ci sono linee
        if (lines.length === 0 && amount) {
            const euro = parseFloat(amount.replace(',', '.'));
            if (!Number.isNaN(euro) && euro > 0) {
                const suggested = suggestFifo(lots, euro);
                if (suggested.length > 0) {
                    onChange(suggested, euroFromLines(lots, suggested));
                }
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps -- solo mount / lots pronti
    }, [lots]);

    const setLineQty = (lotId: number, quantity: number) => {
        const next = lines.filter((l) => l.lot_id !== lotId);
        if (quantity > 0) {
            next.push({ lot_id: lotId, quantity });
        }
        next.sort((a, b) => a.lot_id - b.lot_id);
        const euro = euroFromLines(lots, next);
        onChange(next, euro);
        onAmountChange(euro > 0 ? String(euro) : '');
    };

    const applyAmountAsFifo = () => {
        const euro = parseFloat(amount.replace(',', '.'));
        if (Number.isNaN(euro) || euro <= 0) {
            return;
        }
        const suggested = suggestFifo(lots, euro);
        const computed = euroFromLines(lots, suggested);
        onChange(suggested, computed);
        if (Math.abs(computed - euro) > 0.001) {
            onAmountChange(String(computed));
        }
    };

    if (lots.length === 0) {
        return (
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                Nessun ticket disponibile su questo conto.
            </div>
        );
    }

    return (
        <div className="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-800 dark:bg-emerald-950/20">
            <div>
                <InputLabel value="Ticket da spendere" />
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    I buoni non sono frazionabili. Di default si usano i più vecchi (scadenza). Puoi modificare le quantità o
                    impostare l&apos;importo in euro e applicare FIFO.
                </p>
            </div>

            <div className="flex flex-wrap items-end gap-2">
                <div className="min-w-[8rem] flex-1">
                    <InputLabel htmlFor="meal_voucher_amount_helper" value="Importo (€) → FIFO" />
                    <TextInput
                        id="meal_voucher_amount_helper"
                        type="number"
                        step="0.01"
                        min="0.01"
                        className="mt-1 block w-full"
                        value={amount}
                        onChange={(e) => onAmountChange(e.target.value)}
                    />
                </div>
                <button
                    type="button"
                    onClick={applyAmountAsFifo}
                    className="rounded-lg border border-emerald-500 px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-100 dark:text-emerald-300 dark:hover:bg-emerald-900/40"
                >
                    Applica FIFO
                </button>
            </div>

            <ul className="space-y-2">
                {lots.map((lot) => {
                    const selected = lines.find((l) => l.lot_id === lot.id)?.quantity ?? 0;
                    return (
                        <li
                            key={lot.id}
                            className={clsx(
                                'flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-white p-3 dark:bg-gray-900',
                                selected > 0
                                    ? 'border-emerald-400 dark:border-emerald-600'
                                    : 'border-gray-200 dark:border-gray-700',
                            )}
                        >
                            <div>
                                <p className="text-sm font-medium text-gray-900 dark:text-white">
                                    {formatCurrency(lot.unit_value, currencyCode)} × {lot.quantity_remaining} disponibili
                                </p>
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Acquisiti il {formatDate(lot.acquired_on)}
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <InputLabel htmlFor={`lot-qty-${lot.id}`} value="N ticket" className="sr-only" />
                                <TextInput
                                    id={`lot-qty-${lot.id}`}
                                    type="number"
                                    min={0}
                                    max={lot.quantity_remaining}
                                    step={1}
                                    className="w-24"
                                    value={selected}
                                    onChange={(e) => {
                                        const qty = Math.max(0, Math.min(lot.quantity_remaining, parseInt(e.target.value || '0', 10) || 0));
                                        setLineQty(lot.id, qty);
                                    }}
                                />
                            </div>
                        </li>
                    );
                })}
            </ul>

            <p className="text-sm font-semibold text-gray-900 dark:text-white">
                Totale selezionato: {formatCurrency(computedEuro, currencyCode)}
            </p>
            <InputError message={error} />
        </div>
    );
}
