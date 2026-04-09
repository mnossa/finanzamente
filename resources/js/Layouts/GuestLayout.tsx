import ApplicationLogo from '@/Components/ApplicationLogo';
import { PropsWithChildren } from 'react';
import UmamiAnalytics from '@/Components/UmamiAnalytics';

export default function Guest({ children }: PropsWithChildren) {
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

                {/* Card Container */}
                <div className="w-full overflow-hidden bg-white px-8 py-8 shadow-soft sm:max-w-xl sm:rounded-2xl border border-slate-100">
                    {children}
                </div>

                {/* Footer */}
                <p className="mt-6 text-sm text-slate-500">
                    © {new Date().getFullYear()} Finanzamente. Gestisci le tue finanze con serenità.
                </p>
            </div>
        </>
    );
}
