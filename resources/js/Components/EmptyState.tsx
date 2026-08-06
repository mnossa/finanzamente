import LinkButton from '@/Components/LinkButton';
import PlusIcon from '@/Components/Icons/PlusIcon';
import { ReactNode } from 'react';
import clsx from 'clsx';

interface EmptyStateProps {
    /** Icona o emoji da mostrare (es: "🏦", "💸", "📊") */
    icon: string | ReactNode;
    /** Titolo principale dello stato vuoto */
    title: string;
    /** Descrizione opzionale dello stato vuoto */
    description?: string;
    /** URL per creare il primo elemento */
    createUrl?: string;
    /** Testo del bottone di creazione */
    createLabel?: string;
    /** Classi CSS aggiuntive per personalizzare il componente */
    className?: string;
    /** Mostra il link di creazione anche se non ci sono dati */
    showCreateButton?: boolean;
    /** Contenuto personalizzato da mostrare al posto dei bottoni standard */
    children?: ReactNode;
}

/**
 * Componente riutilizzabile per mostrare uno stato vuoto quando non ci sono dati.
 * Utilizzato in tutte le pagine di indice per guidare l'utente a creare il primo record.
 */
export default function EmptyState({
    icon,
    title,
    description,
    createUrl,
    createLabel = 'Crea il primo',
    className,
    showCreateButton = true,
    children,
}: EmptyStateProps) {
    return (
        <div className={clsx(
            'flex flex-col items-center justify-center py-16 text-center',
            className
        )}>
            <div className="mb-4 text-6xl">
                {typeof icon === 'string' ? icon : icon}
            </div>
            <h3 className="mb-2 text-lg font-medium text-gray-900 dark:text-white">
                {title}
            </h3>
            {description && (
                <p className="mb-6 max-w-md text-slate-500">
                    {description}
                </p>
            )}
            {children ? (
                children
            ) : showCreateButton && createUrl ? (
                <LinkButton
                    href={createUrl}
                    icon={<PlusIcon />}
                >
                    {createLabel}
                </LinkButton>
            ) : null}
        </div>
    );
}
