import { Link } from '@inertiajs/react';
import clsx from 'clsx';
import { useModules } from '@/hooks/useModules';

interface LockedModuleBadgeProps {
    moduleId: string;
    className?: string;
}

/**
 * Badge per indicare che un modulo è bloccato.
 * Mostra un'icona di lucchetto con tooltip informativo.
 */
export function LockedModuleBadge({ moduleId, className = '' }: LockedModuleBadgeProps) {
    const { getModule, getUnlockHint } = useModules();
    const module = getModule(moduleId);
    const hint = getUnlockHint(moduleId);

    if (!module || !module.locked) {
        return null;
    }

    return (
        <span
            className={clsx(
                'group relative inline-flex items-center gap-1 rounded-md bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
                className
            )}
            title={hint || undefined}
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            >
                <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
            <span>Bloccato</span>
            {hint && (
                <div className="pointer-events-none absolute bottom-full left-1/2 mb-2 hidden w-64 -translate-x-1/2 rounded-lg bg-slate-900 px-3 py-2 text-xs text-white shadow-lg group-hover:block">
                    <div className="absolute -bottom-1 left-1/2 h-2 w-2 -translate-x-1/2 rotate-45 bg-slate-900" />
                    {hint}
                </div>
            )}
        </span>
    );
}

interface LockedModuleCardProps {
    moduleId: string;
    showUnlockButton?: boolean;
    className?: string;
}

/**
 * Card per mostrare un modulo bloccato nella dashboard.
 * Include descrizione, hint di sblocco e azione per abilitare.
 */
export function LockedModuleCard({
    moduleId,
    showUnlockButton = true,
    className = '',
}: LockedModuleCardProps) {
    const { getModule, getUnlockHint } = useModules();
    const module = getModule(moduleId);
    const hint = getUnlockHint(moduleId);

    if (!module || !module.locked) {
        return null;
    }

    return (
        <div
            className={clsx(
                'relative overflow-hidden rounded-xl border-2 border-dashed border-amber-200 bg-amber-50/50 p-6 dark:border-amber-800 dark:bg-amber-900/10',
                className
            )}
        >
            {/* Icona lucchetto in background */}
            <div className="absolute right-4 top-4 opacity-10">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="48"
                    height="48"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="text-amber-600"
                >
                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
            </div>

            <div className="relative">
                <div className="mb-2 flex items-center gap-2">
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
                        className="text-amber-600 dark:text-amber-400"
                    >
                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
                        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                    </svg>
                    <h3 className="font-semibold text-amber-900 dark:text-amber-200">
                        {module.name}
                    </h3>
                </div>

                {hint && (
                    <p className="mb-4 text-sm text-amber-700 dark:text-amber-300">
                        {hint}
                    </p>
                )}

                {showUnlockButton && (
                    <Link
                        href={route('profile.quiz-settings.edit')}
                        className="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 dark:bg-amber-700 dark:hover:bg-amber-600"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        >
                            <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        Sblocca Modulo
                    </Link>
                )}
            </div>
        </div>
    );
}

interface ModuleAccessInfoProps {
    className?: string;
}

/**
 * Info box che mostra un riepilogo dei moduli bloccati
 * con link alle impostazioni di profilazione.
 */
export function ModuleAccessInfo({ className = '' }: ModuleAccessInfoProps) {
    const { getLockedModules } = useModules();
    const lockedModules = getLockedModules();

    if (lockedModules.length === 0) {
        return null;
    }

    return (
        <div
            className={clsx(
                'rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20',
                className
            )}
        >
            <div className="flex items-start gap-3">
                <div className="flex-shrink-0">
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
                        className="text-blue-600 dark:text-blue-400"
                    >
                        <circle cx="12" cy="12" r="10" />
                        <path d="M12 16v-4" />
                        <path d="M12 8h.01" />
                    </svg>
                </div>
                <div className="flex-1">
                    <p className="text-sm font-medium text-blue-900 dark:text-blue-200">
                        Hai {lockedModules.length} {lockedModules.length === 1 ? 'modulo bloccato' : 'moduli bloccati'}
                    </p>
                    <p className="mt-1 text-sm text-blue-700 dark:text-blue-300">
                        Personalizza le tue impostazioni di profilazione per sbloccare funzionalità
                        aggiuntive come investimenti e gestione fiscale.
                    </p>
                    <Link
                        href={route('profile.quiz-settings.edit')}
                        className="mt-2 inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                        Modifica Impostazioni
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        >
                            <path d="m9 18 6-6-6-6" />
                        </svg>
                    </Link>
                </div>
            </div>
        </div>
    );
}
