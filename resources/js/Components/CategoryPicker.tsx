import { useState, useMemo } from 'react';
import clsx from 'clsx';

interface Category {
    id: number;
    name: string;
    type: 'income' | 'expense';
    color: string | null;
    icon: string | null;
}

interface CategoryPickerProps {
    categories: Category[];
    value: string;
    onChange: (categoryId: string) => void;
    error?: string;
    className?: string;
}

export default function CategoryPicker({
    categories,
    value,
    onChange,
    error,
    className,
}: CategoryPickerProps) {
    const [activeTab, setActiveTab] = useState<'expense' | 'income'>('expense');
    const [search, setSearch] = useState('');

    const incomeCategories = useMemo(
        () => categories.filter((c) => c.type === 'income'),
        [categories]
    );
    const expenseCategories = useMemo(
        () => categories.filter((c) => c.type === 'expense'),
        [categories]
    );

    const filteredCategories = useMemo(() => {
        const categoryList = activeTab === 'income' ? incomeCategories : expenseCategories;
        if (!search.trim()) return categoryList;
        const searchLower = search.toLowerCase();
        return categoryList.filter((c) => c.name.toLowerCase().includes(searchLower));
    }, [activeTab, incomeCategories, expenseCategories, search]);

    const selectedCategory = categories.find((c) => c.id === Number(value));

    // Sincronizza il tab attivo con la categoria selezionata
    const handleCategorySelect = (categoryId: string) => {
        onChange(categoryId);
    };

    // Se viene selezionata una categoria, cambia tab se necessario
    useMemo(() => {
        if (selectedCategory) {
            setActiveTab(selectedCategory.type);
        }
    }, [selectedCategory?.type]);

    return (
        <div className={clsx('space-y-3', className)}>
            {/* Categoria selezionata (preview) */}
            {selectedCategory && (
                <div
                    className={clsx(
                        'flex items-center justify-between rounded-lg border-2 p-3',
                        selectedCategory.type === 'income'
                            ? 'border-green-500 bg-green-50 dark:bg-green-900/20'
                            : 'border-red-500 bg-red-50 dark:bg-red-900/20'
                    )}
                >
                    <div className="flex items-center space-x-3">
                        <span className="text-2xl">{selectedCategory.icon || (selectedCategory.type === 'income' ? '💰' : '💸')}</span>
                        <div>
                            <p className="font-medium text-gray-900 dark:text-white">
                                {selectedCategory.name}
                            </p>
                            <p
                                className={clsx(
                                    'text-xs',
                                    selectedCategory.type === 'income'
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'
                                )}
                            >
                                {selectedCategory.type === 'income' ? 'Entrata' : 'Uscita'}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={() => onChange('')}
                        className="rounded-full p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                        title="Rimuovi selezione"
                    >
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            )}

            {/* Tabs */}
            <div className="flex rounded-lg bg-gray-100 p-1 dark:bg-gray-700">
                <button
                    type="button"
                    onClick={() => setActiveTab('expense')}
                    className={clsx(
                        'flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                        activeTab === 'expense'
                            ? 'bg-white text-red-600 shadow-sm dark:bg-gray-800 dark:text-red-400'
                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'
                    )}
                >
                    💸 Uscite
                    <span className="ml-1 text-xs text-gray-500 dark:text-gray-400">
                        ({expenseCategories.length})
                    </span>
                </button>
                <button
                    type="button"
                    onClick={() => setActiveTab('income')}
                    className={clsx(
                        'flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                        activeTab === 'income'
                            ? 'bg-white text-green-600 shadow-sm dark:bg-gray-800 dark:text-green-400'
                            : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200'
                    )}
                >
                    💰 Entrate
                    <span className="ml-1 text-xs text-gray-500 dark:text-gray-400">
                        ({incomeCategories.length})
                    </span>
                </button>
            </div>

            {/* Ricerca (mostra solo se ci sono più di 6 categorie nel tab attivo) */}
            {(activeTab === 'income' ? incomeCategories : expenseCategories).length > 6 && (
                <div className="relative">
                    <svg
                        className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                    <input
                        type="text"
                        placeholder="Cerca categoria..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                    />
                    {search && (
                        <button
                            type="button"
                            onClick={() => setSearch('')}
                            className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    )}
                </div>
            )}

            {/* Lista categorie con scroll */}
            <div className="max-h-48 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 sm:max-h-56">
                {filteredCategories.length === 0 ? (
                    <div className="p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                        {search
                            ? 'Nessuna categoria trovata'
                            : activeTab === 'income'
                              ? 'Nessuna categoria di entrata'
                              : 'Nessuna categoria di uscita'}
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-1 p-2 sm:grid-cols-3">
                        {filteredCategories.map((category) => (
                            <button
                                key={category.id}
                                type="button"
                                onClick={() => handleCategorySelect(String(category.id))}
                                className={clsx(
                                    'flex items-center space-x-2 rounded-lg border-2 p-2 text-left text-sm transition-all',
                                    value === String(category.id)
                                        ? activeTab === 'income'
                                            ? 'border-green-500 bg-green-50 ring-2 ring-green-500/20 dark:bg-green-900/20'
                                            : 'border-red-500 bg-red-50 ring-2 ring-red-500/20 dark:bg-red-900/20'
                                        : 'border-transparent bg-gray-50 hover:border-gray-300 hover:bg-gray-100 dark:bg-gray-800 dark:hover:border-gray-600 dark:hover:bg-gray-700'
                                )}
                            >
                                <span className="flex-shrink-0 text-base">
                                    {category.icon || (activeTab === 'income' ? '💰' : '💸')}
                                </span>
                                <span className="truncate text-gray-900 dark:text-white">
                                    {category.name}
                                </span>
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {/* Errore */}
            {error && (
                <p className="text-sm text-red-600 dark:text-red-400">{error}</p>
            )}
        </div>
    );
}
