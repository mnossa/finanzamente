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
        <div className={className}>
            <p className="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                Categorie frequenti
            </p>
            <div className="flex gap-2 overflow-x-auto pb-1 [-webkit-overflow-scrolling:touch]">
                {chips.map((chip) => {
                    const isSelected = selectedCategoryId === String(chip.category_id);

                    return (
                        <button
                            key={chip.category_id}
                            type="button"
                            data-testid={`quick-chip-${chip.category_id}`}
                            onClick={() => onSelect(chip)}
                            className={clsx(
                                'inline-flex max-w-[9rem] shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition-colors',
                                isSelected
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200'
                                    : 'border-gray-200 bg-white text-gray-700 hover:border-emerald-300 hover:bg-emerald-50/50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-emerald-700',
                            )}
                        >
                            {chip.icon && <span className="shrink-0 text-base leading-none">{chip.icon}</span>}
                            <span className="truncate">{chip.label}</span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
