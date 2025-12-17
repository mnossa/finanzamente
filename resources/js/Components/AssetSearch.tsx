import { useState, useCallback, useRef, useEffect } from 'react';
import TextInput from '@/Components/TextInput';
import clsx from 'clsx';

interface SearchResult {
    symbol: string;
    name: string;
    type: string;
    region: string;
    currency: string;
    match_score: number;
}

interface AssetSearchProps {
    onSelect: (asset: SearchResult) => void;
    className?: string;
    disabled?: boolean;
}

export default function AssetSearch({ onSelect, className, disabled }: AssetSearchProps) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [apiConfigured, setApiConfigured] = useState<boolean | null>(null);
    
    const containerRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<NodeJS.Timeout>();

    // Verifica se l'API è configurata
    useEffect(() => {
        fetch('/api/assets/status')
            .then(res => res.json())
            .then(data => setApiConfigured(data.configured))
            .catch(() => setApiConfigured(false));
    }, []);

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

    const searchAssets = useCallback(async (searchQuery: string) => {
        if (searchQuery.length < 2) {
            setResults([]);
            setIsOpen(false);
            return;
        }

        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch(`/api/assets/search?q=${encodeURIComponent(searchQuery)}`);
            const data = await response.json();

            if (data.success) {
                setResults(data.results);
                setIsOpen(data.results.length > 0);
            } else {
                setError(data.error);
                setResults([]);
            }
        } catch {
            setError('Errore nella ricerca');
            setResults([]);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const handleInputChange = (value: string) => {
        setQuery(value);
        
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            searchAssets(value);
        }, 500);
    };

    const handleSelect = (asset: SearchResult) => {
        onSelect(asset);
        setQuery('');
        setResults([]);
        setIsOpen(false);
    };

    const getTypeIcon = (type: string) => {
        switch (type) {
            case 'stock': return '📈';
            case 'etf': return '📊';
            case 'crypto': return '₿';
            case 'index': return '📉';
            default: return '💼';
        }
    };

    if (apiConfigured === false) {
        return (
            <div className={clsx('rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20', className)}>
                <p className="text-sm text-amber-700 dark:text-amber-400">
                    ⚠️ Ricerca online non disponibile. Configura <code className="bg-amber-100 px-1 rounded dark:bg-amber-800">ALPHA_VANTAGE_API_KEY</code> nel file .env per abilitarla.
                </p>
            </div>
        );
    }

    return (
        <div ref={containerRef} className={clsx('relative', className)}>
            <div className="relative">
                <TextInput
                    type="text"
                    value={query}
                    onChange={(e) => handleInputChange(e.target.value)}
                    onFocus={() => results.length > 0 && setIsOpen(true)}
                    placeholder="Cerca asset online (es. Apple, AAPL, VOO...)"
                    className="w-full pr-10"
                    disabled={disabled || apiConfigured === null}
                />
                <div className="absolute right-3 top-1/2 -translate-y-1/2">
                    {isLoading ? (
                        <div className="h-5 w-5 animate-spin rounded-full border-2 border-emerald-500 border-t-transparent" />
                    ) : (
                        <span className="text-gray-400">🔍</span>
                    )}
                </div>
            </div>

            {error && (
                <p className="mt-1 text-sm text-red-500">{error}</p>
            )}

            {isOpen && results.length > 0 && (
                <div className="absolute z-50 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                    <ul className="max-h-64 overflow-y-auto">
                        {results.map((result, index) => (
                            <li key={`${result.symbol}-${index}`}>
                                <button
                                    type="button"
                                    onClick={() => handleSelect(result)}
                                    className="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50 dark:hover:bg-gray-700"
                                >
                                    <span className="text-xl">{getTypeIcon(result.type)}</span>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium text-gray-900 dark:text-white">
                                                {result.symbol}
                                            </span>
                                            <span className="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                {result.currency}
                                            </span>
                                            {result.region && (
                                                <span className="text-xs text-gray-500">
                                                    {result.region}
                                                </span>
                                            )}
                                        </div>
                                        <p className="truncate text-sm text-gray-500 dark:text-gray-400">
                                            {result.name}
                                        </p>
                                    </div>
                                    <span className="text-emerald-500">+</span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Cerca un asset per nome o simbolo per compilare automaticamente i campi
            </p>
        </div>
    );
}
