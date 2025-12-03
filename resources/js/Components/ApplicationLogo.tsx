import { SVGAttributes } from 'react';

export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 48 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >
            {/* Background rounded square */}
            <rect
                width="48"
                height="48"
                rx="12"
                className="fill-emerald-500"
            />
            {/* Wallet icon */}
            <path
                d="M34 20V16C34 14.9 33.1 14 32 14H16C14.9 14 14 14.9 14 16V32C14 33.1 14.9 34 16 34H32C33.1 34 34 33.1 34 32V28"
                stroke="white"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M36 20H28C26.9 20 26 20.9 26 22V26C26 27.1 26.9 28 28 28H36V20Z"
                stroke="white"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle
                cx="30"
                cy="24"
                r="1.5"
                fill="white"
            />
            {/* Decorative element - growth arrow */}
            <path
                d="M18 26L22 22L26 24"
                stroke="rgba(255,255,255,0.6)"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
