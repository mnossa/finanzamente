import { ReactNode } from 'react';
import clsx from 'clsx';

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
export default function PageHeader({ title, actions, subtitle, className }: PageHeaderProps) {
    return (
        <div className={clsx('flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between', className)}>
            <div className="min-w-0 flex-1">
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {title}
                </h2>
                {subtitle && (
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {subtitle}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex flex-shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
