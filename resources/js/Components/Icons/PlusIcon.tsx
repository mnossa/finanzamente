import clsx from 'clsx';

interface PlusIconProps {
    className?: string;
    size?: number;
}

export default function PlusIcon({ className = '', size = 20 }: PlusIconProps) {
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
            <path d="M35.4,38.8c-3.2,2.4 -7.1,3.9 -11.4,3.9c-10.3,0 -18.7,-8.4 -18.7,-18.7c0,-2.6 0.6,-5.2 1.5,-7.4" />
            <path d="M12.1,9.6c3.2,-2.6 7.4,-4.3 11.9,-4.3c10.3,0 18.7,8.4 18.7,18.7c0,2.3 -0.4,4.5 -1.2,6.6" />
            <path d="M24,14v20" />
            <path d="M34,24h-20" />
        </svg>
    );
}
