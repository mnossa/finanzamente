import InputLabel from '@/Components/InputLabel';
import clsx from 'clsx';

interface ColumnMapping {
    date: number | null;
    amount: number | null;
    description: number | null;
    notes: number | null;
}

interface ColumnMapperProps {
    headers: string[];
    columnCount: number;
    mapping: ColumnMapping;
    onChange: (mapping: ColumnMapping) => void;
    className?: string;
}

export default function ColumnMapper({ headers, columnCount, mapping, onChange, className }: ColumnMapperProps) {
    const count = headers.length > 0 ? headers.length : columnCount;
    const options = Array.from({ length: count }, (_, i) => ({
        value: i,
        label: headers[i] ? `Col. ${i + 1}: ${headers[i]}` : `Colonna ${i + 1}`,
    }));

    const handleChange = (field: keyof ColumnMapping, value: string) => {
        onChange({
            ...mapping,
            [field]: value === '' ? null : Number(value),
        });
    };

    const fields: { key: keyof ColumnMapping; label: string; required: boolean }[] = [
        { key: 'date', label: 'Data *', required: true },
        { key: 'amount', label: 'Importo *', required: true },
        { key: 'description', label: 'Descrizione *', required: true },
        { key: 'notes', label: 'Note (opzionale)', required: false },
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
                        className="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                        aria-required={required}
                    >
                        {!required && <option value="">— Non mappare —</option>}
                        {required && <option value="">Seleziona colonna…</option>}
                        {options.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </div>
            ))}
        </div>
    );
}
