import ApplicationLogo from '@/Components/ApplicationLogo';
import { PropsWithChildren, ReactNode } from 'react';
import UmamiAnalytics from '@/Components/UmamiAnalytics';
import clsx from 'clsx';

interface GuestProps {
    sidebar?: ReactNode;
}

export default function Guest({ children, sidebar }: PropsWithChildren<GuestProps>) {
    return (
        <>
            <UmamiAnalytics />
            <div className="flex min-h-screen flex-col items-center bg-slate-50 pt-6 sm:justify-center sm:pt-0">
                {/* Logo Section */}
                <div className="mb-6">
                    <a href="/" className="flex items-center gap-3">
                        <ApplicationLogo className="w-12 h-12" />
                        <span className="text-2xl font-bold text-slate-800">Finanzamente</span>
                    </a>
                </div>

                {/* Card + Sidebar Container */}
                <div className={clsx(
                    sidebar
                        ? 'flex w-full max-w-3xl flex-col items-start gap-6 px-4 lg:flex-row'
                        : 'w-full sm:max-w-xl',
                )}>
                    {/* Card */}
                    <div className={clsx(
                        'overflow-hidden bg-white px-8 py-8 shadow-soft sm:rounded-2xl border border-slate-100',
                        sidebar ? 'w-full flex-1' : 'w-full',
                    )}>
                        {children}
                    </div>

                    {/* Sidebar (es. riepilogo ordine) */}
                    {sidebar && (
                        <div className="w-full lg:w-72 lg:flex-shrink-0">
                            {sidebar}
                        </div>
                    )}
                </div>

                {/* Footer */}
                <p className="mt-6 text-sm text-slate-500">
                    © {new Date().getFullYear()} Finanzamente. Gestisci le tue finanze con serenità.
                </p>
            </div>
        </>
    );
}
