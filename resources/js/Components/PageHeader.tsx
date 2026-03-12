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
     * Sottotitolo o descrizione opzionale.
     * @deprecated Usare `description` per i nuovi componenti.
     */
    subtitle?: string;

    /**
     * Descrizione opzionale mostrata sotto il titolo (preferire questo rispetto a `subtitle`).
     */
    description?: string;

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
export default function PageHeader({ title, actions, subtitle, description, className, backLink }: PageHeaderProps) {
    const subtext = description ?? subtitle;
    return (
        <div className={clsx('flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between', className)}>
            <div className="min-w-0 flex-1 flex items-center justify-between gap-2">
                {backLink && (
                    <Link
                        href={backLink}
                        className="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                    >
                        ←
                    </Link>
                )}
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {title}
                    {subtext && (
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {subtext}
                    </p>
                )}
                </h2>
                
            </div>
            {actions && (
                <div className="flex flex-shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
