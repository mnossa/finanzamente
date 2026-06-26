import clsx from 'clsx';

export interface QuickChip {
    category_id: number;
    label: string;
    icon: string | null;
    color: string | null;
    type: 'income' | 'expense';
    account_id: number;
}

interface TransactionQuickChipsProps {
    chips: QuickChip[];
    selectedCategoryId?: string;
    onSelect: (chip: QuickChip) => void;
    className?: string;
}

export default function TransactionQuickChips({
    chips,
    selectedCategoryId,
    onSelect,
    className,
}: TransactionQuickChipsProps) {
    if (chips.length === 0) {
        return null;
    }

    return (
        <section
            className={clsx(
                'rounded-2xl border border-emerald-200/70 bg-linear-to-b from-emerald-50/80 to-white p-4 dark:border-emerald-800/60 dark:from-emerald-900/20 dark:to-gray-800',
                className,
            )}
            aria-label="Inserimento rapido"
        >
            <div className="mb-3 flex items-center gap-2">
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-base leading-none">
                    ⚡
                </span>
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Inserimento rapido
                    </h3>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        Tocca una categoria: importo e via.
                    </p>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {chips.map((chip) => {
                    const isSelected = selectedCategoryId === String(chip.category_id);
                    const accent = chip.color ?? undefined;

                    return (
                        <button
                            key={chip.category_id}
                            type="button"
                            data-testid={`quick-chip-${chip.category_id}`}
                            onClick={() => onSelect(chip)}
                            className={clsx(
                                'flex min-h-19 flex-col items-center justify-center gap-1.5 rounded-xl border bg-white px-2 py-3 text-center transition-all active:scale-[0.97] dark:bg-gray-800',
                                isSelected
                                    ? 'border-emerald-500 ring-2 ring-emerald-500/40 dark:border-emerald-400'
                                    : 'border-gray-200 hover:border-emerald-300 hover:shadow-sm dark:border-gray-600 dark:hover:border-emerald-700',
                            )}
                        >
                            <span
                                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg leading-none"
                                style={
                                    accent
                                        ? { backgroundColor: `${accent}22`, color: accent }
                                        : undefined
                                }
                            >
                                {chip.icon ?? (chip.type === 'income' ? '📥' : '📤')}
                            </span>
                            <span className="line-clamp-2 w-full text-xs font-medium leading-tight text-gray-800 dark:text-gray-100">
                                {chip.label}
                            </span>
                        </button>
                    );
                })}
            </div>
        </section>
    );
}
