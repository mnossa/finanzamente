import ContentPanelShell from '@/Components/ContentPanelShell';
import { mobileListBodyClass } from '@/Components/IndexPageListToolbars';
import clsx from 'clsx';
import { ReactNode } from 'react';

interface IndexListCardProps {
    /** Titolo sezione, filtri o KPI sopra la lista */
    header?: ReactNode;
    kpi?: ReactNode;
    /** Barra strumenti (selezione, bulk…) */
    toolbar?: ReactNode;
    footer?: ReactNode;
    /** Contenuto quando la lista è vuota (tipicamente IndexEmptyList) */
    empty?: ReactNode;
    isEmpty?: boolean;
    children?: ReactNode;
    className?: string;
    bodyClassName?: string;
    /** `flush` = lista full-bleed su mobile (WFI-114 M2). */
    appearance?: 'default' | 'flush';
}

/**
 * Card lista standard per pagine indice: shell condiviso, corpo con inset lista.
 */
export default function IndexListCard({
    header,
    kpi,
    toolbar,
    footer,
    empty,
    isEmpty = false,
    children,
    className,
    bodyClassName,
    appearance = 'default',
}: IndexListCardProps): ReactNode {
    return (
        <ContentPanelShell
            variant={appearance === 'flush' ? 'indexFlush' : 'index'}
            className={clsx(appearance === 'default' && 'shadow-sm', className)}
            wrapBody={false}
        >
            {header}
            {kpi}
            {toolbar}
            {isEmpty && empty ? (
                empty
            ) : (
                <div className={clsx(mobileListBodyClass, bodyClassName)}>{children}</div>
            )}
            {!isEmpty && footer}
        </ContentPanelShell>
    );
}
