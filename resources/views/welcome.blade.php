@extends('layouts.guest')

{{-- I meta tag sono impostati in WelcomeController tramite artesaos/seotools --}}

@section('content')
    <!-- Hero Section -->
    <section class="relative pt-24 sm:pt-32 pb-12 sm:pb-20 overflow-hidden" aria-labelledby="hero-title">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-50 via-white to-accent-50 opacity-60" aria-hidden="true"></div>
        <div class="absolute top-0 right-0 -translate-y-12 translate-x-12 w-72 h-72 bg-primary-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 translate-y-12 -translate-x-12 w-72 h-72 bg-accent-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <h1 id="hero-title" class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-surface-900 leading-tight mb-4 sm:mb-6">
                    Il tuo quadro finanziario completo,
                    <span class="bg-gradient-to-r from-primary-600 to-primary-800 bg-clip-text text-transparent">tutto in un posto</span>
                </h1>

                <p class="text-base sm:text-lg md:text-xl text-surface-600 mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed">
                    Dal conto corrente agli investimenti, dai debiti agli obiettivi di risparmio: FinanzaMente ti dà una visione chiara e completa del tuo patrimonio, con privacy totale e senza complicazioni.
                </p>

                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center mb-8 sm:mb-12">
                    @if (Route::has('plan.select'))
                        <a href="{{ route('plan.select') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-white bg-gradient-to-r from-accent-600 to-accent-700 hover:from-accent-700 hover:to-accent-800 rounded-xl shadow-accent hover:shadow-accent-lg transition-all duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Inizia gratis ora
                        </a>
                    @endif
                    <a href="#funzionalita" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-medium text-primary-700 bg-white hover:bg-surface-50 rounded-xl border-2 border-primary-200 hover:border-primary-300 transition-all duration-200">
                        Scopri le funzionalità
                    </a>
                </div>

                <!-- Trust indicators -->
                <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-8 text-sm text-surface-600">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-accent-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        100% gratuito
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-accent-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        Privacy totale, nessun tracciamento
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-accent-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z" />
                        </svg>
                        Pensato per l'Italia
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Grid Section -->
    <section id="funzionalita" class="py-12 sm:py-20 bg-white" aria-labelledby="features-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10 sm:mb-16">
                <h2 id="features-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    Tutto quello che serve, niente di superfluo
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Funzionalità concrete per ogni aspetto della tua vita finanziaria
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 max-w-6xl mx-auto">
                <!-- Transazioni rapide -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Inserimento lampo</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Sessione rapida per inserire più transazioni di fila in pochi secondi. Perfetta per registrare la spesa del supermercato o le uscite del weekend.
                    </p>
                </div>

                <!-- Portfolio investimenti -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Portfolio e Asset Allocation</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Traccia ETF, azioni, criptovalute e obbligazioni. Visualizza la tua asset allocation con indice di rischio (scala 1–7) per capire quanto sei esposto.
                    </p>
                </div>

                <!-- Budget -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Budget per categoria</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Imposta tetti di spesa mensili per ogni categoria. Monitora l'avanzamento in tempo reale e tieni a bada le cattive abitudini.
                    </p>
                </div>

                <!-- Debiti e crediti -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Debiti e crediti</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Tieni traccia di chi ti deve soldi e di cosa devi restituire. Collega i rimborsi alle transazioni originali e chiudi i conti in sospeso senza dimenticare nulla.
                    </p>
                </div>

                <!-- Spese ricorrenti -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-violet-500 to-violet-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Transazioni ricorrenti</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Automatizza abbonamenti, affitti e stipendi. Definisci la frequenza e genera le transazioni con un click quando arriva il momento.
                    </p>
                </div>

                <!-- Detrazioni fiscali -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-teal-500 to-teal-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Detrazioni fiscali</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Marca le spese detraibili durante l'anno ed esporta tutto in PDF per la dichiarazione dei redditi. Nessuna spesa dimenticata al 730.
                    </p>
                </div>

                <!-- Household condiviso -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-pink-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Finanze condivise</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Gestisci le finanze di famiglia o coinquilini in un household condiviso. Ognuno vede solo ciò che gli compete, con trasferimenti tra nuclei diversi.
                    </p>
                </div>

                <!-- Import CSV -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Import da banca</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Importa i movimenti dal CSV della tua banca con rilevamento automatico dei duplicati. Salva i layout personalizzati per ogni istituto con un click.
                    </p>
                </div>

                <!-- Lifestyle Score -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Lifestyle Inflation Score</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Scopri se le tue spese voluttuarie crescono più velocemente delle entrate. Un indicatore unico per capire se stai cadendo nella trappola dell'inflazione del tenore di vita.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Highlight speciale: Asset Allocation -->
    <section class="py-12 sm:py-20 bg-gradient-to-br from-primary-50 to-accent-50" aria-labelledby="allocation-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-16 items-center">
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-sm font-medium mb-4">
                        💼 Patrimonio completo
                    </span>
                    <h2 id="allocation-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-4 sm:mb-6">
                        Dal conto corrente agli investimenti: un unico patrimonio
                    </h2>
                    <p class="text-base sm:text-lg text-surface-600 mb-6 leading-relaxed">
                        FinanzaMente non si limita alle spese quotidiane. Aggiungi il tuo portafoglio di ETF, azioni e crypto per avere la visione completa di quanto vali davvero.
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

    <!-- Come funziona -->
    <section id="come-funziona" class="py-12 sm:py-20 bg-white" aria-labelledby="how-it-works-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10 sm:mb-16">
                <h2 id="how-it-works-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    Operativo in 3 minuti
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Nessuna configurazione complicata, nessuna carta di credito richiesta
                </p>
            </div>

            <div class="max-w-4xl mx-auto space-y-6 sm:space-y-8">
                <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6 bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200">
                    <div class="flex-shrink-0 w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-primary-600 to-primary-800 rounded-full flex items-center justify-center text-white text-xl sm:text-2xl font-bold shadow-lg">
                        1
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Crea il tuo account e completa il profilo</h3>
                        <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                            Registrati gratuitamente e rispondi a poche domande per personalizzare l'app al tuo stile di vita. Nessuna carta di credito richiesta.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6 bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200">
                    <div class="flex-shrink-0 w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-accent-600 to-accent-800 rounded-full flex items-center justify-center text-white text-xl sm:text-2xl font-bold shadow-lg">
                        2
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Aggiungi conti e portafoglio</h3>
                        <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                            Inserisci i tuoi conti correnti, carte, contanti e — se li hai — i tuoi investimenti. Puoi anche importare i movimenti dal CSV della tua banca.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6 bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200">
                    <div class="flex-shrink-0 w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-primary-600 to-primary-800 rounded-full flex items-center justify-center text-white text-xl sm:text-2xl font-bold shadow-lg">
                        3
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Registra e analizza</h3>
                        <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                            Usa la sessione rapida per inserire le spese quotidiane in secondi. La dashboard ti mostrerà subito trend, budget consumati e la tua situazione patrimoniale complessiva.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Perché FinanzaMente -->
    <section class="py-12 sm:py-20 bg-gradient-to-br from-surface-50 to-surface-100" aria-labelledby="benefits-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10 sm:mb-16">
                <h2 id="benefits-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    Perché scegliere FinanzaMente?
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Non è un altro tracker di spese. È la gestione finanziaria completa per chi vuole capire davvero dove sta andando il suo denaro.
                </p>
            </div>

            <div class="max-w-6xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-2xl p-6 border border-surface-200 text-center hover:shadow-soft-md transition-all duration-300">
                    <div class="text-3xl mb-3">🔒</div>
                    <h3 class="font-semibold text-surface-900 mb-2">Privacy totale</h3>
                    <p class="text-sm text-surface-600">I tuoi dati non vengono mai condivisi con terze parti o banche. Zero sincronizzazione esterna.</p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-surface-200 text-center hover:shadow-soft-md transition-all duration-300">
                    <div class="text-3xl mb-3">🇮🇹</div>
                    <h3 class="font-semibold text-surface-900 mb-2">Fatto per l'Italia</h3>
                    <p class="text-sm text-surface-600">Euro, formato italiano, 730 e detrazioni fiscali: pensato per la realtà fiscale italiana.</p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-surface-200 text-center hover:shadow-soft-md transition-all duration-300">
                    <div class="text-3xl mb-3">📱</div>
                    <h3 class="font-semibold text-surface-900 mb-2">Mobile first</h3>
                    <p class="text-sm text-surface-600">Interfaccia ottimizzata per smartphone. Registra una spesa in 5 secondi mentre sei ancora alla cassa.</p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-surface-200 text-center hover:shadow-soft-md transition-all duration-300">
                    <div class="text-3xl mb-3">💸</div>
                    <h3 class="font-semibold text-surface-900 mb-2">Gratis per sempre</h3>
                    <p class="text-sm text-surface-600">Nessun abbonamento obbligatorio, nessuna pubblicità, nessun costo nascosto.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Piani -->
    <section id="piani" class="py-12 sm:py-20 bg-white" aria-labelledby="pricing-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-8 sm:mb-12">
                <h2 id="pricing-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    Semplice e trasparente
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Inizia gratis. Passa a Pro quando sei pronto.
                </p>
            </div>

            @php
                $basePlan = $plans['base'] ?? null;
                $proPlan = $plans['pro'] ?? null;
                $proMonthly = $proPlan ? $proPlan['price_monthly'] : 0;
                $proAnnualMonthly = $proPlan ? $proPlan['price_annual_monthly'] : 0;
                $proAnnualTotal = $proPlan ? $proPlan['price_annual_total'] : 0;
                $discount = $annualDiscountPercent;
            @endphp

            {{-- Toggle mensile/annuale (solo se Pro è disponibile) --}}
            @if($proEnabled && $proPlan)
            <div class="flex justify-center mb-10">
                <div class="inline-flex items-center gap-4 bg-surface-50 rounded-full px-6 py-3 border border-surface-200">
                    <span id="label-monthly" class="text-sm font-medium text-surface-900">Mensile</span>
                    <button
                        type="button"
                        id="billing-toggle"
                        role="switch"
                        aria-checked="false"
                        aria-labelledby="label-monthly label-annual"
                        class="relative inline-flex h-6 w-11 items-center rounded-full bg-surface-200 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                    >
                        <span id="toggle-thumb" class="inline-block h-4 w-4 transform rounded-full bg-white shadow translate-x-1 transition-transform duration-200"></span>
                    </button>
                    <span id="label-annual" class="text-sm font-medium text-surface-400">
                        Annuale
                        <span class="ml-1.5 bg-accent-100 text-accent-700 text-xs font-semibold px-2 py-0.5 rounded-full">-{{ $discount }}%</span>
                    </span>
                </div>
            </div>
            @endif

            <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8 items-stretch">
                <!-- Piano Base -->
                @if($basePlan)
                <div class="bg-white rounded-2xl border-2 border-primary-500 p-6 sm:p-8 shadow-soft-md relative flex flex-col">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="bg-primary-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">Gratuito</span>
                    </div>
                    <div class="text-center mb-6">
                        <h3 class="text-xl font-bold text-surface-900 mb-1">FinanzaMente Base</h3>
                        <div class="text-4xl font-extrabold text-primary-600 my-3">€0</div>
                        <p class="text-sm text-surface-500">Per sempre. Nessuna carta richiesta.</p>
                    </div>
                    <ul class="space-y-3 mb-8 text-sm text-surface-700 flex-1">
                        @foreach($basePlan['features'] as $feature)
                        <li class="flex items-center gap-2"><span class="text-primary-500 font-bold">✓</span> {{ $feature }}</li>
                        @endforeach
                    </ul>
                    @if (Route::has('plan.select'))
                        <a href="{{ route('plan.select') }}?plan=base" class="block w-full text-center py-3 px-6 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors duration-200">
                            Inizia gratis
                        </a>
                    @endif
                </div>
                @endif

                <!-- Piano Pro -->
                @if($proPlan && $proEnabled)
                <div class="bg-gradient-to-b from-accent-600 to-accent-700 rounded-2xl p-6 sm:p-8 shadow-accent relative flex flex-col text-white">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="bg-white text-accent-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">Consigliato</span>
                    </div>
                    <div class="text-center mb-6">
                        <h3 class="text-xl font-bold text-white mb-1">FinanzaMente Pro</h3>
                        <div class="my-3">
                            <div class="flex items-baseline justify-center gap-1">
                                <span id="pro-price" class="text-4xl font-extrabold text-white">
                                    {{ number_format($proMonthly, 2, ',', '.') }} €
                                </span>
                                <span class="text-accent-200 text-sm">/mese</span>
                            </div>
                            <div id="pro-annual-info" class="hidden mt-1 text-center space-y-0.5">
                                <p class="text-accent-200 text-sm">
                                    <span class="line-through">{{ number_format($proMonthly * 12, 2, ',', '.') }} €/anno</span>
                                    <span class="text-white font-semibold ml-1">→ {{ number_format($proAnnualTotal, 2, ',', '.') }} €/anno</span>
                                </p>
                                <p class="text-xs text-accent-100">Risparmi {{ number_format($proMonthly * 12 - $proAnnualTotal, 2, ',', '.') }} €/anno</p>
                            </div>
                        </div>
                        <p class="text-sm text-accent-100">{{ $proPlan['label'] }}</p>
                    </div>
                    <ul class="space-y-3 mb-8 text-sm flex-1">
                        @foreach($proPlan['features'] as $feature)
                        <li class="flex items-center gap-2 text-white"><span class="font-bold">✓</span> {{ $feature }}</li>
                        @endforeach
                    </ul>
                    @if (Route::has('plan.select'))
                        <a id="pro-cta" href="{{ route('plan.select') }}?plan=pro&billing_cycle=monthly" class="block w-full text-center py-3 px-6 bg-white hover:bg-accent-50 text-accent-700 font-semibold rounded-xl transition-colors duration-200">
                            Scegli Pro mensile
                        </a>
                    @endif
                </div>
                @elseif($proPlan && !$proEnabled)
                <!-- Piano Pro — coming soon -->
                <div class="bg-surface-50 rounded-2xl border-2 border-dashed border-surface-300 p-6 sm:p-8 relative opacity-70 flex flex-col">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span class="bg-surface-400 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">Presto disponibile</span>
                    </div>
                    <div class="text-center mb-6">
                        <h3 class="text-xl font-bold text-surface-900 mb-1">FinanzaMente Pro</h3>
                        <div class="text-4xl font-extrabold text-surface-400 my-3">Presto</div>
                        <p class="text-sm text-surface-500">Funzionalità avanzate in arrivo.</p>
                    </div>
                    <ul class="space-y-3 mb-8 text-sm text-surface-500 flex-1">
                        @foreach($proPlan['features'] as $feature)
                        <li class="flex items-center gap-2"><span class="font-bold">✦</span> {{ $feature }}</li>
                        @endforeach
                    </ul>
                    <button disabled class="block w-full text-center py-3 px-6 bg-surface-200 text-surface-400 font-semibold rounded-xl cursor-not-allowed">
                        Presto disponibile
                    </button>
                </div>
                @endif
            </div>
        </div>
    </section>

    @if($proEnabled && $proPlan)
    @push('scripts')
    <script>
    (function() {
        var toggle = document.getElementById('billing-toggle');
        var thumb = document.getElementById('toggle-thumb');
        var labelMonthly = document.getElementById('label-monthly');
        var labelAnnual = document.getElementById('label-annual');
        var proPrice = document.getElementById('pro-price');
        var proAnnualInfo = document.getElementById('pro-annual-info');
        var proCta = document.getElementById('pro-cta');

        if (!toggle) return;

        var isAnnual = false;
        var priceMonthly = @json(number_format($proMonthly, 2, ',', '.') . ' €');
        var priceAnnualMonthly = @json(number_format($proAnnualMonthly, 2, ',', '.') . ' €');
        var baseSelectUrl = @json(route('plan.select'));

        toggle.addEventListener('click', function() {
            isAnnual = !isAnnual;
            toggle.setAttribute('aria-checked', isAnnual ? 'true' : 'false');
            thumb.style.transform = isAnnual ? 'translateX(1.5rem)' : 'translateX(0.25rem)';
            toggle.style.backgroundColor = isAnnual ? 'var(--color-primary-500, #4f4ce5)' : '';
            labelMonthly.style.color = isAnnual ? '' : 'var(--color-surface-900, #0f172a)';
            labelAnnual.style.color = isAnnual ? 'var(--color-surface-900, #0f172a)' : '';

            if (proPrice) {
                proPrice.textContent = isAnnual ? priceAnnualMonthly : priceMonthly;
            }
            if (proAnnualInfo) {
                proAnnualInfo.classList.toggle('hidden', !isAnnual);
            }
            if (proCta) {
                proCta.href = baseSelectUrl + '?plan=pro&billing_cycle=' + (isAnnual ? 'annual' : 'monthly');
                proCta.textContent = isAnnual ? 'Scegli Pro annuale' : 'Scegli Pro mensile';
            }
        });
    })();
    </script>
    @endpush
    @endif

    <!-- CTA finale -->
    <section class="py-12 sm:py-20 bg-gradient-to-br from-primary-600 to-primary-800 text-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-3 sm:mb-4">
                    Inizia oggi a capire davvero le tue finanze
                </h2>
                <p class="text-base sm:text-lg text-primary-100 mb-6 sm:mb-8 max-w-2xl mx-auto">
                    Registrati in 30 secondi. Nessuna carta di credito, nessun abbonamento, nessuna connessione bancaria richiesta.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-primary-700 bg-white hover:bg-surface-50 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200">
                            Crea il tuo account gratis
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                    @endif
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-medium text-white hover:text-primary-100 transition-colors">
                            Hai già un account? Accedi
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                    window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
                }
            });
        });
    </script>
@endpush
