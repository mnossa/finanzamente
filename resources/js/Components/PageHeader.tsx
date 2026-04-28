import { ReactNode } from 'react';
import clsx from 'clsx';
import { Link } from '@inertiajs/react';

interface PageHeaderProps {
    /**
     * Titolo principale della pagina (può essere una stringa o un elemento React)
     */
    title: string | ReactNode;

    /**
     * Elemento o elementi da mostrare come azioni (es. bottoni)
     */
    actions?: ReactNode;

    /**
     * Sottotitolo o descrizione opzionale
     */
    subtitle?: string;

    /**
     * Classi CSS aggiuntive per personalizzazione
     */
    className?: string;

    /**
     * Link per tornare indietro (opzionale)
     */
    backLink?: string;
}

/**
 * Componente header generico per le pagine autenticate.
 * Mostra un titolo, opzionale sottotitolo e azioni (es. bottoni).
 * 
 * @example
 * ```tsx
 * <PageHeader 
 *   title="Trasferimenti" 
 *   actions={
 *     <LinkButton href={route('transfers.create')}>
 *       <PlusIcon className="mr-2 h-4 w-4" />
 *       Nuovo Trasferimento
 *     </LinkButton>
 *   }
 * />
 * ```
 */
export default function PageHeader({ title, actions, subtitle, className, backLink }: PageHeaderProps) {
    return (
        <div className={clsx('flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between', className)}>
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                    {backLink && (
                        <Link
                            href={backLink}
                            className="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                        >
                            ←
                        </Link>
                    )}
                    <h2 className="truncate text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {title}
                    </h2>
                </div>
                {subtitle && (
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {subtitle}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
