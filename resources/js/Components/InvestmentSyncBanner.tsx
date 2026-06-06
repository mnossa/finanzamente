import { Link } from '@inertiajs/react';

interface InvestmentSyncBannerProps {
    count: number;
    className?: string;
}

export default function InvestmentSyncBanner({ count, className = '' }: InvestmentSyncBannerProps) {
    if (count <= 0) {
        return null;
    }

    return (
        <div className={`rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-100 ${className}`}>
            <p className="font-medium">
                {count === 1
                    ? '1 movimento investimento non è ancora collegato alle transazioni.'
                    : `${count} movimenti investimento non sono ancora collegati alle transazioni.`}
            </p>
            <p className="mt-1 text-amber-800 dark:text-amber-200/90">
                Collega conto e sincronizza per allineare saldo, 50/30/20 e patrimonio.
                Esegui da terminale: <code className="rounded bg-amber-100 px-1 dark:bg-amber-950">investment-pacs:sync-transactions</code>
                {' '}oppure{' '}
                <Link href={route('investments.index')} className="font-medium underline">
                    rivedi gli investimenti
                </Link>
                .
            </p>
        </div>
    );
}
