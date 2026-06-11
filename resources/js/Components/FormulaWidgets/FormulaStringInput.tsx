import { sanitizeFormulaString } from '@/utils/formulaWidgetForm';
import clsx from 'clsx';
import { useEffect, useMemo, useRef, useState } from 'react';

export interface FormulaSuggestion {
    code: string;
    label: string;
    hint?: string;
    example?: string;
}

interface FormulaStringInputProps {
    id: string;
    value: string;
    onChange: (value: string) => void;
    suggestions: FormulaSuggestion[];
    className?: string;
    required?: boolean;
    placeholder?: string;
}

interface ActiveToken {
    start: number;
    query: string;
}

function getActiveToken(value: string, cursor: number): ActiveToken | null {
    const before = value.slice(0, cursor);
    const openBracket = before.lastIndexOf('[');

    if (openBracket === -1) {
        return null;
    }

    const fragment = before.slice(openBracket + 1);

    if (fragment.includes(']')) {
        return null;
    }

    if (!/^[a-z0-9_]*$/i.test(fragment)) {
        return null;
    }

    return { start: openBracket + 1, query: fragment };
}

export default function FormulaStringInput({
    id,
    value,
    onChange,
    suggestions,
    className,
    required,
    placeholder,
}: FormulaStringInputProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLUListElement>(null);
    const [cursor, setCursor] = useState(value.length);
    const [highlightIndex, setHighlightIndex] = useState(0);
    const [isOpen, setIsOpen] = useState(false);

    const activeToken = getActiveToken(value, cursor);

    const filtered = useMemo(() => {
        if (!activeToken) {
            return [];
        }

        const query = activeToken.query.toLowerCase();

        return suggestions
            .filter((item) => {
                if (query === '') {
                    return true;
                }

                return (
                    item.code.toLowerCase().includes(query) ||
                    item.label.toLowerCase().includes(query) ||
                    (item.example?.toLowerCase().includes(query) ?? false)
                );
            })
            .slice(0, 10);
    }, [activeToken, suggestions]);

    useEffect(() => {
        setIsOpen(activeToken !== null && filtered.length > 0);
        setHighlightIndex(0);
    }, [activeToken?.query, activeToken?.start, filtered.length]);

    const applySuggestion = (code: string) => {
        if (!activeToken || !inputRef.current) {
            return;
        }

        const bracketStart = activeToken.start - 1;
        const nextValue = `${value.slice(0, bracketStart)}[${code}]${value.slice(cursor)}`;
        const nextCursor = bracketStart + code.length + 2;

        onChange(nextValue);
        setIsOpen(false);

        requestAnimationFrame(() => {
            inputRef.current?.focus();
            inputRef.current?.setSelectionRange(nextCursor, nextCursor);
            setCursor(nextCursor);
        });
    };

    const handleChange = (nextValue: string, selectionStart: number | null) => {
        const sanitized = sanitizeFormulaString(nextValue);
        onChange(sanitized);
        setCursor(selectionStart ?? sanitized.length);
    };

    const handleKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (!isOpen || filtered.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setHighlightIndex((current) => (current + 1) % filtered.length);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setHighlightIndex((current) => (current - 1 + filtered.length) % filtered.length);
        } else if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            applySuggestion(filtered[highlightIndex].code);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setIsOpen(false);
        }
    };

    return (
        <div className="relative">
            <input
                ref={inputRef}
                id={id}
                type="text"
                className={clsx(
                    'w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 font-mono text-slate-800 outline-none transition-all duration-200',
                    'placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:ring-2 focus:ring-emerald-200',
                    className,
                )}
                value={value}
                onChange={(e) => handleChange(e.target.value, e.target.selectionStart)}
                onClick={(e) => setCursor(e.currentTarget.selectionStart ?? value.length)}
                onKeyUp={(e) => setCursor(e.currentTarget.selectionStart ?? value.length)}
                onKeyDown={handleKeyDown}
                onBlur={() => {
                    window.setTimeout(() => setIsOpen(false), 120);
                }}
                onFocus={(e) => {
                    const position = e.currentTarget.selectionStart ?? value.length;
                    setCursor(position);
                    if (getActiveToken(value, position) !== null) {
                        setIsOpen(filtered.length > 0);
                    }
                }}
                required={required}
                placeholder={placeholder}
                autoComplete="off"
                spellCheck={false}
                role="combobox"
                aria-expanded={isOpen}
                aria-controls={`${id}-suggestions`}
                aria-autocomplete="list"
            />

            {isOpen && (
                <ul
                    id={`${id}-suggestions`}
                    ref={listRef}
                    role="listbox"
                    className="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-600 dark:bg-gray-900"
                >
                    {filtered.map((item, index) => (
                        <li key={item.code} role="option" aria-selected={index === highlightIndex}>
                            <button
                                type="button"
                                className={clsx(
                                    'flex w-full flex-col items-start px-3 py-2 text-left text-sm',
                                    index === highlightIndex
                                        ? 'bg-primary-50 text-primary-900 dark:bg-primary-900/30 dark:text-primary-100'
                                        : 'text-gray-800 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800',
                                )}
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => applySuggestion(item.code)}
                            >
                                <span className="font-mono font-medium">[{item.code}]</span>
                                <span className="text-xs text-gray-500 dark:text-gray-400">{item.label}</span>
                                {item.hint && (
                                    <span className="text-xs text-gray-400 dark:text-gray-500">{item.hint}</span>
                                )}
                                {item.example && (
                                    <span className="mt-0.5 font-mono text-xs text-primary-700 dark:text-primary-300">
                                        Es. {item.example}
                                    </span>
                                )}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
