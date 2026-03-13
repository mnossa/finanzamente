import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import CardBox from '@/Components/CardBox';
import { Head, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import React from 'react';

interface Props extends PageProps {
    linked: boolean;
    token: string | null;
    tokenExpiresAt: string | null;
    botUsername: string | null;
}

function CountdownTimer({ expiresAt }: { expiresAt: string }) {
    const [seconds, setSeconds] = React.useState(() => {
        const ms = new Date(expiresAt).getTime() - Date.now();
        return Math.max(0, Math.floor(ms / 1000));
    });

    React.useEffect(() => {
        if (seconds <= 0) return;
        const id = setInterval(() => {
            setSeconds(Math.max(0, Math.floor((new Date(expiresAt).getTime() - Date.now()) / 1000)));
        }, 1000);
        return () => clearInterval(id);
    }, [expiresAt, seconds]);

    if (seconds <= 0) return <span className="text-red-500 font-medium">Scaduto</span>;

    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return (
        <span className="font-mono font-medium text-amber-600 dark:text-amber-400">
            {mins}:{secs.toString().padStart(2, '0')}
        </span>
    );
}

export default function TelegramLink({ linked, token, tokenExpiresAt, botUsername }: Props) {
    const generateForm = useForm({});
    const unlinkForm = useForm({});

    const botLink = botUsername && token
        ? `https://t.me/${botUsername}?start=${token}`
        : null;

    function handleGenerate(e: React.FormEvent) {
        e.preventDefault();
        generateForm.post(route('telegram.link.generate'));
    }

    function handleUnlink(e: React.FormEvent) {
        e.preventDefault();
        if (window.confirm('Sei sicuro di voler scollegare il tuo account Telegram?')) {
            unlinkForm.delete(route('telegram.link.unlink'));
        }
    }

    return (
        <AuthenticatedLayout
            header={<PageHeader title="Collegamento Telegram" subtitle="Invia spese direttamente da Telegram alla tua Inbox" />}
        >
            <Head title="Collegamento Telegram" />

            <div className="py-6 px-4 sm:px-6 lg:px-8 max-w-xl mx-auto space-y-6">

                {/* Stato collegamento */}
                <CardBox>
                    <div className="flex items-center gap-3">
                        <div className={`w-10 h-10 rounded-full flex items-center justify-center text-xl ${linked ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-slate-100 dark:bg-slate-700'}`}>
                            {linked ? '✅' : '🔗'}
                        </div>
                        <div>
                            <p className="font-semibold text-slate-800 dark:text-white">
                                {linked ? 'Account Telegram collegato' : 'Account Telegram non collegato'}
                            </p>
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                {linked
                                    ? 'Puoi inviare spese e scontrini direttamente dal bot.'
                                    : 'Collega il tuo account per inviare spese da Telegram.'}
                            </p>
                        </div>
                    </div>

                    {linked && (
                        <form onSubmit={handleUnlink} className="mt-4">
                            <button
                                type="submit"
                                disabled={unlinkForm.processing}
                                className="px-4 py-2 rounded-lg border border-red-300 dark:border-red-800 text-red-600 dark:text-red-400 text-sm hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors disabled:opacity-50"
                            >
                                {unlinkForm.processing ? 'Scollegamento...' : 'Scollega account Telegram'}
                            </button>
                        </form>
                    )}
                </CardBox>

                {/* Token di collegamento */}
                {!linked && (
                    <CardBox>
                        <h3 className="font-semibold text-slate-800 dark:text-white mb-4">
                            Come collegare il tuo account
                        </h3>

                        <ol className="space-y-3 text-sm text-slate-600 dark:text-slate-400 mb-6">
                            <li className="flex gap-2">
                                <span className="font-bold text-emerald-600 dark:text-emerald-400">1.</span>
                                Genera un token univoco qui sotto (valido 30 minuti).
                            </li>
                            <li className="flex gap-2">
                                <span className="font-bold text-emerald-600 dark:text-emerald-400">2.</span>
                                {botUsername
                                    ? <>Apri il bot <strong>@{botUsername}</strong> su Telegram.</>
                                    : <>Apri il bot Finanzamente su Telegram.</>}
                            </li>
                            <li className="flex gap-2">
                                <span className="font-bold text-emerald-600 dark:text-emerald-400">3.</span>
                                Invia il comando <code className="bg-slate-100 dark:bg-slate-700 px-1 rounded">/start TOKEN</code> oppure clicca il link generato.
                            </li>
                        </ol>

                        {token && tokenExpiresAt ? (
                            <div className="space-y-3">
                                <div className="p-3 rounded-lg bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600">
                                    <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Il tuo token (scade tra <CountdownTimer expiresAt={tokenExpiresAt} />):</p>
                                    <code className="text-sm font-mono font-bold text-slate-800 dark:text-white break-all">{token}</code>
                                </div>

                                {botLink && (
                                    <a
                                        href={botLink}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg bg-sky-500 text-white text-sm font-medium hover:bg-sky-600 transition-colors"
                                    >
                                        <svg viewBox="0 0 24 24" fill="currentColor" className="w-4 h-4">
                                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.820 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                        </svg>
                                        Apri su Telegram
                                    </a>
                                )}

                                <form onSubmit={handleGenerate}>
                                    <button
                                        type="submit"
                                        disabled={generateForm.processing}
                                        className="w-full px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 text-sm hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors disabled:opacity-50"
                                    >
                                        {generateForm.processing ? 'Generazione...' : '🔄 Rigenera token'}
                                    </button>
                                </form>
                            </div>
                        ) : (
                            <form onSubmit={handleGenerate}>
                                <button
                                    type="submit"
                                    disabled={generateForm.processing}
                                    className="w-full px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition-colors disabled:opacity-50"
                                >
                                    {generateForm.processing ? 'Generazione...' : '🔑 Genera token di collegamento'}
                                </button>
                            </form>
                        )}
                    </CardBox>
                )}

                {/* Come usare il bot */}
                <CardBox>
                    <h3 className="font-semibold text-slate-800 dark:text-white mb-3">Come inviare le spese</h3>
                    <ul className="space-y-2 text-sm text-slate-600 dark:text-slate-400">
                        <li className="flex gap-2">
                            <span>💬</span>
                            <span><strong>Testo:</strong> invia <code className="bg-slate-100 dark:bg-slate-700 px-1 rounded">15.50 Pizza</code> o <code className="bg-slate-100 dark:bg-slate-700 px-1 rounded">Supermercato 32</code></span>
                        </li>
                        <li className="flex gap-2">
                            <span>📸</span>
                            <span><strong>Foto scontrino:</strong> invia direttamente la foto — l'AI estrae importo, negozio e data.</span>
                        </li>
                        <li className="flex gap-2">
                            <span>✅</span>
                            <span>Le spese arrivano in <a href={route('inbox.index')} className="text-emerald-600 underline">Inbox</a> e richiedono conferma prima di essere conteggiate nei report.</span>
                        </li>
                    </ul>
                </CardBox>
            </div>
        </AuthenticatedLayout>
    );
}
