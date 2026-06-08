import SectionBadge from '@/Components/SectionBadge';
import SectionCard from '@/Components/SectionCard';
import clsx from 'clsx';
import { ReactNode } from 'react';

interface IndexIntroSectionProps {
    label: string;
    icon: ReactNode;
    description: string;
    /** Contenuto aggiuntivo sotto la descrizione (es. nota valutazione investimenti) */
    extra?: ReactNode;
    className?: string;
}

/**
 * Blocco introduttivo desktop-only per pagine indice (badge + descrizione).
 */
export default function IndexIntroSection({
    label,
    icon,
    description,
    extra,
    className,
}: IndexIntroSectionProps): ReactNode {
    return (
        <SectionCard
            className={clsx(
                'hidden bg-linear-to-br from-emerald-50 via-white to-teal-50 sm:block dark:from-emerald-950/20 dark:via-gray-900 dark:to-teal-950/20',
                className,
            )}
        >
            <div className="space-y-2">
                <SectionBadge label={label} icon={icon} />
                <p className="text-sm text-gray-600 dark:text-gray-300">{description}</p>
                {extra}
            </div>
        </SectionCard>
    );
}
