import { Children, ReactNode } from 'react';
import clsx from 'clsx';
import LinkButton from '@/Components/LinkButton';
import { isHrefCoveredByMobileFab, isMobileFabActionCovered } from '@/utils/mobilePrimaryFab';
import type { ComponentProps } from 'react';

/**
 * Raggruppa le azioni dell’header su desktop; `PageHeader` nasconde già tutto il blocco `actions` sotto `lg`.
 */
export function IndexPageHeaderActions({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}): ReactNode {
    return <div className={clsx('flex flex-wrap items-center gap-2', className)}>{children}</div>;
}

/**
 * Inset orizzontale comune per toolbar, card, filtri e liste su pagine indice (allineato a PageContent).
 */
export const indexPageInsetX = 'px-3 sm:px-4';

/** Filtri collapsible — summary e corpo. */
export const mobileFilterSummaryClass = `${indexPageInsetX} py-3`;
export const mobileFilterBodyClass = `${indexPageInsetX} pb-3 pt-2 sm:pb-4 sm:pt-3`;

/** Corpo pannello con padding standard (widget dashboard, KPI standalone). */
export const contentPanelBodyClass = 'p-3 sm:p-4';

/** Header pannello condiviso dashboard + index list. */
export const contentPanelHeaderClass = clsx(
    indexPageInsetX,
    'border-b border-gray-100 py-2.5 dark:border-gray-700 sm:py-3',
);

/** Corpo lista dentro pannello (widget dashboard). */
export const contentPanelListBodyClass = clsx(
    indexPageInsetX,
    'space-y-1.5 py-2.5 sm:py-3',
);

/** Empty state compatto dentro widget dashboard. */
export const contentPanelEmptyClass = 'py-5 text-center sm:py-6';

/** @deprecated Usare contentPanelHeaderClass */
export const mobileListHeaderClass = clsx(contentPanelHeaderClass, 'border-gray-200 dark:border-gray-700');

/** Barre strumenti dentro card (selezione multipla, titolo sezione). */
export const mobileListPanelClass = `${indexPageInsetX} py-2.5 sm:py-3`;

/** Corpo lista e offset righe (negative margin = bordo full-width nella card). */
export const mobileListBodyClass = `${indexPageInsetX} py-1 sm:py-2`;
export const mobileListRowInsetClass = '-mx-3 px-3 sm:-mx-4 sm:px-4';

/** Griglia KPI esterna (CardBox separati). */
export const mobileKpiGridGapClass = 'gap-2 sm:gap-3';

/** Pannello KPI 2×2 dentro una card (es. riepilogo transazioni). */
export const mobileKpiPanelClass = clsx(
    'grid grid-cols-2 gap-2 border-b border-gray-100 text-sm sm:grid-cols-4 sm:gap-3 dark:border-gray-700',
    indexPageInsetX,
    'py-3 sm:py-4',
);

/** Cella KPI interna al pannello. */
export const mobileKpiCellClass = 'rounded-lg px-2.5 py-2 sm:p-2';

/** Legenda/hint sotto i filtri (solo mobile). */
export const mobileLegendClass = clsx(
    indexPageInsetX,
    'py-1.5 text-[10px] leading-snug text-gray-500 sm:hidden dark:text-gray-400',
);

/** @deprecated Usare mobileKpiPanelClass o mobileKpiGridGapClass */
export const mobileKpiGridClass = mobileKpiPanelClass;

/**
 * LinkButton create: nascosto su mobile/tablet se il FAB floating punta allo stesso href.
 */
export function MobileCreateLinkButton(props: ComponentProps<typeof LinkButton>): ReactNode {
    const href = typeof props.href === 'string' ? props.href : '';
    if (href && isHrefCoveredByMobileFab(href)) {
        return null;
    }

    return <LinkButton {...props} />;
}

/**
 * Pulsante create inline: nascosto su mobile/tablet se il FAB floating esegue la stessa azione.
 */
export function MobileCreateActionButton({
    actionId,
    children,
    className,
    ...props
}: ComponentProps<'button'> & {
    actionId: string;
}): ReactNode {
    if (isMobileFabActionCovered(actionId)) {
        return null;
    }

    return (
        <button type="button" className={className} {...props}>
            {children}
        </button>
    );
}

function hasVisibleToolbarChildren(children: ReactNode): boolean {
    return Children.toArray(children).length > 0;
}

/**
 * CTA secondarie solo su viewport < lg, sopra il contenuto principale.
 * Default: una riga con larghezza uguale (Importa/Esporta affiancati).
 * Non usare `w-full` sui figli — il layout è gestito dal container.
 * Si nasconde se tutti i figli sono null (es. solo create coperto dal FAB).
 */
export function IndexPageMobileToolbar({
    children,
    className,
    equalWidth = true,
}: {
    children: ReactNode;
    className?: string;
    /** false = flex-wrap libero (CTA di lunghezze diverse) */
    equalWidth?: boolean;
}): ReactNode {
    if (!hasVisibleToolbarChildren(children)) {
        return null;
    }

    return (
        <div
            className={clsx(
                'mb-3 lg:hidden sm:mb-4',
                equalWidth
                    ? 'flex flex-row gap-2 [&>*]:min-w-0 [&>*]:flex-1 [&>*]:justify-center'
                    : 'flex flex-row flex-wrap gap-2',
                className,
            )}
        >
            {children}
        </div>
    );
}
