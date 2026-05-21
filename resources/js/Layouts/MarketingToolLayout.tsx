import ApplicationLogo from '@/Components/ApplicationLogo';
import UmamiAnalytics from '@/Components/UmamiAnalytics';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import clsx from 'clsx';
import { PropsWithChildren } from 'react';

const navLinkClass =
    'font-medium text-slate-600 hover:text-primary-600 dark:text-slate-300 dark:hover:text-primary-400';

const footerLinkClass = 'hover:text-primary-600 dark:hover:text-primary-400';

export default function MarketingToolLayout({ children, className }: PropsWithChildren<{ className?: string }>) {
    const { auth, privacy, marketing } = usePage<PageProps>().props;
    const canRegister = marketing?.can_register ?? false;

    return (
        <>
            <UmamiAnalytics enabled={privacy?.analytics_enabled ?? false} />
            <div className="min-h-screen bg-slate-50 dark:bg-gray-950">
                <header className="border-b border-slate-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                    <div className="container mx-auto flex items-center justify-between gap-4 px-4 py-4 sm:px-6">
                        <a href={route('home')} className="flex items-center gap-2">
                            <ApplicationLogo className="h-10 w-10" />
                            <span className="text-lg font-bold text-slate-800 dark:text-white">Finanzamente</span>
                        </a>
                        <nav className="flex items-center gap-3 text-sm">
                            <a href={route('home')} className={navLinkClass}>
                                Home
                            </a>
                            {auth.user ? (
                                <a href={route('dashboard')} className={navLinkClass}>
                                    Dashboard
                                </a>
                            ) : (
                                canRegister && (
                                    <a
                                        href={route('register')}
                                        className="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700"
                                    >
                                        Registrati
                                    </a>
                                )
                            )}
                            {auth.user ? (
                                <span className="hidden max-w-[10rem] truncate text-slate-600 dark:text-slate-300 sm:inline">
                                    {auth.user.name}
                                </span>
                            ) : (
                                route().has('login') && (
                                    <a href={route('login')} className={clsx(navLinkClass, 'hidden sm:inline')}>
                                        Accedi
                                    </a>
                                )
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
                        <a href={route('simulations.public')} className={footerLinkClass}>
                            Simulazioni
                        </a>
                        {' · '}
                        <a href={route('legal.privacy')} className={footerLinkClass}>
                            Privacy
                        </a>
                        {' · '}
                        <a href={route('legal.terms')} className={footerLinkClass}>
                            Termini
                        </a>
                    </p>
                </footer>
            </div>
        </>
    );
}
