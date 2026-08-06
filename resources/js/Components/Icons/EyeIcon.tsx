import clsx from 'clsx';

interface EyeIconProps {
    className?: string;
    size?: number;
}

export default function EyeIcon({ className = '', size = 20 }: EyeIconProps) {
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
            <path d="M24,12c-8,0 -15,7 -18,12c3,5 10,12 18,12c8,0 15,-7 18,-12c-3,-5 -10,-12 -18,-12z" />
            <circle cx="24" cy="24" r="5" />
        </svg>
    );
}
