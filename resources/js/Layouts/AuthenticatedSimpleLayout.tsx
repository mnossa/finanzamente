import ApplicationLogo from '@/Components/ApplicationLogo';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { PropsWithChildren, FormEvent } from 'react';

/**
 * Layout semplificato per utenti autenticati senza sidebar.
 * Utilizzato per pagine come la selezione/creazione household
 * dove l'utente non ha ancora una household attiva.
 */
export default function AuthenticatedSimpleLayout({
    children,
}: PropsWithChildren) {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    const handleLogout = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const form = e.currentTarget as HTMLFormElement;
        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        }).then(() => {
            window.location.href = '/';
        });
    };

    return (
        <div className="flex min-h-screen flex-col bg-slate-50">
            {/* Header */}
            <header className="border-b border-slate-200 bg-white shadow-sm">
                <div className="mx-auto flex h-16 max-w-4xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <a
                        href="/"
                        className="flex items-center gap-3 font-bold text-xl text-slate-900"
                    >
                        <ApplicationLogo className="h-8 w-8" />
                        <span>Finanzamente</span>
                    </a>

                    <div className="flex items-center gap-4">
                        <span className="text-sm text-slate-600">
                            {user.name}
                        </span>
                        <form onSubmit={handleLogout} action={route('logout')} method="POST">
                            <button
                                type="submit"
                                className="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                            >
                                Esci
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            {/* Main Content */}
            <main className="flex flex-1 flex-col items-center justify-center px-4 py-12">
                <div className="w-full max-w-lg">
                    <div className="overflow-hidden rounded-2xl bg-white p-8 shadow-lg ring-1 ring-slate-200">
                        {children}
                    </div>
                </div>
            </main>

            {/* Footer */}
            <footer className="border-t border-slate-200 bg-white py-4">
                <div className="mx-auto max-w-4xl px-4 text-center text-sm text-slate-500">
                    © {new Date().getFullYear()} Finanzamente. Tutti i diritti
                    riservati.
                </div>
            </footer>
        </div>
    );
}
