import clsx from 'clsx';
import { useTheme } from '@/contexts/ThemeContext';

interface ThemeToggleProps {
    className?: string;
}

const SunIcon = () => (
    <svg
        xmlns="http://www.w3.org/2000/svg"
        width="20"
        height="20"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
    >
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
    </svg>
);

const MoonIcon = () => (
    <svg
        xmlns="http://www.w3.org/2000/svg"
        width="20"
        height="20"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden="true"
    >
        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
    </svg>
);

/**
 * Componente toggle per la modalità scura/chiara.
 * Utilizza ThemeContext per leggere e modificare il tema corrente.
 * Accessibile e compatibile con screen reader.
 */
export default function ThemeToggle({ className }: ThemeToggleProps) {
    const { isDark, toggleTheme } = useTheme();

    return (
        <button
            onClick={toggleTheme}
            className={clsx(
                'relative p-2 rounded-xl transition-colors',
                isDark
                    ? 'text-amber-400 hover:bg-slate-700'
                    : 'text-slate-500 hover:bg-slate-100',
                className,
            )}
            title={isDark ? 'Passa alla modalità chiara' : 'Passa alla modalità scura'}
            aria-label={isDark ? 'Attiva modalità chiara' : 'Attiva modalità scura'}
            aria-pressed={isDark}
            type="button"
        >
            {isDark ? <SunIcon /> : <MoonIcon />}
        </button>
    );
}
