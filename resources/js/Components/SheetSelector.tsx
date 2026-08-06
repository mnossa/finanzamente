import clsx from 'clsx';

export interface SheetInfo {
    index: number;
    name: string;
}

interface SheetSelectorProps {
    sheets: SheetInfo[];
    selectedIndex: number;
    onChange: (index: number) => void;
    className?: string;
}

/**
 * Selettore del foglio (sheet) per file XLSX con più fogli.
 * Mostra una lista di chip selezionabili, uno per foglio.
 */
export default function SheetSelector({ sheets, selectedIndex, onChange, className }: SheetSelectorProps) {
    if (sheets.length <= 1) return null;

    return (
        <div className={clsx('space-y-2', className)}>
            <p className="text-sm font-medium text-gray-700 dark:text-gray-200">
                Foglio da importare
                <span className="ml-1 text-xs text-gray-500 font-normal">
                    (il file contiene {sheets.length} fogli)
                </span>
            </p>
            <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Seleziona foglio">
                {sheets.map((sheet) => (
                    <button
                        key={sheet.index}
                        type="button"
                        role="radio"
                        aria-checked={selectedIndex === sheet.index}
                        onClick={() => onChange(sheet.index)}
                        className={clsx(
                            'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm border transition',
                            'focus:outline-none focus:ring-2 focus:ring-blue-500',
                            selectedIndex === sheet.index
                                ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                                : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-600 hover:border-blue-400',
                        )}
                    >
                        <span aria-hidden="true">📋</span>
                        {sheet.name}
                    </button>
                ))}
            </div>
        </div>
    );
}
