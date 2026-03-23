import { useState, useCallback, useRef, useEffect } from 'react';
import axios from 'axios';
import clsx from 'clsx';

interface Tag {
    id: number;
    name: string;
    color: string | null;
}

interface TagAutocompleteProps {
    selectedTags: Tag[];
    onAdd: (tag: Tag) => void;
    onRemove: (tagName: string) => void;
    className?: string;
}

/**
 * Componente autocomplete per la selezione dei tag nelle transazioni.
 * Cerca i tag esistenti della household durante la digitazione e permette
 * di creare nuovi tag inline. I tag sono sempre in uppercase.
 */
export default function TagAutocomplete({ selectedTags, onAdd, onRemove, className }: TagAutocompleteProps) {
    const [inputValue, setInputValue] = useState('');
    const [suggestions, setSuggestions] = useState<Tag[]>([]);
    const [isOpen, setIsOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>();

    // Chiudi dropdown quando si clicca fuori
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const searchTags = useCallback(async (query: string) => {
        setIsLoading(true);
        try {
            const response = await axios.get<Tag[]>(route('tags.search'), { params: { q: query } });
            const data = response.data;
            // Escludi i tag già selezionati (confronta per nome, poiché i nuovi tag hanno id=0)
            const filtered = data.filter((t) => !selectedTags.some((s) => s.name === t.name));
            setSuggestions(filtered);
            setIsOpen(filtered.length > 0 || (query.trim().length > 0));
        } catch {
            setSuggestions([]);
        } finally {
            setIsLoading(false);
        }
    }, [selectedTags]);

    const handleInputChange = (value: string) => {
        setInputValue(value);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            searchTags(value);
        }, 200);
    };

    const handleSelectSuggestion = (tag: Tag) => {
        onAdd(tag);
        setInputValue('');
        setSuggestions([]);
        setIsOpen(false);
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const normalized = inputValue.trim().toUpperCase();
            if (!normalized) return;

            // Se c'è un suggerimento che corrisponde esattamente, usalo
            const exact = suggestions.find((s) => s.name === normalized);
            if (exact) {
                handleSelectSuggestion(exact);
                return;
            }

            // Altrimenti aggiungi come nuovo tag (creato lato backend al salvataggio)
            const existing = selectedTags.find((t) => t.name === normalized);
            if (!existing) {
                onAdd({ id: 0, name: normalized, color: null });
            }
            setInputValue('');
            setSuggestions([]);
            setIsOpen(false);
        } else if (e.key === 'Escape') {
            setIsOpen(false);
        }
    };

    const normalizedInput = inputValue.trim().toUpperCase();
    // Mostra opzione "crea nuovo tag" solo se non corrisponde a un suggerimento esistente
    const showCreateOption =
        normalizedInput.length > 0 &&
        !suggestions.some((s) => s.name === normalizedInput) &&
        !selectedTags.some((t) => t.name === normalizedInput);

    return (
        <div className={clsx('space-y-2', className)}>
            {/* Tag selezionati */}
            {selectedTags.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {selectedTags.map((tag) => (
                        <span
                            key={tag.id || tag.name}
                            className="inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium"
                            style={{
                                backgroundColor: tag.color ? `${tag.color}20` : '#e5e7eb',
                                color: tag.color || '#374151',
                                borderWidth: 1,
                                borderStyle: 'solid',
                                borderColor: tag.color || '#d1d5db',
                            }}
                        >
                            🏷️ {tag.name}
                            <button
                                type="button"
                                onClick={() => onRemove(tag.name)}
                                className="ml-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full hover:bg-black/10"
                                aria-label={`Rimuovi tag ${tag.name}`}
                            >
                                ×
                            </button>
                        </span>
                    ))}
                </div>
            )}

            {/* Input autocomplete */}
            <div ref={containerRef} className="relative">
                <input
                    type="text"
                    value={inputValue}
                    onChange={(e) => handleInputChange(e.target.value)}
                    onKeyDown={handleKeyDown}
                    onFocus={() => {
                        if (inputValue.trim()) {
                            searchTags(inputValue);
                        }
                    }}
                    placeholder="Digita per cercare o aggiungere un tag…"
                    className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:placeholder-gray-500"
                    autoComplete="off"
                />

                {/* Dropdown suggerimenti */}
                {isOpen && (
                    <div className="absolute z-20 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        <ul className="max-h-48 overflow-y-auto py-1 text-sm">
                            {isLoading && (
                                <li className="px-3 py-2 text-gray-400 dark:text-gray-500">
                                    Ricerca in corso…
                                </li>
                            )}
                            {!isLoading && suggestions.map((tag) => (
                                <li key={tag.id}>
                                    <button
                                        type="button"
                                        onClick={() => handleSelectSuggestion(tag)}
                                        className="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700"
                                    >
                                        <span
                                            className="inline-block h-2 w-2 rounded-full"
                                            style={{ backgroundColor: tag.color || '#6366f1' }}
                                        />
                                        {tag.name}
                                    </button>
                                </li>
                            ))}
                            {!isLoading && showCreateOption && (
                                <li>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            onAdd({ id: 0, name: normalizedInput, color: null });
                                            setInputValue('');
                                            setSuggestions([]);
                                            setIsOpen(false);
                                        }}
                                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-900/20"
                                    >
                                        <span>+</span>
                                        Crea tag &quot;{normalizedInput}&quot;
                                    </button>
                                </li>
                            )}
                            {!isLoading && suggestions.length === 0 && !showCreateOption && (
                                <li className="px-3 py-2 text-gray-400 dark:text-gray-500">
                                    Nessun tag trovato
                                </li>
                            )}
                        </ul>
                    </div>
                )}
            </div>

            <p className="text-xs text-gray-500 dark:text-gray-400">
                Digita il nome del tag e premi Invio, oppure seleziona un tag esistente. I tag vengono salvati in maiuscolo.
            </p>
        </div>
    );
}
