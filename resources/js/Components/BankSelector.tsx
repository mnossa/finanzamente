import clsx from 'clsx';

interface BankSelectorProps {
    bankNames: Record<string, string>;
    selectedBank: string;
    onSelect: (bank: string) => void;
    className?: string;
}

export default function BankSelector({ bankNames, selectedBank, onSelect, className }: BankSelectorProps) {
    const bankIcons: Record<string, string> = {
        intesa: '🏦',
        unicredit: '🏦',
        fineco: '💳',
        banco_bpm: '🏦',
        poste_pay: '📮',
        custom: '⚙️',
    };

    return (
        <div className={clsx('grid grid-cols-2 sm:grid-cols-3 gap-3', className)}>
            {Object.entries(bankNames).map(([key, name]) => (
                <button
                    key={key}
                    type="button"
                    onClick={() => onSelect(key)}
                    className={clsx(
                        'flex flex-col items-center justify-center gap-2 p-4 rounded-xl border-2 transition-all text-center',
                        'hover:border-blue-400 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500',
                        selectedBank === key
                            ? 'border-blue-500 bg-blue-50 shadow-sm'
                            : 'border-gray-200 bg-white',
                    )}
                    aria-pressed={selectedBank === key}
                    aria-label={`Seleziona ${name}`}
                >
                    <span className="text-2xl" aria-hidden="true">{bankIcons[key] ?? '🏦'}</span>
                    <span className={clsx(
                        'text-sm font-medium',
                        selectedBank === key ? 'text-blue-700' : 'text-gray-700',
                    )}>
                        {name}
                    </span>
                </button>
            ))}
        </div>
    );
}
