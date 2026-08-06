import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import type { PageProps } from '@/types';

const BALANCE_PRIVACY_STORAGE_KEY = 'fm-hide-balances';

function readStoredHideBalances(): boolean {
    try {
        return localStorage.getItem(BALANCE_PRIVACY_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function writeStoredHideBalances(hide: boolean): void {
    try {
        if (hide) {
            localStorage.setItem(BALANCE_PRIVACY_STORAGE_KEY, '1');
        } else {
            localStorage.removeItem(BALANCE_PRIVACY_STORAGE_KEY);
        }
    } catch {
        /* ignore */
    }
}

function clearStoredHideBalances(): void {
    try {
        localStorage.removeItem(BALANCE_PRIVACY_STORAGE_KEY);
    } catch {
        /* ignore */
    }
}

interface BalancePrivacyContextType {
    hideBalances: boolean;
    toggleHideBalances: () => void;
}

const BalancePrivacyContext = createContext<BalancePrivacyContextType>({
    hideBalances: false,
    toggleHideBalances: () => {},
});

export function BalancePrivacyProvider({
    initialHideBalances,
    children,
}: {
    initialHideBalances?: boolean;
    children: ReactNode;
}) {
    const [hideBalances, setHideBalances] = useState(() => {
        if (typeof initialHideBalances === 'boolean') {
            return initialHideBalances;
        }

        return readStoredHideBalances();
    });

    useEffect(() => {
        return router.on('success', (event) => {
            const props = event.detail.page.props as Partial<PageProps>;
            const user = props.auth?.user;
            if (!user) {
                setHideBalances(false);
                clearStoredHideBalances();
                document.documentElement.classList.remove('fm-hide-balances');

                return;
            }
            const pref =
                user.preferences && typeof user.preferences === 'object'
                    ? (user.preferences as Record<string, unknown>).hide_balances
                    : undefined;
            if (typeof pref === 'boolean') {
                setHideBalances(pref);
                writeStoredHideBalances(pref);
            }
        });
    }, []);

    useEffect(() => {
        if (hideBalances) {
            document.documentElement.classList.add('fm-hide-balances');
        } else {
            document.documentElement.classList.remove('fm-hide-balances');
        }
    }, [hideBalances]);

    const toggleHideBalances = () => {
        const next = !hideBalances;
        setHideBalances(next);
        writeStoredHideBalances(next);

        axios.patch(route('user.preferences.hide_balances'), { hide_balances: next }, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                Accept: 'application/json',
            },
            withCredentials: true,
        });
    };

    return (
        <BalancePrivacyContext.Provider value={{ hideBalances, toggleHideBalances }}>
            {children}
        </BalancePrivacyContext.Provider>
    );
}

export function useBalancePrivacy(): BalancePrivacyContextType {
    return useContext(BalancePrivacyContext);
}
