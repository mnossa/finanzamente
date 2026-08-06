import InputLabel from '@/Components/InputLabel';
import clsx from 'clsx';

export interface InvestmentColumnMapping {
    buy_date: number | null;
    quantity: number | null;
    buy_price: number | null;
    ticker: number | null;
    isin: number | null;
    fees: number | null;
    notes: number | null;
}

interface InvestmentColumnMapperProps {
    headers: string[];
    columnCount: number;
    mapping: InvestmentColumnMapping;
    onChange: (mapping: InvestmentColumnMapping) => void;
    className?: string;
}

export default function InvestmentColumnMapper({
    headers,
    columnCount,
    mapping,
    onChange,
    className,
}: InvestmentColumnMapperProps) {
    const count = headers.length > 0 ? headers.length : columnCount;
    const options = Array.from({ length: count }, (_, i) => ({
        value: i,
        label: headers[i] ? `Col. ${i + 1}: ${headers[i]}` : `Colonna ${i + 1}`,
    }));

    const handleChange = (field: keyof InvestmentColumnMapping, value: string) => {
        onChange({
            ...mapping,
            [field]: value === '' ? null : Number(value),
        });
    };

    const fields: { key: keyof InvestmentColumnMapping; label: string; required: boolean }[] = [
        { key: 'buy_date',  label: 'Data acquisto *',      required: true },
        { key: 'quantity',  label: 'Quantità *',            required: true },
        { key: 'buy_price', label: 'Prezzo acquisto *',     required: true },
        { key: 'ticker',    label: 'Ticker (es. AAPL)',     required: false },
        { key: 'isin',      label: 'ISIN (es. US0378331005)', required: false },
        { key: 'fees',      label: 'Commissioni (opzionale)', required: false },
        { key: 'notes',     label: 'Note (opzionale)',      required: false },
    ];

    return (
        <div className={clsx('grid grid-cols-1 sm:grid-cols-2 gap-4', className)}>
            {fields.map(({ key, label, required }) => (
                <div key={key}>
                    <InputLabel htmlFor={`col_${key}`} value={label} />
                    <select
                        id={`col_${key}`}
                        value={mapping[key] ?? ''}
                        onChange={(e) => handleChange(key, e.target.value)}
                        required={required}
                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"
                        aria-required={required}
                    >
                        {!required && <option value="">— Non mappare —</option>}
                        {required && <option value="">Seleziona colonna…</option>}
                        {options.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>
            ))}
            {/* Nota ticker/isin */}
            <div className="sm:col-span-2">
                <p className="text-xs text-amber-600 dark:text-amber-400">
                    ⚠️ È necessario mappare almeno <strong>Ticker</strong> oppure <strong>ISIN</strong> per identificare l'asset.
                </p>
            </div>
        </div>
    );
}
