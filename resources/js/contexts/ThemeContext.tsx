import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import axios from 'axios';

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
    const [isDark, setIsDark] = useState(() => themeToIsDark(initialTheme));

    // Inertia aggiorna `auth.user` a ogni visita: riallinea il tema locale al server
    useEffect(() => {
        setIsDark(themeToIsDark(initialTheme));
    }, [initialTheme]);

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
