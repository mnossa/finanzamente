@extends('layouts.guest')

@section('content')
    <!-- Hero Section -->
    <section class="relative pt-24 sm:pt-32 pb-12 sm:pb-20 overflow-hidden" aria-labelledby="hero-title">
        <div class="absolute inset-0 bg-gradient-to-br from-orange-50 via-white to-amber-50 opacity-70" aria-hidden="true"></div>
        <div class="absolute top-0 right-0 -translate-y-12 translate-x-12 w-72 h-72 bg-orange-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 translate-y-12 -translate-x-12 w-72 h-72 bg-amber-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <span class="inline-flex items-center px-4 py-1.5 rounded-full bg-orange-100 text-orange-700 text-sm font-semibold mb-6">
                    🧾 Per Freelance e Partita IVA
                </span>
                <h1 id="hero-title" class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-surface-900 leading-tight mb-4 sm:mb-6">
                    Finanze e IVA sotto controllo,
                    <span class="bg-gradient-to-r from-orange-600 to-amber-600 bg-clip-text text-transparent">senza stressarti ogni trimestre</span>
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-surface-600 mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed">
                    Gestisci le spese deducibili, tieni separati i costi professionali da quelli personali e preparati per ogni liquidazione IVA — tutto in un'unica app italiana, pensata per la realtà fiscale italiana.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center mb-8 sm:mb-12">
                    @if (Route::has('plan.select'))
                        <a href="{{ route('plan.select') }}?plan=pro" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-white bg-gradient-to-r from-accent-600 to-accent-700 hover:from-accent-700 hover:to-accent-800 rounded-xl shadow-accent transition-all duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Inizia con Pro
                        </a>
                    @endif
                    <a href="#funzionalita" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-medium text-primary-700 bg-white hover:bg-surface-50 rounded-xl border-2 border-primary-200 hover:border-primary-300 transition-all duration-200">
                        Scopri come funziona
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Highlight IVA -->
    <section id="funzionalita" class="py-12 sm:py-20 bg-gradient-to-br from-orange-50 to-amber-50" aria-labelledby="iva-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-16 items-center">
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-orange-100 text-orange-700 text-sm font-medium mb-4">
                        🧾 Gestione IVA
                    </span>
                    <h2 id="iva-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-4 sm:mb-6">
                        Tieni l'IVA sempre pronta per la liquidazione
                    </h2>
                    <p class="text-base sm:text-lg text-surface-600 mb-6 leading-relaxed">
                        Con FinanzaMente Pro puoi marcare ogni transazione con l'aliquota IVA, separare i costi professionali da quelli personali e avere sempre chiaro quanto devi versare — senza sorprese a fine trimestre.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-orange-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Marcatura IVA per transazione con aliquote personalizzabili (22%, 10%, 4%, esente…)</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-orange-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Riepilogo trimestrale IVA a credito e IVA a debito pronto per il commercialista</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-orange-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Categorie separate per spese professionali e personali — nessun incrocio nei report</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-orange-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-orange-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Import CSV da conti business e carte dedicate alla professione</p>
                        </div>
                    </div>
                </div>

                <!-- Visual IVA mock -->
                <div class="relative bg-white rounded-2xl p-6 sm:p-8 shadow-soft-lg border border-surface-200">
                    <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-5">Riepilogo IVA — Q1 2025</p>
                    <div class="space-y-4 mb-6">
                        <div class="p-3 bg-orange-50 rounded-xl border border-orange-100">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-orange-800">IVA a debito (sulle fatture)</span>
                                <span class="text-sm font-bold text-orange-900">+ € 2.420,00</span>
                            </div>
                        </div>
                        <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-emerald-800">IVA a credito (sugli acquisti)</span>
                                <span class="text-sm font-bold text-emerald-900">- € 380,00</span>
                            </div>
                        </div>
                        <div class="p-4 bg-surface-900 rounded-xl">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-semibold text-white">IVA da versare</span>
                                <span class="text-lg font-extrabold text-amber-400">€ 2.040,00</span>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs text-surface-500 font-medium uppercase tracking-wide mb-2">Ultime spese deducibili</p>
                        <div class="flex justify-between text-sm py-1.5 border-b border-surface-100">
                            <span class="text-surface-700">Abbonamento software</span>
                            <span class="font-medium text-surface-900">€ 49,00</span>
                        </div>
                        <div class="flex justify-between text-sm py-1.5 border-b border-surface-100">
                            <span class="text-surface-700">Telefonia business</span>
                            <span class="font-medium text-surface-900">€ 25,00</span>
                        </div>
                        <div class="flex justify-between text-sm py-1.5">
                            <span class="text-surface-700">Formazione professionale</span>
                            <span class="font-medium text-surface-900">€ 199,00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Funzionalità specifiche -->
    <section class="py-12 sm:py-20 bg-white" aria-labelledby="features-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10 sm:mb-16">
                <h2 id="features-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    Strumenti pensati per la Partita IVA italiana
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Gestisci la complessità fiscale italiana con semplicità
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 max-w-6xl mx-auto">
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-orange-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Gestione IVA</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Marca ogni transazione con l'aliquota IVA e ottieni il riepilogo trimestrale pronto per la liquidazione.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-orange-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Detrazioni e deducibili</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Marca le spese deducibili e ottieni un report PDF completo per il commercialista a fine anno.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-emerald-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Inserimento rapido</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Registra una spesa professionale in 5 secondi, subito dopo l'acquisto, senza rimandare a fine giornata.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-violet-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-violet-500 to-violet-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Ricorrenti illimitate</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Automatizza abbonamenti software, hosting, SaaS e tutti i costi fissi del tuo lavoro senza limiti di numero.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-blue-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Import da banca business</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Importa i movimenti dal CSV del tuo conto business. Layout salvati per Fineco, Intesa, UniCredit e altri istituti.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-red-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Crediti illimitati</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Tieni traccia dei pagamenti in sospeso dai clienti senza limiti. Collega ogni incasso alla fattura originale.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Sezione prezzi -->
    @php $targetId = 'freelance'; @endphp
    @include('partials.landing.pricing')

    <!-- CTA finale -->
    @include('partials.landing.cta-finale', [
        'ctaTitle' => 'Metti in ordine le tue finanze da freelance',
        'ctaSubtitle' => 'Registrati gratis e inizia a gestire spese professionali e personali separatamente. Passa a Pro per sbloccare la gestione IVA e le detrazioni fiscali.',
    ])
@endsection
