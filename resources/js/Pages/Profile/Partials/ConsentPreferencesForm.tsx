import PrimaryButton from '@/Components/PrimaryButton';
import FormActionsBar from '@/Components/FormActionsBar';
import InlineSuccessBadge from '@/Components/InlineSuccessBadge';
import SectionBadge from '@/Components/SectionBadge';
import { Transition } from '@headlessui/react';
import { FormEventHandler } from 'react';
import { useForm } from '@inertiajs/react';

interface Props {
    className?: string;
    consents: {
        marketing_email: boolean;
        analytics_tracking: boolean;
    };
}

export default function ConsentPreferencesForm({ className = '', consents }: Props) {
    const { data, setData, patch, post, processing, recentlySuccessful } = useForm({
        marketing_email: consents.marketing_email,
        analytics_tracking: consents.analytics_tracking,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.consents.update'));
    };

    return (
        <section className={className}>
            <header className="hidden sm:block space-y-2">
                <SectionBadge
                    label="Privacy"
                    icon={(
                        <svg className="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fillRule="evenodd" d="M10 1a1 1 0 01.707.293l6 6A1 1 0 0117 8v2c0 4.273-2.865 7.888-6.78 8.873a1 1 0 01-.44 0C5.865 17.888 3 14.273 3 10V8a1 1 0 01.293-.707l6-6A1 1 0 0110 1zm3.707 7.707a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                        </svg>
                    )}
                />
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Preferenze privacy
                </h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Gestisci i consensi opzionali per marketing e analytics.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <div className="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                    <label className="flex items-start gap-3">
                        <input
                            type="checkbox"
                            checked={data.marketing_email}
                            onChange={(e) => setData('marketing_email', e.target.checked)}
                            className="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        <span className="text-sm font-medium text-gray-800 dark:text-gray-200">
                            Ricevi email marketing e aggiornamenti prodotto.
                        </span>
                    </label>
                    <p className="ml-7 mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Se disattivato, continuerai comunque a ricevere email di servizio e sicurezza.
                    </p>
                </div>

                <div className="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                    <label className="flex items-start gap-3">
                        <input
                            type="checkbox"
                            checked={data.analytics_tracking}
                            onChange={(e) => setData('analytics_tracking', e.target.checked)}
                            className="mt-0.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        <span className="text-sm font-medium text-gray-800 dark:text-gray-200">
                            Consenti analytics per miglioramento servizio.
                        </span>
                    </label>
                    <p className="ml-7 mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Attivandolo mi aiuti a migliorare l'app con statistiche anonime di utilizzo (niente pubblicità).
                    </p>
                </div>

                <FormActionsBar sticky={false}>
                    <PrimaryButton disabled={processing}>Salva preferenze privacy</PrimaryButton>
                    <button
                        type="button"
                        onClick={() => post(route('profile.consents.revoke-optional'), {
                            preserveScroll: true,
                            onSuccess: () => {
                                setData('marketing_email', false);
                                setData('analytics_tracking', false);
                            },
                        })}
                        className="inline-flex items-center rounded-md border border-red-300 px-3 py-2 text-sm font-medium text-red-700 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-950/40"
                        disabled={processing}
                    >
                        Revoca consensi opzionali
                    </button>
                    <div className="inline-flex items-center rounded-md border border-emerald-300 bg-emerald-50 text-emerald-800 transition hover:bg-emerald-100 focus-within:ring-2 focus-within:ring-emerald-500 focus-within:ring-offset-2 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200 dark:hover:bg-emerald-900/60">
                        <a
                            href={route('profile.consents.export')}
                            className="px-3 py-2 text-sm font-medium focus:outline-none"
                        >
                            Esporta storico consensi (JSON)
                        </a>
                    </div>
                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <InlineSuccessBadge />
                    </Transition>
                </FormActionsBar>
            </form>
        </section>
    );
}
