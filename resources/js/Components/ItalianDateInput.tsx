import clsx from 'clsx';
import { useEffect, useId, useRef, useState } from 'react';
import { isoToItalianDate, italianDateToIso } from '@/utils/format';

interface ItalianDateInputProps {
    value: string;
    onChange: (isoDate: string) => void;
    className?: string;
    id?: string;
    'aria-label'?: string;
    disabled?: boolean;
    name?: string;
}

/**
 * Campo data con display italiano `dd/mm/yyyy` e valore ISO `YYYY-MM-DD` per il backend.
 * Include picker nativo come supporto (icona calendario).
 */
export default function ItalianDateInput({
    value,
    onChange,
    className = '',
    id,
    'aria-label': ariaLabel,
    disabled = false,
    name,
}: ItalianDateInputProps) {
    const autoId = useId();
    const inputId = id ?? autoId;
    const pickerRef = useRef<HTMLInputElement>(null);
    const [text, setText] = useState(() => isoToItalianDate(value));
    const [invalid, setInvalid] = useState(false);

    useEffect(() => {
        setText(isoToItalianDate(value));
        setInvalid(false);
    }, [value]);

    function commit(raw: string): void {
        const trimmed = raw.trim();
        if (trimmed === '') {
            setInvalid(false);
            setText('');
            if (value !== '') {
                onChange('');
            }
            return;
        }

        const iso = italianDateToIso(trimmed);
        if (iso === null) {
            setInvalid(true);
            setText(isoToItalianDate(value));
            return;
        }

        setInvalid(false);
        setText(isoToItalianDate(iso));
        if (iso !== value) {
            onChange(iso);
        }
    }

    return (
        <div className={clsx('relative', className)}>
            <input
                id={inputId}
                name={name}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                placeholder="gg/mm/aaaa"
                disabled={disabled}
                aria-label={ariaLabel}
                aria-invalid={invalid || undefined}
                value={text}
                onChange={(e) => {
                    setText(e.target.value);
                    setInvalid(false);
                }}
                onBlur={(e) => commit(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        commit((e.target as HTMLInputElement).value);
                    }
                }}
                className={clsx(
                    'w-full rounded-lg border bg-white py-2 pl-2.5 pr-10 text-sm text-gray-700 shadow-sm',
                    'focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500',
                    'dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300',
                    invalid
                        ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500'
                        : 'border-gray-200',
                )}
            />
            <input
                ref={pickerRef}
                type="date"
                tabIndex={-1}
                aria-hidden="true"
                disabled={disabled}
                value={value || ''}
                onChange={(e) => {
                    const next = e.target.value;
                    setInvalid(false);
                    setText(isoToItalianDate(next));
                    onChange(next);
                }}
                className="pointer-events-none absolute inset-y-0 right-0 w-9 opacity-0"
            />
            <button
                type="button"
                disabled={disabled}
                tabIndex={-1}
                aria-label={ariaLabel ? `Apri calendario: ${ariaLabel}` : 'Apri calendario'}
                className="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                onClick={() => {
                    const el = pickerRef.current;
                    if (!el) {
                        return;
                    }
                    if (typeof el.showPicker === 'function') {
                        el.showPicker();
                    } else {
                        el.focus();
                        el.click();
                    }
                }}
            >
                <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        fillRule="evenodd"
                        d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm-2 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zm1 3a1 1 0 100 2h3a1 1 0 100-2H5z"
                        clipRule="evenodd"
                    />
                </svg>
            </button>
        </div>
    );
}
