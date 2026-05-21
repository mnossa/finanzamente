import ApplicationLogo from '@/Components/ApplicationLogo';
import UmamiAnalytics from '@/Components/UmamiAnalytics';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import clsx from 'clsx';
import { PropsWithChildren } from 'react';

export default function MarketingToolLayout({ children, className }: PropsWithChildren<{ className?: string }>) {
    const { privacy } = usePage<PageProps>().props;

    return (
        <>
            <UmamiAnalytics enabled={privacy?.analytics_enabled ?? false} />
            <div className="min-h-screen bg-slate-50 dark:bg-gray-950">
                <header className="border-b border-slate-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div className="container mx-auto flex items-center justify-between gap-4 px-4 py-4 sm:px-6">
                        <Link href="/" className="flex items-center gap-2">
                            <ApplicationLogo className="h-10 w-10" />
                            <span className="text-lg font-bold text-slate-800 dark:text-white">Finanzamente</span>
                        </Link>
                        <nav className="flex items-center gap-3 text-sm">
                            <Link
                                href="/"
                                className="font-medium text-slate-600 hover:text-primary-600 dark:text-slate-300 dark:hover:text-primary-400"
                            >
                                Home
                            </Link>
                            {route().has('register') && (
                                <Link
                                    href={route('register')}
                                    className="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700"
                                >
                                    Registrati
                                </Link>
                            )}
                            {route().has('login') && (
                                <Link
                                    href={route('login')}
                                    className="hidden font-medium text-slate-600 hover:text-primary-600 sm:inline dark:text-slate-300"
                                >
                                    Accedi
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                <main className={clsx('container mx-auto px-4 py-6 sm:px-6 sm:py-8', className)}>
                    {children}
                </main>

                <footer className="border-t border-slate-200 py-6 text-center text-sm text-slate-500 dark:border-gray-800 dark:text-slate-400">
                    <p>
                        © {new Date().getFullYear()} Finanzamente ·{' '}
                        <Link href={route('legal.privacy')} className="hover:text-primary-600">
                            Privacy
                        </Link>
                        {' · '}
                        <Link href={route('legal.terms')} className="hover:text-primary-600">
                            Termini
                        </Link>
                    </p>
                </footer>
            </div>
        </>
    );
}
