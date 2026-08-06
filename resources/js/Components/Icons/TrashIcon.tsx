import clsx from 'clsx';

interface TrashIconProps {
    className?: string;
    size?: number;
}

export default function TrashIcon({ className = '', size = 20 }: TrashIconProps) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width={size}
            height={size}
            viewBox="4 4 40 40"
            fill="none"
            stroke="currentColor"
            strokeWidth="3"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={clsx('flex-shrink-0', className)}
        >
            <path d="M8,14h32" />
            <path d="M14,14v24c0,2 2,4 4,4h12c2,0 4,-2 4,-4v-24" />
            <path d="M18,14v-4c0,-2 2,-4 4,-4h4c2,0 4,2 4,4v4" />
            <line x1="20" y1="20" x2="20" y2="34" />
            <line x1="28" y1="20" x2="28" y2="34" />
        </svg>
    );
}
