import CloudIcon from '@heroicons/react/24/outline/CloudIcon';
import DocumentTextIcon from '@heroicons/react/24/outline/DocumentTextIcon';
import clsx from 'clsx';

/** Valori salvati in `bank_import_layouts.icon` per indicare la fonte dati. */
export const LAYOUT_SOURCE_ICON_CSV = 'csv';
export const LAYOUT_SOURCE_ICON_GDRIVE = 'gdrive';

export default function ImportLayoutSourceIcon({
    icon,
    className,
    size = 'md',
}: {
    icon: string | null;
    className?: string;
    /** md: lista layout; lg: wizard salva layout */
    size?: 'sm' | 'md' | 'lg';
}) {
    const box = size === 'lg' ? 'h-10 w-10' : size === 'sm' ? 'h-5 w-5' : 'h-9 w-9';
    const inner = size === 'lg' ? 'h-6 w-6' : size === 'sm' ? 'h-4 w-4' : 'h-5 w-5';

    if (icon === LAYOUT_SOURCE_ICON_CSV) {
        return (
            <span
                className={clsx(
                    'inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-700',
                    box,
                    className,
                )}
                title="File locale (CSV / Excel)"
            >
                <DocumentTextIcon className={inner} aria-hidden />
            </span>
        );
    }

    if (icon === LAYOUT_SOURCE_ICON_GDRIVE) {
        return (
            <span
                className={clsx(
                    'inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white text-[#4285F4]',
                    box,
                    className,
                )}
                title="Google Drive"
            >
                <CloudIcon className={inner} aria-hidden />
            </span>
        );
    }

    return (
        <span className={clsx('text-2xl shrink-0 leading-none', className)} aria-hidden>
            {icon && icon.trim() !== '' ? icon : '📄'}
        </span>
    );
}

export const LAYOUT_SOURCE_OPTIONS = [
    {
        id: 'csv',
        label: 'File locale',
        hint: 'CSV o Excel sul dispositivo',
    },
    {
        id: 'gdrive',
        label: 'Google Drive',
        hint: 'Import da cloud',
    },
] as const;
