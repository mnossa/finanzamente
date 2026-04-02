@extends('layouts.guest')

@section('content')
    <!-- Hero Section -->
    <section class="relative pt-24 sm:pt-32 pb-12 sm:pb-20 overflow-hidden" aria-labelledby="hero-title">
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-white to-primary-50 opacity-70" aria-hidden="true"></div>
        <div class="absolute top-0 right-0 -translate-y-12 translate-x-12 w-72 h-72 bg-blue-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 translate-y-12 -translate-x-12 w-72 h-72 bg-primary-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <span class="inline-flex items-center px-4 py-1.5 rounded-full bg-blue-100 text-blue-700 text-sm font-semibold mb-6">
                    💼 Per chi investe
                </span>
                <h1 id="hero-title" class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-surface-900 leading-tight mb-4 sm:mb-6">
                    Il tuo portafoglio e le spese quotidiane,
                    <span class="bg-gradient-to-r from-blue-600 to-primary-700 bg-clip-text text-transparent">finalmente in un unico posto</span>
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-surface-600 mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed">
                    Traccia ETF, azioni, crypto e obbligazioni insieme alle tue spese quotidiane. Visualizza l'asset allocation, l'indice di rischio e l'andamento del tuo patrimonio complessivo — tutto senza uscire dall'app.
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
                        Scopri le funzionalità
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Asset Allocation Visual -->
    <section id="funzionalita" class="py-12 sm:py-20 bg-gradient-to-br from-blue-50 to-primary-50" aria-labelledby="allocation-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-16 items-center">
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-sm font-medium mb-4">
                        💼 Asset Allocation
                    </span>
                    <h2 id="allocation-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-4 sm:mb-6">
                        Dal conto corrente agli investimenti: un unico patrimonio
                    </h2>
                    <p class="text-base sm:text-lg text-surface-600 mb-6 leading-relaxed">
                        FinanzaMente Pro non si limita alle spese quotidiane. Aggiungi il tuo portafoglio per avere la visione completa di quanto vali davvero.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Grafico donut della composizione del portafoglio per asset class (azionario, obbligazionario, crypto, liquidità…)</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Indice di rischio sintetico (scala 1–7) calcolato sulla composizione reale del tuo patrimonio</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Supporto per ETF, azioni, indici, materie prime, crypto e prodotti assicurativi</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Analisi dell'andamento nel tempo con grafici storici e performance per asset</p>
                        </div>
                    </div>
                </div>

                <!-- Visual card portfolio -->
                <div class="relative bg-white rounded-2xl p-6 sm:p-8 shadow-soft-lg border border-surface-200">
                    <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-5">Esempio asset allocation</p>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-blue-500 flex-shrink-0"></div>
                            <div class="flex-1">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-surface-800">Azionario</span>
                                    <span class="text-surface-600">52%</span>
                                </div>
                                <div class="w-full h-2 bg-surface-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-500 rounded-full" style="width: 52%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-cyan-500 flex-shrink-0"></div>
                            <div class="flex-1">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-surface-800">Liquidità</span>
                                    <span class="text-surface-600">28%</span>
                                </div>
                                <div class="w-full h-2 bg-surface-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-cyan-500 rounded-full" style="width: 28%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-violet-500 flex-shrink-0"></div>
                            <div class="flex-1">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-surface-800">Crypto</span>
                                    <span class="text-surface-600">12%</span>
                                </div>
                                <div class="w-full h-2 bg-surface-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-violet-500 rounded-full" style="width: 12%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-emerald-500 flex-shrink-0"></div>
                            <div class="flex-1">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-surface-800">Obbligazionario</span>
                                    <span class="text-surface-600">8%</span>
                                </div>
                                <div class="w-full h-2 bg-surface-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: 8%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 pt-5 border-t border-surface-100 flex items-center justify-between">
                        <div>
                            <p class="text-xs text-surface-500 mb-1">Patrimonio totale</p>
                            <p class="text-xl font-bold text-surface-900">€ 42.350,00</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-surface-500 mb-1">Rischio</p>
                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-amber-100 text-amber-700 text-sm font-bold">4 / 7 — Moderato</span>
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
                    Tutto ciò che serve a un investitore attivo
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Funzionalità Pro pensate per chi tiene soldi in più posti e vuole un quadro chiaro
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 max-w-6xl mx-auto">
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-blue-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Portfolio e Asset Allocation</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Traccia ETF, azioni, criptovalute e obbligazioni. Visualizza la tua asset allocation con indice di rischio (scala 1–7).
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-blue-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Analisi investimenti</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Analizza l'andamento storico delle tue posizioni, il rendimento per asset class e confronta le performance nel tempo.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-blue-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-cyan-500 to-cyan-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Asset gestiti manualmente</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Inserisci qualsiasi strumento tramite ISIN o ticker. Nessuna connessione obbligatoria al broker: tu controlli i dati.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-emerald-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Inserimento lampo</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Sessione rapida per inserire più transazioni di fila in pochi secondi, anche mentre sei in mobilità.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-amber-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Import da banca</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Importa i movimenti dal CSV della tua banca con rilevamento automatico dei duplicati e layout salvati.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-violet-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-violet-500 to-violet-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Budget per categoria</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Mantieni sotto controllo le spese correnti anche mentre crescono i tuoi investimenti. Bilancia risparmio e stile di vita.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Sezione prezzi -->
    @php $targetId = 'investitori'; @endphp
    @include('partials.landing.pricing')

    <!-- CTA finale -->
    @include('partials.landing.cta-finale', [
        'ctaTitle' => 'Inizia a tracciare il tuo patrimonio completo',
        'ctaSubtitle' => 'Registrati gratis e aggiungi subito i tuoi investimenti insieme alle spese quotidiane. Passa a Pro per sbloccare asset allocation e analisi avanzate.',
    ])
@endsection
