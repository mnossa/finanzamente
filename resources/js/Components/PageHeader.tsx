import { ReactNode } from 'react';
import clsx from 'clsx';
import { Link } from '@inertiajs/react';

interface PageHeaderProps {
    /**
     * Titolo principale della pagina (può essere una stringa o un elemento React)
     */
    title: string | ReactNode;

    /**
     * Titolo breve su viewport &lt; lg (es. "PAC" al posto di "PAC — Piani di accumulo")
     */
    mobileTitle?: string;

    /**
     * Elemento o elementi da mostrare come azioni (es. bottoni)
     */
    actions?: ReactNode;

    /**
     * Sottotitolo o descrizione opzionale
     */
    subtitle?: string;

    /**
     * Nasconde il sottotitolo sotto `sm` (header più compatto su mobile)
     */
    hideSubtitleOnMobile?: boolean;

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
 * Le `actions` sono visibili solo da `lg` in su (su mobile/tablet: FAB floating + toolbar nel contenuto, non header).
 * Per CTA secondarie solo mobile usare `IndexPageMobileToolbar` (vedi `IndexPageListToolbars.tsx`).
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
export default function PageHeader({ title, mobileTitle, actions, subtitle, hideSubtitleOnMobile = false, className, backLink }: PageHeaderProps) {
    return (
        <div
            className={clsx(
                'flex min-w-0 flex-1 flex-col gap-2 lg:flex-row lg:items-center lg:gap-3',
                className,
            )}
        >
            <div className="flex min-w-0 items-center gap-2 lg:min-w-0 lg:flex-1">
                {backLink && (
                    <Link
                        href={backLink}
                        className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-gray-200 text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                    >
                        ←
                    </Link>
                )}
                <div className="min-w-0 flex-1">
                    <h2 className="truncate text-base font-semibold leading-tight text-gray-800 dark:text-gray-200 sm:text-xl">
                        {mobileTitle ? (
                            <>
                                <span className="lg:hidden">{mobileTitle}</span>
                                <span className="hidden lg:inline">{title}</span>
                            </>
                        ) : (
                            title
                        )}
                    </h2>
                    {subtitle && (
                        <p
                            className={clsx(
                                'mt-0.5 truncate text-xs text-gray-600 dark:text-gray-400 sm:text-sm',
                                hideSubtitleOnMobile && 'hidden sm:block',
                            )}
                        >
                            {subtitle}
                        </p>
                    )}
                </div>
            </div>
            {actions && (
                <div className="hidden min-w-0 shrink-0 flex-wrap items-center gap-2 lg:ml-auto lg:flex">
                    {actions}
                </div>
            )}
        </div>
    );
}
