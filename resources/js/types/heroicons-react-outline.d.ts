/**
 * Tipizzazioni ambientali per `@heroicons/react/24/outline`.
 * Risolvono TS2307 quando TypeScript/l’IDE non applicano bene `package.json#exports` del pacchetto.
 */

declare module '@heroicons/react/24/outline' {
    import type * as React from 'react';

    type HeroOutlineIcon = React.ForwardRefExoticComponent<
        React.PropsWithoutRef<React.SVGProps<SVGSVGElement>> & {
            title?: string;
            titleId?: string;
        } & React.RefAttributes<SVGSVGElement>
    >;

    export const ArrowPathIcon: HeroOutlineIcon;
    export const BanknotesIcon: HeroOutlineIcon;
    export const ChartBarIcon: HeroOutlineIcon;
    export const CheckCircleIcon: HeroOutlineIcon;
    export const ClockIcon: HeroOutlineIcon;
    export const ExclamationTriangleIcon: HeroOutlineIcon;
    export const TagIcon: HeroOutlineIcon;
    export const UserGroupIcon: HeroOutlineIcon;
}

declare module '@heroicons/react/24/outline/*' {
    import type * as React from 'react';

    type HeroOutlineIcon = React.ForwardRefExoticComponent<
        React.PropsWithoutRef<React.SVGProps<SVGSVGElement>> & {
            title?: string;
            titleId?: string;
        } & React.RefAttributes<SVGSVGElement>
    >;

    const Icon: HeroOutlineIcon;
    export default Icon;
}
