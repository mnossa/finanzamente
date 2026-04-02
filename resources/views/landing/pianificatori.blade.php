@extends('layouts.guest')

@section('content')
    <!-- Hero Section -->
    <section class="relative pt-24 sm:pt-32 pb-12 sm:pb-20 overflow-hidden" aria-labelledby="hero-title">
        <div class="absolute inset-0 bg-gradient-to-br from-violet-50 via-white to-indigo-50 opacity-70" aria-hidden="true"></div>
        <div class="absolute top-0 right-0 -translate-y-12 translate-x-12 w-72 h-72 bg-violet-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 translate-y-12 -translate-x-12 w-72 h-72 bg-indigo-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <span class="inline-flex items-center px-4 py-1.5 rounded-full bg-violet-100 text-violet-700 text-sm font-semibold mb-6">
                    🔮 Per pianificatori finanziari
                </span>
                <h1 id="hero-title" class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-surface-900 leading-tight mb-4 sm:mb-6">
                    Simula il tuo futuro finanziario
                    <span class="bg-gradient-to-r from-violet-600 to-indigo-700 bg-clip-text text-transparent">con scenari reali</span>
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-surface-600 mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed">
                    Pianifica con precisione usando simulazioni che tengono conto del tuo stile di vita reale. Obiettivi illimitati, ricorrenti senza confini e proiezioni personalizzate per sapere esattamente quando raggiungerai i tuoi traguardi.
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

    <!-- Highlight simulazioni -->
    <section id="funzionalita" class="py-12 sm:py-20 bg-gradient-to-br from-violet-50 to-indigo-50" aria-labelledby="sim-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-16 items-center">
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-violet-100 text-violet-700 text-sm font-medium mb-4">
                        📊 Simulazioni finanziarie
                    </span>
                    <h2 id="sim-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-4 sm:mb-6">
                        "Se risparmio X al mese, quando raggiungo Y?"
                    </h2>
                    <p class="text-base sm:text-lg text-surface-600 mb-6 leading-relaxed">
                        Con FinanzaMente Pro puoi simulare diversi scenari finanziari basati sui tuoi dati reali. Cambia le variabili, visualizza le proiezioni e scopri quale percorso ti porta prima al tuo obiettivo.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-violet-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-violet-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Proiezioni personalizzate basate sulle tue entrate, uscite e abitudini reali</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-violet-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-violet-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Scenari multipli: ottimistico, realistico, conservativo — confronta tutte le opzioni</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-violet-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-violet-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Impatto delle ricorrenti e degli obiettivi sulla liquidità futura</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-violet-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-violet-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Grafici interattivi con proiezione temporale chiara e leggibile</p>
                        </div>
                    </div>
                </div>

                <!-- Visual simulazione mock -->
                <div class="relative bg-white rounded-2xl p-6 sm:p-8 shadow-soft-lg border border-surface-200">
                    <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-5">Simulazione: Fondo emergenze 6 mesi</p>
                    <div class="mb-6">
                        <div class="flex justify-between items-end mb-2">
                            <span class="text-xs text-surface-500">Scenario attuale</span>
                            <span class="text-sm font-bold text-violet-700">12 mesi</span>
                        </div>
                        <div class="w-full h-3 bg-surface-100 rounded-full overflow-hidden mb-4">
                            <div class="h-full bg-violet-500 rounded-full" style="width: 55%"></div>
                        </div>
                        <div class="flex justify-between items-end mb-2">
                            <span class="text-xs text-surface-500">Con risparmio +€200/mese</span>
                            <span class="text-sm font-bold text-emerald-700">8 mesi</span>
                        </div>
                        <div class="w-full h-3 bg-surface-100 rounded-full overflow-hidden mb-4">
                            <div class="h-full bg-emerald-500 rounded-full" style="width: 75%"></div>
                        </div>
                        <div class="flex justify-between items-end mb-2">
                            <span class="text-xs text-surface-500">Con ottimizzazione spese</span>
                            <span class="text-sm font-bold text-blue-700">6 mesi</span>
                        </div>
                        <div class="w-full h-3 bg-surface-100 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width: 100%"></div>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-surface-100">
                        <div class="flex justify-between">
                            <div>
                                <p class="text-xs text-surface-500 mb-1">Obiettivo</p>
                                <p class="text-lg font-bold text-surface-900">€ 12.000</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-surface-500 mb-1">Accantonato finora</p>
                                <p class="text-lg font-bold text-violet-700">€ 4.200</p>
                            </div>
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
                    Il kit completo del pianificatore attivo
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Strumenti Pro per chi vuole costruire il proprio futuro finanziario con metodo
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 max-w-6xl mx-auto">
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-violet-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-violet-500 to-violet-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Simulazioni finanziarie</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Proiezioni personalizzate per capire quando raggiungerai i tuoi obiettivi, con scenari multipli e grafici interattivi.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-indigo-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Obiettivi illimitati</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Crea quanti obiettivi vuoi: fondo emergenze, anticipo casa, pensione integrativa, viaggio — monitorali tutti in parallelo.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-violet-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Ricorrenti illimitate</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Automatizza tutti i tuoi flussi ricorrenti senza limiti: piani di risparmio, investimenti automatici, abbonamenti.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-emerald-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Budget avanzato</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Imposta budget mensili dettagliati per ogni categoria di spesa e monitora le deviazioni dal piano in tempo reale.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-blue-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Portafoglio investimenti</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Integra il portafoglio nella pianificazione complessiva. Il patrimonio investito contribuisce alle proiezioni future.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-amber-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Import dati bancari</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Importa lo storico bancario per avere subito dati storici reali su cui basare simulazioni accurate.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Sezione prezzi -->
    @php $targetId = 'pianificatori'; @endphp
    @include('partials.landing.pricing')

    <!-- CTA finale -->
    @include('partials.landing.cta-finale', [
        'ctaTitle' => 'Inizia a costruire il tuo futuro finanziario oggi',
        'ctaSubtitle' => 'Registrati gratis e crea i tuoi primi obiettivi finanziari. Passa a Pro per sbloccare simulazioni avanzate e obiettivi illimitati.',
    ])
@endsection
