import { useState, useRef, useEffect } from 'react';
import clsx from 'clsx';

interface EmojiGroup {
    label: string;
    icon: string;
    emojis: string[];
}

const EMOJI_GROUPS: EmojiGroup[] = [
    {
        label: 'Tutti',
        icon: '✨',
        emojis: [],
    },
    {
        label: 'Finanza',
        icon: '💰',
        emojis: [
            '💰', '💵', '💴', '💶', '💷', '💸', '💳', '🏦', '🏧', '💹',
            '📈', '📉', '📊', '🪙', '💎', '💼', '🤑', '🏷️', '🧾', '📑',
        ],
    },
    {
        label: 'Casa',
        icon: '🏠',
        emojis: [
            '🏠', '🏡', '🏢', '🏗️', '🔑', '🪴', '🛋️', '🛏️', '🚪', '🪟',
            '🔒', '💡', '🔌', '💻', '📱', '📺', '🧹', '🧺', '🧻', '🪣',
        ],
    },
    {
        label: 'Trasporti',
        icon: '🚗',
        emojis: [
            '🚗', '🚙', '🚕', '🏎️', '🚌', '🚎', '🚆', '🚇', '✈️', '⛽',
            '🛵', '🚲', '🛺', '🚢', '🚁', '🛤️', '🅿️', '🚦', '🛣️', '🗺️',
        ],
    },
    {
        label: 'Cibo',
        icon: '🍕',
        emojis: [
            '🍕', '🍔', '🍟', '🌮', '🌯', '🥗', '🍱', '🍣', '🍜', '🍝',
            '🥘', '🫕', '🍲', '🍛', '🥙', '🧆', '🥚', '🍳', '🥞', '🧇',
            '🥐', '🥖', '🧀', '🥩', '🥓', '🌭', '🍿', '🧂', '☕', '🍵',
            '🧃', '🥤', '🍺', '🍷', '🥂', '🛒', '🛍️', '🧺', '🍫', '🍩',
        ],
    },
    {
        label: 'Salute',
        icon: '❤️',
        emojis: [
            '❤️', '🏥', '💊', '💉', '🩺', '🩹', '🧬', '🦷', '👁️', '🧠',
            '💪', '🏋️', '🧘', '🏊', '🚴', '⚽', '🥊', '🎽', '🧴', '🧼',
        ],
    },
    {
        label: 'Intrattenimento',
        icon: '🎮',
        emojis: [
            '🎮', '🎬', '🎵', '🎸', '🎭', '🎪', '🎠', '🎢', '🎡', '🎯',
            '🎲', '♟️', '🎼', '🎻', '🎺', '🥁', '📚', '📖', '🎨', '🖼️',
            '✏️', '📷', '🎥', '🎤', '🎧', '🎫', '🎟️', '🏆', '🥇', '🎁',
        ],
    },
    {
        label: 'Istruzione',
        icon: '🎓',
        emojis: [
            '🎓', '🏫', '📚', '📖', '✏️', '📝', '🖊️', '📐', '📏', '🔬',
            '🔭', '🧪', '💡', '🖥️', '⌨️', '🖱️', '📡', '🧮', '📋', '🗂️',
        ],
    },
    {
        label: 'Shopping',
        icon: '🛍️',
        emojis: [
            '🛍️', '👕', '👗', '👠', '👟', '👒', '🧣', '🧤', '🧥', '👜',
            '👛', '🎒', '💍', '💄', '🕶️', '⌚', '📿', '🪞', '🪒', '💅',
        ],
    },
    {
        label: 'Lavoro',
        icon: '💼',
        emojis: [
            '💼', '🖥️', '⌨️', '🖨️', '📠', '☎️', '📞', '📧', '📨', '📩',
            '📤', '📥', '📦', '📫', '🗃️', '🗄️', '📊', '📈', '📉', '🗓️',
        ],
    },
    {
        label: 'Natura',
        icon: '🌿',
        emojis: [
            '🌿', '🌱', '🌳', '🌲', '🍀', '🌻', '🌹', '🌺', '🌸', '🌼',
            '🍃', '🌾', '☀️', '🌙', '⭐', '🌈', '❄️', '🌊', '🔥', '💧',
        ],
    },
    {
        label: 'Famiglia',
        icon: '👨‍👩‍👧',
        emojis: [
            '👨‍👩‍👧', '👶', '🧒', '👦', '👧', '🧑', '👱', '🧔', '👩', '👨',
            '🧓', '👴', '👵', '🐕', '🐈', '🐾', '🏠', '❤️', '🎂', '🎉',
        ],
    },
    {
        label: 'Viaggi',
        icon: '✈️',
        emojis: [
            '✈️', '🧳', '🗺️', '🏖️', '🏝️', '⛺', '🏔️', '🌍', '🌎', '🌏',
            '🗼', '🏰', '⛩️', '🗽', '🎡', '🎢', '🎠', '🚢', '🚆', '🚁',
        ],
    },
    {
        label: 'Altro',
        icon: '📦',
        emojis: [
            '📦', '🎁', '🎀', '🏅', '🏆', '🥇', '⭐', '💫', '✨', '🌟',
            '🔔', '🔕', '📢', '📣', '🔧', '🔨', '⚙️', '🧰', '🪛', '🔩',
            '🎯', '🧲', '🔑', '🗝️', '🔐', '🔓', '🔒', '📌', '📍', '🏷️',
        ],
    },
];

// Flatten all emojis for "Tutti" group (excluding the first item itself)
const ALL_EMOJIS = EMOJI_GROUPS.slice(1).flatMap((g) => g.emojis);
EMOJI_GROUPS[0].emojis = ALL_EMOJIS;

interface EmojiPickerProps {
    value: string;
    onChange: (emoji: string) => void;
    className?: string;
}

export default function EmojiPicker({ value, onChange, className }: EmojiPickerProps) {
    const [open, setOpen] = useState(false);
    const [activeGroup, setActiveGroup] = useState(0);
    const [search, setSearch] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    // Close on outside click
    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        }
        if (open) {
            document.addEventListener('mousedown', handleClickOutside);
        }
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [open]);

    const displayedEmojis = search.trim()
        ? ALL_EMOJIS.filter((e) => e.includes(search.trim()))
        : EMOJI_GROUPS[activeGroup].emojis;

    const handleSelect = (emoji: string) => {
        onChange(emoji);
        setOpen(false);
        setSearch('');
    };

    return (
        <div ref={containerRef} className={clsx('relative', className)}>
            {/* Trigger button */}
            <button
                type="button"
                onClick={() => setOpen((prev) => !prev)}
                className={clsx(
                    'flex h-10 w-full items-center gap-2 rounded-md border border-gray-300 bg-white px-3 shadow-sm transition',
                    'hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300',
                    'dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:hover:border-indigo-400',
                    open && 'border-indigo-500 ring-2 ring-indigo-300 dark:border-indigo-400'
                )}
                aria-haspopup="true"
                aria-expanded={open}
            >
                <span className="text-xl">{value || '📁'}</span>
                <span className="flex-1 text-left text-sm text-gray-500 dark:text-gray-400">
                    {value ? 'Cambia emoji' : 'Seleziona emoji'}
                </span>
                <svg
                    className={clsx('h-4 w-4 text-gray-400 transition-transform', open && 'rotate-180')}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={2}
                >
                    <path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {/* Dropdown panel */}
            {open && (
                <div
                    className={clsx(
                        'absolute z-50 mt-1 w-full min-w-[320px] rounded-xl border border-gray-200 bg-white shadow-xl',
                        'dark:border-gray-700 dark:bg-gray-800',
                        'left-0'
                    )}
                    role="dialog"
                    aria-label="Seleziona emoji"
                >
                    {/* Search */}
                    <div className="border-b border-gray-100 p-3 dark:border-gray-700">
                        <div className="relative">
                            <span className="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                            <input
                                type="text"
                                placeholder="Cerca emoji..."
                                value={search}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                    setActiveGroup(0);
                                }}
                                className={clsx(
                                    'block w-full rounded-lg border border-gray-200 py-1.5 pl-8 pr-3 text-sm',
                                    'focus:border-indigo-400 focus:outline-none focus:ring-1 focus:ring-indigo-300',
                                    'dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400'
                                )}
                                autoFocus
                            />
                        </div>
                    </div>

                    {/* Category tabs */}
                    {!search && (
                        <div className="flex gap-0.5 overflow-x-auto border-b border-gray-100 px-2 py-1.5 dark:border-gray-700">
                            {EMOJI_GROUPS.map((group, index) => (
                                <button
                                    key={group.label}
                                    type="button"
                                    onClick={() => setActiveGroup(index)}
                                    title={group.label}
                                    className={clsx(
                                        'flex-shrink-0 rounded-md px-2 py-1 text-sm transition',
                                        activeGroup === index
                                            ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
                                            : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
                                    )}
                                >
                                    {group.icon}
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Group label */}
                    <div className="px-3 pt-2 text-xs font-semibold text-gray-400 dark:text-gray-500">
                        {search ? `Risultati per "${search}"` : EMOJI_GROUPS[activeGroup].label}
                    </div>

                    {/* Emoji grid */}
                    <div className="max-h-52 overflow-y-auto p-2">
                        {displayedEmojis.length > 0 ? (
                            <div className="grid grid-cols-8 gap-0.5">
                                {displayedEmojis.map((emoji, i) => (
                                    <button
                                        key={`${emoji}-${i}`}
                                        type="button"
                                        onClick={() => handleSelect(emoji)}
                                        title={emoji}
                                        className={clsx(
                                            'flex items-center justify-center rounded-lg p-1.5 text-xl transition',
                                            'hover:bg-indigo-50 dark:hover:bg-indigo-900/30',
                                            value === emoji && 'bg-indigo-100 ring-1 ring-indigo-400 dark:bg-indigo-900/40'
                                        )}
                                    >
                                        {emoji}
                                    </button>
                                ))}
                            </div>
                        ) : (
                            <p className="py-4 text-center text-sm text-gray-400 dark:text-gray-500">
                                Nessuna emoji trovata
                            </p>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
