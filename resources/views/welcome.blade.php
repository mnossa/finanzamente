@extends('layouts.guest')

{{-- I meta tag sono impostati in WelcomeController tramite artesaos/seotools --}}

@section('content')
    <!-- Hero -->
    <section class="relative pt-24 sm:pt-32 pb-12 sm:pb-20 overflow-hidden" aria-labelledby="hero-title">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-50 via-white to-accent-50 opacity-60" aria-hidden="true"></div>
        <div class="absolute top-0 right-0 -translate-y-12 translate-x-12 w-72 h-72 bg-primary-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 translate-y-12 -translate-x-12 w-72 h-72 bg-accent-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <h1 id="hero-title" class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-surface-900 leading-tight mb-4 sm:mb-6">
                    Le spese di oggi e il patrimonio di domani,
                    <span class="bg-gradient-to-r from-primary-600 to-primary-800 bg-clip-text text-transparent">nello stesso posto</span>
                </h1>

                <p class="text-base sm:text-lg md:text-xl text-surface-600 mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed">
                    Finanzamente è l'app di finanza personale per chi vive in Italia. Registri i movimenti, imposti i budget, segui investimenti e detrazioni. Decidi tu cosa tracciare: ai calcoli pensa l'app.
                </p>

                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center mb-8 sm:mb-12">
                    @if (!$preLaunchMode && Route::has('plan.select'))
                        <a href="{{ route('plan.select') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-white bg-gradient-to-r from-accent-600 to-accent-700 hover:from-accent-700 hover:to-accent-800 rounded-xl shadow-accent hover:shadow-accent-lg transition-all duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Inizia gratis
                        </a>
                    @endif
                    <a href="#funzionalita" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-medium text-primary-700 bg-white hover:bg-surface-50 rounded-xl border-2 border-primary-200 hover:border-primary-300 transition-all duration-200">
                        Guarda cosa include
                    </a>
                </div>

                <!-- Micro-prove concrete -->
                <ul class="flex flex-wrap items-center justify-center gap-4 sm:gap-8 text-sm text-surface-600">
                    <li class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-accent-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        Piano gratuito senza scadenza
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-accent-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        Nessuna carta richiesta
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-accent-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        In italiano, in euro
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <!-- I quattro pilastri -->
    <section id="funzionalita" class="py-12 sm:py-20 bg-white" aria-labelledby="features-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10 sm:mb-16">
                <h2 id="features-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    Quattro aree, un unico quadro
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Parti da quella che ti serve adesso. Le altre restano lì, pronte per quando ne avrai bisogno.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8 max-w-6xl mx-auto">
                <!-- Registra -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200">
                    <div class="w-12 h-12 bg-gradient-to-br from-surface-500 to-surface-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Registra senza perderci tempo</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed mb-4">
                        Un movimento si salva in pochi secondi, dal telefono, mentre sei ancora alla cassa.
                    </p>
                    <ul class="space-y-2.5 text-sm sm:text-base text-surface-700">
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Form rapido con le categorie che usi più spesso già a portata di dito
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Uno storico lo carichi da un file CSV o Excel, con la mappatura delle colonne che resta salvata
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Affitto, abbonamenti e stipendio si ripresentano da soli alla scadenza
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Doppioni e ricorrenze che non hai ancora impostato te li segnala l'app
                        </li>
                    </ul>
                </div>

                <!-- Pianifica -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200">
                    <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Decidi prima, non a fine mese</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed mb-4">
                        I limiti li scegli tu all'inizio del mese, poi l'app ti dice quanto ne resta.
                    </p>
                    <ul class="space-y-2.5 text-sm sm:text-base text-surface-700">
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Budget per categoria, aggiornati a ogni movimento che registri
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Obiettivi di risparmio con la percentuale già raggiunta
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Debiti e crediti con scadenze, TAN e TAEG, e i rimborsi collegati alla spesa originale
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Un avviso quando un budget si avvicina al limite o una scadenza è dietro l'angolo
                        </li>
                    </ul>
                </div>

                <!-- Patrimonio -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Guarda oltre il mese corrente</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed mb-4">
                        Liquidità e investimenti confluiscono in un solo patrimonio, che puoi seguire nel tempo.
                    </p>
                    <ul class="space-y-2.5 text-sm sm:text-base text-surface-700">
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Conti di ogni tipo: liquidità, carte, contanti, buoni pasto, deposito
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            <span>ETF, azioni, obbligazioni, crypto e piani di accumulo <span class="align-middle inline-flex items-center px-1.5 py-0.5 rounded bg-accent-100 text-accent-700 text-xs font-semibold uppercase tracking-wide">Pro</span></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            <span>Asset allocation con indice di rischio da 1 a 7 <span class="align-middle inline-flex items-center px-1.5 py-0.5 rounded bg-accent-100 text-accent-700 text-xs font-semibold uppercase tracking-wide">Pro</span></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Andamento del patrimonio netto mese per mese
                        </li>
                    </ul>
                </div>

                <!-- Insieme -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200">
                    <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-pink-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Conti chiari anche in due</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed mb-4">
                        Coppie, famiglie e coinquilini condividono un nucleo, ognuno con la sua parte.
                    </p>
                    <ul class="space-y-2.5 text-sm sm:text-base text-surface-700">
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Portafoglio comune, se mettete tutto insieme
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Oppure conti separati, con percentuali di ripartizione su chi contribuisce a cosa
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            Sulle spese fisse l'app suggerisce a chi tocca questo mese
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-accent-600 font-bold mt-0.5" aria-hidden="true">✓</span>
                            <span>I movimenti che segni come privati restano solo tuoi. Invitare altri membri richiede <span class="align-middle inline-flex items-center px-1.5 py-0.5 rounded bg-accent-100 text-accent-700 text-xs font-semibold uppercase tracking-wide">Pro</span></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Patrimonio e asset allocation -->
    <section class="py-12 sm:py-20 bg-gradient-to-br from-primary-50 to-accent-50" aria-labelledby="allocation-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-16 items-center">
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-sm font-medium mb-4">
                        Patrimonio
                    </span>
                    <h2 id="allocation-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-4 sm:mb-6">
                        Quanto vali, non solo quanto spendi
                    </h2>
                    <p class="text-base sm:text-lg text-surface-600 mb-6 leading-relaxed">
                        Quasi tutti gli strumenti di finanza personale si fermano alle uscite. Qui la liquidità e il portafoglio investimenti stanno nello stesso quadro, con la composizione e il rischio sempre in vista.
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span class="text-sm sm:text-base text-surface-700">Composizione per asset class: azionario, obbligazionario, liquidità, crypto, materie prime</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span class="text-sm sm:text-base text-surface-700">Indice di rischio da 1 a 7 calcolato su come è composto il tuo patrimonio, non su un questionario</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-blue-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span class="text-sm sm:text-base text-surface-700">Piani di accumulo con proiezioni, e analisi di redditività per scelte concrete come fotovoltaico o auto elettrica</span>
                        </li>
                    </ul>
                    <p class="mt-6 text-sm text-surface-500">
                        La sezione investimenti fa parte del piano Pro. Se non investi, resta spenta.
                    </p>
                </div>

                <!-- Esempio visivo asset allocation -->
                <div class="relative bg-white rounded-2xl p-6 sm:p-8 shadow-soft-lg border border-surface-200">
                    <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-5">Esempio asset allocation</p>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-blue-500 flex-shrink-0" aria-hidden="true"></div>
                            <div class="flex-1">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-surface-800">Azionario</span>
                                    <span class="text-surface-600">52%</span>
                                </div>
                                <div class="w-full h-2 bg-surface-100 rounded-full overflow-hidden" aria-hidden="true">
                                    <div class="h-full bg-blue-500 rounded-full" style="width: 52%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-cyan-500 flex-shrink-0" aria-hidden="true"></div>
                            <div class="flex-1">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-surface-800">Liquidità</span>
                                    <span class="text-surface-600">28%</span>
                                </div>
                                <div class="w-full h-2 bg-surface-100 rounded-full overflow-hidden" aria-hidden="true">
                                    <div class="h-full bg-cyan-500 rounded-full" style="width: 28%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-violet-500 flex-shrink-0" aria-hidden="true"></div>
                            <div class="flex-1">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-surface-800">Crypto</span>
                                    <span class="text-surface-600">12%</span>
                                </div>
                                <div class="w-full h-2 bg-surface-100 rounded-full overflow-hidden" aria-hidden="true">
                                    <div class="h-full bg-violet-500 rounded-full" style="width: 12%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-surface-500 flex-shrink-0" aria-hidden="true"></div>
                            <div class="flex-1">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-surface-800">Obbligazionario</span>
                                    <span class="text-surface-600">8%</span>
                                </div>
                                <div class="w-full h-2 bg-surface-100 rounded-full overflow-hidden" aria-hidden="true">
                                    <div class="h-full bg-surface-500 rounded-full" style="width: 8%"></div>
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
                    <p class="mt-4 text-xs text-surface-500">Dati di esempio a scopo illustrativo.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Dashboard componibile -->
    <section class="py-12 sm:py-20 bg-white" aria-labelledby="dashboard-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-16 items-center">
                <!-- Esempio visivo widget -->
                <div class="order-2 lg:order-1 space-y-4">
                    <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-soft-lg border border-surface-200">
                        <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-3">Quota risparmio</p>
                        <p class="text-3xl font-bold text-surface-900 mb-1">23,4%</p>
                        <p class="text-sm text-accent-700">+2,1 punti sul trimestre precedente</p>
                        <p class="mt-3 pt-3 border-t border-surface-100 font-mono text-xs text-surface-500 break-all">
                            (entrate - uscite) / entrate
                        </p>
                    </div>
                    <div class="bg-white rounded-2xl p-5 sm:p-6 shadow-soft-lg border border-surface-200">
                        <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-4">Spese fisse sul netto</p>
                        <div class="flex items-end gap-2 h-24" aria-hidden="true">
                            <div class="flex-1 bg-primary-300 rounded-t" style="height: 55%"></div>
                            <div class="flex-1 bg-primary-300 rounded-t" style="height: 70%"></div>
                            <div class="flex-1 bg-primary-300 rounded-t" style="height: 48%"></div>
                            <div class="flex-1 bg-primary-300 rounded-t" style="height: 62%"></div>
                            <div class="flex-1 bg-primary-300 rounded-t" style="height: 41%"></div>
                            <div class="flex-1 bg-primary-600 rounded-t" style="height: 52%"></div>
                        </div>
                        <p class="mt-3 text-xs text-surface-500">Ultimi 6 mesi, in evidenza quello in corso</p>
                    </div>
                    <p class="text-xs text-surface-500">Dati di esempio a scopo illustrativo.</p>
                </div>

                <div class="order-1 lg:order-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-primary-100 text-primary-700 text-sm font-medium mb-4">
                        Su misura
                    </span>
                    <h2 id="dashboard-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-4 sm:mb-6">
                        La dashboard te la costruisci tu
                    </h2>
                    <p class="text-base sm:text-lg text-surface-600 mb-6 leading-relaxed">
                        Nessuno spende come te, quindi nessuna schermata preconfezionata può andare bene per tutti. Scrivi l'indicatore che ti interessa come una formula, scegli come visualizzarlo e lo appunti dove ti serve.
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-primary-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-primary-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span class="text-sm sm:text-base text-surface-700">Numeri singoli, linee, barre, torte e tabelle, costruiti sulle tue categorie e sui tuoi conti</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-primary-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-primary-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span class="text-sm sm:text-base text-surface-700">Le tue variabili — l'affitto, il netto mensile, la quota da mettere via — riutilizzabili in più formule</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-primary-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-primary-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span class="text-sm sm:text-base text-surface-700">Una raccolta di modelli pronti, se preferisci partire da qualcosa che già funziona</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex-shrink-0 w-6 h-6 bg-primary-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-primary-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span class="text-sm sm:text-base text-surface-700">Più viste salvate: una per il mese in corso, una per il lungo periodo</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Dettagli distintivi -->
    <section class="py-12 sm:py-20 bg-gradient-to-br from-surface-50 to-surface-100" aria-labelledby="details-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10 sm:mb-16">
                <h2 id="details-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    I dettagli che fanno la differenza
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Alcuni contano solo se vivi in Italia. Altri difficilmente li trovi altrove.
                </p>
            </div>

            <div class="max-w-6xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-2xl p-6 border border-surface-200">
                    <h3 class="font-semibold text-surface-900 mb-2">
                        Detrazioni e 730
                        <span class="ml-1 align-middle inline-flex items-center px-1.5 py-0.5 rounded bg-accent-100 text-accent-700 text-xs font-semibold uppercase tracking-wide">Pro</span>
                    </h3>
                    <p class="text-sm text-surface-600">
                        Marchi le spese detraibili durante l'anno ed esporti tutto in PDF, allegati compresi, quando arriva il momento della dichiarazione.
                    </p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-surface-200">
                    <h3 class="font-semibold text-surface-900 mb-2">Buoni pasto veri</h3>
                    <p class="text-sm text-surface-600">
                        Gestiti a lotti con il loro valore unitario, perché non sono contanti e non si comportano come gli altri conti.
                    </p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-surface-200">
                    <h3 class="font-semibold text-surface-900 mb-2">Formati di casa</h3>
                    <p class="text-sm text-surface-600">
                        Euro, date in giorno/mese/anno, virgola per i decimali. Se ti servono altre valute, il cambio lo applica l'app.
                    </p>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-surface-200">
                    <h3 class="font-semibold text-surface-900 mb-2">
                        Lifestyle Inflation Score
                        <span class="ml-1 align-middle inline-flex items-center px-1.5 py-0.5 rounded bg-accent-100 text-accent-700 text-xs font-semibold uppercase tracking-wide">Pro</span>
                    </h3>
                    <p class="text-sm text-surface-600">
                        Un indicatore che confronta la crescita delle spese non essenziali con quella delle entrate. Utile quando lo stipendio sale e i risparmi no.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Simulazioni aperte -->
    <section class="py-12 sm:py-20 bg-white" aria-labelledby="simulations-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <h2 id="simulations-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    I simulatori puoi provarli adesso
                </h2>
                <p class="text-base sm:text-lg text-surface-600 mb-8 max-w-2xl mx-auto leading-relaxed">
                    Interesse composto, fondo di emergenza, indipendenza finanziaria, scenari di crisi. Sono aperti a tutti, senza account. Se poi ti registri, salvi gli scenari e li ricalcoli sui tuoi numeri.
                </p>
                <a href="{{ route('simulations.public') }}" class="inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-white bg-gradient-to-r from-primary-600 to-primary-800 hover:from-primary-700 hover:to-primary-900 rounded-xl shadow-soft-lg transition-all duration-200">
                    Apri le simulazioni
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            </div>
        </div>
    </section>

    <!-- Come funziona -->
    <section id="come-funziona" class="py-12 sm:py-20 bg-gradient-to-br from-primary-50 to-accent-50" aria-labelledby="how-it-works-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10 sm:mb-16">
                <h2 id="how-it-works-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    Dal primo accesso ai primi grafici
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Tre passaggi. Niente configurazione infinita prima di poter usare l'app.
                </p>
            </div>

            <ol class="max-w-4xl mx-auto space-y-6 sm:space-y-8">
                <li class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6 bg-white rounded-2xl p-6 sm:p-8 border border-surface-200">
                    <span class="flex-shrink-0 w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-primary-600 to-primary-800 rounded-full flex items-center justify-center text-white text-xl sm:text-2xl font-bold shadow-lg" aria-hidden="true">
                        1
                    </span>
                    <div>
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Crea l'account</h3>
                        <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                            Poche domande sulle tue abitudini servono ad accendere solo le sezioni che ti riguardano. Nessuna carta di credito richiesta.
                        </p>
                    </div>
                </li>

                <li class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6 bg-white rounded-2xl p-6 sm:p-8 border border-surface-200">
                    <span class="flex-shrink-0 w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-accent-600 to-accent-800 rounded-full flex items-center justify-center text-white text-xl sm:text-2xl font-bold shadow-lg" aria-hidden="true">
                        2
                    </span>
                    <div>
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Dichiara da dove parti</h3>
                        <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                            Aggiungi i conti che usi con il loro saldo di partenza e, se li segui, gli investimenti. Se hai già uno storico da qualche parte, lo carichi da un file CSV o Excel.
                        </p>
                    </div>
                </li>

                <li class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6 bg-white rounded-2xl p-6 sm:p-8 border border-surface-200">
                    <span class="flex-shrink-0 w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-primary-600 to-primary-800 rounded-full flex items-center justify-center text-white text-xl sm:text-2xl font-bold shadow-lg" aria-hidden="true">
                        3
                    </span>
                    <div>
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Registra e leggi i numeri</h3>
                        <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                            Dopo qualche settimana di movimenti la dashboard ha abbastanza materiale per mostrarti i trend, i budget consumati e il patrimonio. Da lì decidi cosa cambiare.
                        </p>
                    </div>
                </li>
            </ol>
        </div>
    </section>

    <!-- FAQ -->
    <section class="py-12 sm:py-20 bg-white" aria-labelledby="faq-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10 sm:mb-12">
                <h2 id="faq-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    Domande frequenti
                </h2>
            </div>

            <div class="max-w-3xl mx-auto space-y-3">
                @foreach ($faqs as $faq)
                    <details class="group bg-surface-50 rounded-2xl border border-surface-200 overflow-hidden">
                        <summary class="flex items-center justify-between gap-4 cursor-pointer p-5 sm:p-6 font-semibold text-surface-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-inset">
                            {{ $faq['question'] }}
                            <svg class="w-5 h-5 flex-shrink-0 text-surface-400 transition-transform duration-200 group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </summary>
                        <p class="px-5 sm:px-6 pb-5 sm:pb-6 text-sm sm:text-base text-surface-600 leading-relaxed">
                            {{ $faq['answer'] }}
                        </p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    @include('partials.landing.pricing', ['targetId' => 'home', 'waitlistEnabled' => $waitlistEnabled])

    <!-- CTA finale -->
    <section class="py-12 sm:py-20 bg-gradient-to-br from-primary-600 to-primary-800 text-white" aria-labelledby="final-cta-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <h2 id="final-cta-title" class="text-2xl sm:text-3xl md:text-4xl font-bold mb-3 sm:mb-4">
                    Inizia dal prossimo movimento
                </h2>
                <p class="text-base sm:text-lg text-primary-100 mb-6 sm:mb-8 max-w-2xl mx-auto">
                    L'account lo crei in un minuto e sul piano gratuito puoi restare quanto vuoi.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center">
                    @if (!$preLaunchMode && Route::has('register'))
                        <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-primary-700 bg-white hover:bg-surface-50 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200">
                            Crea un account gratuito
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
