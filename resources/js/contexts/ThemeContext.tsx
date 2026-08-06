import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import type { PageProps } from '@/types';

const THEME_STORAGE_KEY = 'fm-theme';

function readStoredTheme(): string | undefined {
    try {
        const v = localStorage.getItem(THEME_STORAGE_KEY);
        return v === 'dark' || v === 'light' ? v : undefined;
    } catch {
        return undefined;
    }
}

function writeStoredTheme(theme: 'dark' | 'light'): void {
    try {
        localStorage.setItem(THEME_STORAGE_KEY, theme);
    } catch {
        /* ignore quota / private mode */
    }
}

function clearStoredTheme(): void {
    try {
        localStorage.removeItem(THEME_STORAGE_KEY);
    } catch {
        /* ignore */
    }
}

interface ThemeContextType {
    isDark: boolean;
    toggleTheme: () => void;
}

const ThemeContext = createContext<ThemeContextType>({
    isDark: false,
    toggleTheme: () => {},
});

function themeToIsDark(theme: string | undefined): boolean {
    return theme === 'dark';
}

export function ThemeProvider({
    initialTheme,
    children,
}: {
    initialTheme?: string;
    children: ReactNode;
}) {
    const [isDark, setIsDark] = useState(() => {
        if (initialTheme === 'dark' || initialTheme === 'light') {
            return themeToIsDark(initialTheme);
        }
        return themeToIsDark(readStoredTheme());
    });

    useEffect(() => {
        return router.on('success', (event) => {
            const props = event.detail.page.props as Partial<PageProps>;
            const user = props.auth?.user;
            if (!user) {
                setIsDark(false);
                clearStoredTheme();
                return;
            }
            const theme =
                user.preferences && typeof user.preferences === 'object'
                    ? (user.preferences as Record<string, unknown>).theme
                    : undefined;
            if (theme === 'dark' || theme === 'light') {
                setIsDark(theme === 'dark');
                writeStoredTheme(theme);
            }
        });
    }, []);

    useEffect(() => {
        if (isDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }, [isDark]);

    const toggleTheme = () => {
        const newIsDark = !isDark;
        setIsDark(newIsDark);
        writeStoredTheme(newIsDark ? 'dark' : 'light');

        // Stesso endpoint definito in routes (it): user.preferences.theme
        axios.patch(route('user.preferences.theme'), { theme: newIsDark ? 'dark' : 'light' }, {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
                Accept: 'application/json',
            },
            withCredentials: true,
        });
    };

    return (
        <ThemeContext.Provider value={{ isDark, toggleTheme }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme(): ThemeContextType {
    return useContext(ThemeContext);
}
