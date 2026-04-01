import clsx from 'clsx';

interface ProBadgeProps {
    className?: string;
    size?: 'xs' | 'sm';
}

/**
 * Badge compatto "Pro" da mostrare accanto alle voci di menu o funzionalità riservate al piano Pro.
 */
export default function ProBadge({ className = '', size = 'xs' }: ProBadgeProps) {
    return (
        <span
            className={clsx(
                'inline-flex items-center rounded font-bold uppercase tracking-wide text-white',
                'bg-gradient-to-r from-amber-500 to-amber-600',
                size === 'xs' && 'px-1.5 py-0.5 text-[10px]',
                size === 'sm' && 'px-2 py-0.5 text-xs',
                className
            )}
        >
            Pro
        </span>
    );
}
