import clsx from 'clsx';

interface PencilIconProps {
    className?: string;
    size?: number;
}

export default function PencilIcon({ className = '', size = 20 }: PencilIconProps) {
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
            <path d="M22 6H8a4 4 0 0 0-4 4v28a4 4 0 0 0 4 4h28a4 4 0 0 0 4-4V24" />
            <path d="M37 3a4.24 4.24 0 0 1 6 6L24 28l-8 2 2-8 19-19z" />
        </svg>
    );
}
