import clsx from 'clsx';
import { ReactNode } from 'react';
import { useBalancePrivacy } from '@/contexts/BalancePrivacyContext';

interface SensitiveAmountProps {
    children: ReactNode;
    className?: string;
    as?: 'span' | 'p' | 'div';
}

export default function SensitiveAmount({ children, className, as: Tag = 'span' }: SensitiveAmountProps) {
    const { hideBalances } = useBalancePrivacy();

    return (
        <Tag
            className={clsx(
                'inline-block transition-[filter] duration-200',
                hideBalances && 'blur-md select-none',
                className,
            )}
            aria-hidden={hideBalances}
        >
            {children}
        </Tag>
    );
}
