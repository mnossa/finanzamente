import clsx from 'clsx';

interface ArchiveIconProps {
    className?: string;
    size?: number;
}

export default function ArchiveIcon({ className = '', size = 20 }: ArchiveIconProps) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width={size}
            height={size}
            viewBox="0 0 48 48"
            fill="none"
            stroke="currentColor"
            strokeWidth="3"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={clsx('flex-shrink-0', className)}
        >
            <path d="M42 12H6a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h36a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2z" />
            <path d="M6 20v18a4 4 0 0 0 4 4h28a4 4 0 0 0 4-4V20" />
            <path d="M18 28h12" />
        </svg>
    );
}
