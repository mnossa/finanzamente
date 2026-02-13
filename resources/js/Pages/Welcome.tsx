import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

export default function Welcome({ auth }: PageProps) {
    return (
        <>
            <Head title="Finanzamente - Gestione Finanziaria Personale" />
            <div className="bg-white text-gray-900">
                {/* Header */}
                <header className="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-sm border-b border-gray-200">
                    <div className="container mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            <div className="flex items-center">
                                <h1 className="text-xl sm:text-2xl font-bold text-emerald-600">
                                    Finanzamente
                                </h1>
                            </div>
                            <nav className="flex items-center gap-2 sm:gap-4">
                                {auth.user ? (
                                    <Link
                                        href={route('dashboard')}
                                        className="px-3 py-2 sm:px-4 text-sm sm:text-base rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors"
                                    >
                                        Dashboard
                                    </Link>
                                ) : (
                                    <>
                                        <Link
                                            href={route('login')}
                                            className="px-3 py-2 sm:px-4 text-sm sm:text-base rounded-lg text-gray-700 hover:text-emerald-600 transition-colors"
                                        >
                                            Accedi
                                        </Link>
                                        <Link
                                            href={route('register')}
                                            className="px-3 py-2 sm:px-4 text-sm sm:text-base rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors"
                                        >
                                            Registrati
                                        </Link>
                                    </>
                                )}
                            </nav>
                        </div>
                    </div>
                </header>

                {/* Hero Section */}
                <section className="pt-24 sm:pt-32 pb-12 sm:pb-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-emerald-50 via-white to-teal-50">
                    <div className="container mx-auto max-w-6xl">
                        <div className="text-center">
                            <h2 className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 mb-4 sm:mb-6">
                                Gestisci le tue finanze con{' '}
                                <span className="text-emerald-600">consapevolezza</span>
                            </h2>
                            <p className="text-base sm:text-lg md:text-xl text-gray-700 mb-6 sm:mb-8 max-w-3xl mx-auto leading-relaxed">
                                Finanzamente ti permette di controllare le tue finanze personali e familiari
                                in modo <strong>manuale e consapevole</strong>, senza collegare i tuoi conti bancari.
                                <span className="block mt-2">
                                    La tua privacy è al centro, i tuoi dati restano sempre sotto il tuo controllo.
                                </span>
                            </p>
                            {!auth.user && (
                                <div className="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center">
                                    <Link
                                        href={route('register')}
                                        className="w-full sm:w-auto px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors font-semibold shadow-lg hover:shadow-xl"
                                    >
                                        Inizia Gratis
                                    </Link>
                                    <a
                                        href="#come-funziona"
                                        className="w-full sm:w-auto px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg rounded-lg border-2 border-emerald-600 text-emerald-600 hover:bg-emerald-50 transition-colors font-semibold"
                                    >
                                        Scopri di più
                                    </a>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section className="py-12 sm:py-20 px-4 sm:px-6 lg:px-8 bg-white">
                    <div className="container mx-auto max-w-6xl">
                        <div className="text-center mb-10 sm:mb-16">
                            <h3 className="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3 sm:mb-4">
                                Perché scegliere Finanzamente
                            </h3>
                            <p className="text-base sm:text-lg text-gray-600 max-w-2xl mx-auto">
                                Un approccio diverso alla gestione delle finanze personali
                            </p>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                            {/* Privacy Totale */}
                            <div className="p-6 rounded-xl bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-100">
                                <div className="w-12 h-12 rounded-lg bg-emerald-600 flex items-center justify-center mb-4">
                                    <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <h4 className="text-lg sm:text-xl font-bold text-gray-900 mb-2">Privacy Totale</h4>
                                <p className="text-sm sm:text-base text-gray-700">
                                    Nessuna sincronizzazione automatica. I tuoi dati finanziari restano completamente
                                    privati e sotto il tuo controllo diretto.
                                </p>
                            </div>

                            {/* Consapevolezza */}
                            <div className="p-6 rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100">
                                <div className="w-12 h-12 rounded-lg bg-blue-600 flex items-center justify-center mb-4">
                                    <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <h4 className="text-lg sm:text-xl font-bold text-gray-900 mb-2">Gestione Consapevole</h4>
                                <p className="text-sm sm:text-base text-gray-700">
                                    Inserendo manualmente ogni transazione, diventi più consapevole delle tue spese
                                    e sviluppi abitudini finanziarie migliori.
                                </p>
                            </div>

                            {/* Flessibilità */}
                            <div className="p-6 rounded-xl bg-gradient-to-br from-purple-50 to-pink-50 border border-purple-100">
                                <div className="w-12 h-12 rounded-lg bg-purple-600 flex items-center justify-center mb-4">
                                    <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                    </svg>
                                </div>
                                <h4 className="text-lg sm:text-xl font-bold text-gray-900 mb-2">Massima Flessibilità</h4>
                                <p className="text-sm sm:text-base text-gray-700">
                                    Adatto a single, famiglie, partite IVA. Gestisci più nuclei familiari
                                    o progetti con un solo account.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Pricing Section */}
                <section className="py-12 sm:py-20 px-4 sm:px-6 lg:px-8 bg-gray-50" id="piani">
                    <div className="container mx-auto max-w-6xl">
                        <div className="text-center mb-10 sm:mb-16">
                            <h3 className="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3 sm:mb-4">
                                Scegli il piano adatto a te
                            </h3>
                            <p className="text-base sm:text-lg text-gray-600 max-w-2xl mx-auto">
                                Inizia gratis e passa a Premium quando ne hai bisogno
                            </p>
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8 max-w-5xl mx-auto">
                            {/* Piano Free */}
                            <div className="bg-white rounded-2xl shadow-lg border-2 border-gray-200 overflow-hidden flex flex-col">
                                <div className="p-6 sm:p-8 bg-gradient-to-br from-gray-50 to-gray-100">
                                    <h4 className="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Piano Free</h4>
                                    <div className="flex items-baseline gap-2 mb-4">
                                        <span className="text-3xl sm:text-4xl font-bold text-emerald-600">€0</span>
                                        <span className="text-gray-600">/mese</span>
                                    </div>
                                    <p className="text-sm sm:text-base text-gray-700">
                                        Perfetto per la gestione personale delle tue finanze
                                    </p>
                                </div>
                                <div className="p-6 sm:p-8 flex-grow">
                                    <ul className="space-y-3 sm:space-y-4">
                                        <li className="flex items-start gap-3">
                                            <svg className="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span className="text-sm sm:text-base text-gray-700">
                                                <strong>1 conto</strong> per la gestione delle finanze personali
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <svg className="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span className="text-sm sm:text-base text-gray-700">
                                                <strong>Tutte le funzionalità base</strong>: transazioni, categorie, budget
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <svg className="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span className="text-sm sm:text-base text-gray-700">
                                                <strong>Promemoria</strong> per scadenze e spese ricorrenti
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <svg className="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span className="text-sm sm:text-base text-gray-700">
                                                <strong>Privacy totale</strong>: nessuna sincronizzazione automatica
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <div className="p-6 sm:p-8 pt-0">
                                    {!auth.user && (
                                        <Link
                                            href={route('register')}
                                            className="block w-full text-center px-6 py-3 rounded-lg border-2 border-emerald-600 text-emerald-600 hover:bg-emerald-50 transition-colors font-semibold"
                                        >
                                            Inizia Gratis
                                        </Link>
                                    )}
                                </div>
                            </div>

                            {/* Piano Premium */}
                            <div className="bg-white rounded-2xl shadow-xl border-2 border-emerald-600 overflow-hidden flex flex-col relative">
                                <div className="absolute top-0 right-0 bg-emerald-600 text-white px-4 py-1 text-xs sm:text-sm font-bold rounded-bl-lg">
                                    CONSIGLIATO
                                </div>
                                <div className="p-6 sm:p-8 bg-gradient-to-br from-emerald-50 to-teal-50">
                                    <h4 className="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">Piano Premium</h4>
                                    <div className="flex items-baseline gap-2 mb-4">
                                        <span className="text-3xl sm:text-4xl font-bold text-emerald-600">€9,99</span>
                                        <span className="text-gray-600">/mese</span>
                                    </div>
                                    <p className="text-sm sm:text-base text-gray-700">
                                        Per famiglie, professionisti e chi vuole il massimo controllo
                                    </p>
                                </div>
                                <div className="p-6 sm:p-8 flex-grow">
                                    <ul className="space-y-3 sm:space-y-4">
                                        <li className="flex items-start gap-3">
                                            <svg className="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span className="text-sm sm:text-base text-gray-700">
                                                <strong>Conti illimitati</strong>: gestisci tutti i tuoi conti, carte e wallet
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <svg className="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span className="text-sm sm:text-base text-gray-700">
                                                <strong>Multi-households</strong>: gestisci più nuclei familiari o progetti
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <svg className="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span className="text-sm sm:text-base text-gray-700">
                                                <strong>Strumenti avanzati per partite IVA</strong>: fatture, ricavi, gestione fiscale
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <svg className="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span className="text-sm sm:text-base text-gray-700">
                                                <strong>Reportistica evoluta</strong>: analisi dettagliate e grafici personalizzati
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <svg className="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span className="text-sm sm:text-base text-gray-700">
                                                <strong>Export dati</strong>: esporta i tuoi dati in formato CSV, Excel, PDF
                                            </span>
                                        </li>
                                        <li className="flex items-start gap-3">
                                            <svg className="w-5 h-5 sm:w-6 sm:h-6 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span className="text-sm sm:text-base text-gray-700">
                                                Tutte le funzionalità del piano Free incluse
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                                <div className="p-6 sm:p-8 pt-0">
                                    {!auth.user && (
                                        <Link
                                            href={route('register')}
                                            className="block w-full text-center px-6 py-3 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors font-semibold shadow-lg"
                                        >
                                            Prova Premium
                                        </Link>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Come Funziona Section */}
                <section className="py-12 sm:py-20 px-4 sm:px-6 lg:px-8 bg-white" id="come-funziona">
                    <div className="container mx-auto max-w-6xl">
                        <div className="text-center mb-10 sm:mb-16">
                            <h3 className="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3 sm:mb-4">
                                Come funziona
                            </h3>
                            <p className="text-base sm:text-lg text-gray-600 max-w-2xl mx-auto">
                                Gestisci le tue finanze in 3 semplici passaggi
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
                            {/* Step 1 */}
                            <div className="text-center">
                                <div className="w-16 h-16 rounded-full bg-emerald-600 text-white flex items-center justify-center text-2xl font-bold mx-auto mb-4">
                                    1
                                </div>
                                <h4 className="text-lg sm:text-xl font-bold text-gray-900 mb-3">Crea il tuo account</h4>
                                <p className="text-sm sm:text-base text-gray-700">
                                    Registrati gratuitamente e configura il tuo primo conto. Nessun dato bancario richiesto,
                                    nessuna sincronizzazione automatica.
                                </p>
                            </div>

                            {/* Step 2 */}
                            <div className="text-center">
                                <div className="w-16 h-16 rounded-full bg-emerald-600 text-white flex items-center justify-center text-2xl font-bold mx-auto mb-4">
                                    2
                                </div>
                                <h4 className="text-lg sm:text-xl font-bold text-gray-900 mb-3">Registra le transazioni</h4>
                                <p className="text-sm sm:text-base text-gray-700">
                                    Inserisci manualmente ogni spesa e entrata. Questo ti rende più consapevole
                                    delle tue abitudini finanziarie.
                                </p>
                            </div>

                            {/* Step 3 */}
                            <div className="text-center">
                                <div className="w-16 h-16 rounded-full bg-emerald-600 text-white flex items-center justify-center text-2xl font-bold mx-auto mb-4">
                                    3
                                </div>
                                <h4 className="text-lg sm:text-xl font-bold text-gray-900 mb-3">Monitora e pianifica</h4>
                                <p className="text-sm sm:text-base text-gray-700">
                                    Visualizza report, imposta budget, pianifica obiettivi finanziari.
                                    Tutto sotto il tuo controllo.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* CTA Section */}
                {!auth.user && (
                    <section className="py-12 sm:py-20 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-emerald-600 to-teal-600 text-white">
                        <div className="container mx-auto max-w-4xl text-center">
                            <h3 className="text-2xl sm:text-3xl md:text-4xl font-bold mb-4 sm:mb-6">
                                Prendi il controllo delle tue finanze oggi
                            </h3>
                            <p className="text-base sm:text-lg md:text-xl mb-6 sm:mb-8 opacity-90">
                                Inizia gratis e scopri come la gestione manuale delle tue finanze
                                può aiutarti a raggiungere i tuoi obiettivi.
                            </p>
                            <Link
                                href={route('register')}
                                className="inline-block px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg rounded-lg bg-white text-emerald-600 hover:bg-gray-100 transition-colors font-semibold shadow-xl"
                            >
                                Registrati Gratuitamente
                            </Link>
                        </div>
                    </section>
                )}

                {/* Footer */}
                <footer className="py-8 sm:py-12 px-4 sm:px-6 lg:px-8 bg-gray-900 text-gray-400">
                    <div className="container mx-auto max-w-6xl">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 sm:gap-8 mb-6 sm:mb-8">
                            <div>
                                <h5 className="text-white font-bold mb-3 sm:mb-4 text-base sm:text-lg">Finanzamente</h5>
                                <p className="text-xs sm:text-sm">
                                    Gestione finanziaria personale con privacy e consapevolezza al centro.
                                </p>
                            </div>
                            <div>
                                <h5 className="text-white font-bold mb-3 sm:mb-4 text-sm sm:text-base">Prodotto</h5>
                                <ul className="space-y-2 text-xs sm:text-sm">
                                    <li>
                                        <a href="#piani" className="hover:text-white transition-colors">
                                            Piani e Prezzi
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#come-funziona" className="hover:text-white transition-colors">
                                            Come Funziona
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <h5 className="text-white font-bold mb-3 sm:mb-4 text-sm sm:text-base">Supporto</h5>
                                <ul className="space-y-2 text-xs sm:text-sm">
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            Aiuto
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            Documentazione
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <h5 className="text-white font-bold mb-3 sm:mb-4 text-sm sm:text-base">Legale</h5>
                                <ul className="space-y-2 text-xs sm:text-sm">
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            Privacy Policy
                                        </a>
                                    </li>
                                    <li>
                                        <a href="#" className="hover:text-white transition-colors">
                                            Termini di Servizio
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div className="border-t border-gray-800 pt-6 sm:pt-8 text-center text-xs sm:text-sm">
                            <p>&copy; {new Date().getFullYear()} Finanzamente. Tutti i diritti riservati.</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
