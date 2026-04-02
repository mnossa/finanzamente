@extends('layouts.guest')

@section('content')
    <!-- Hero Section -->
    <section class="relative pt-24 sm:pt-32 pb-12 sm:pb-20 overflow-hidden" aria-labelledby="hero-title">
        <div class="absolute inset-0 bg-gradient-to-br from-sky-50 via-white to-cyan-50 opacity-70" aria-hidden="true"></div>
        <div class="absolute top-0 right-0 -translate-y-12 translate-x-12 w-72 h-72 bg-sky-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 translate-y-12 -translate-x-12 w-72 h-72 bg-cyan-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <span class="inline-flex items-center px-4 py-1.5 rounded-full bg-sky-100 text-sky-700 text-sm font-semibold mb-6">
                    ⚡ Per tech-savvy
                </span>
                <h1 id="hero-title" class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-surface-900 leading-tight mb-4 sm:mb-6">
                    Registra le spese direttamente
                    <span class="bg-gradient-to-r from-sky-600 to-cyan-600 bg-clip-text text-transparent">dal tuo Telegram</span>
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-surface-600 mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed">
                    Nessuna app da aprire, nessun form da compilare. Scrivi la spesa al bot Telegram, la trovi già categorizzata nella tua dashboard. FinanzaMente Pro per chi vuole velocità e automazione.
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
                        Vedi come funziona
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Highlight Telegram bot -->
    <section id="funzionalita" class="py-12 sm:py-20 bg-gradient-to-br from-sky-50 to-cyan-50" aria-labelledby="telegram-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-16 items-center">
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-sky-100 text-sky-700 text-sm font-medium mb-4">
                        ✈️ Bot Telegram
                    </span>
                    <h2 id="telegram-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-4 sm:mb-6">
                        La tua contabilità personale in una chat Telegram
                    </h2>
                    <p class="text-base sm:text-lg text-surface-600 mb-6 leading-relaxed">
                        Colleghi il tuo account FinanzaMente Pro al bot Telegram. Scrivi "Caffè 1.50 @BancaIntesa #Ristoranti" e la transazione è registrata. Puoi anche inviare una foto dello scontrino con la caption.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-sky-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-sky-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Sintassi naturale: <code class="bg-surface-100 px-1.5 py-0.5 rounded text-xs font-mono">Caffè 1.50</code>, <code class="bg-surface-100 px-1.5 py-0.5 rounded text-xs font-mono">@BancaIntesa</code>, <code class="bg-surface-100 px-1.5 py-0.5 rounded text-xs font-mono">#Categoria</code></p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-sky-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-sky-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Comandi rapidi: <code class="bg-surface-100 px-1.5 py-0.5 rounded text-xs font-mono">/saldo</code> per i saldi, <code class="bg-surface-100 px-1.5 py-0.5 rounded text-xs font-mono">/ultime</code> per le ultime 5 transazioni</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-sky-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-sky-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Foto dello scontrino con caption — la transazione viene aggiunta con l'allegato</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-sky-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-sky-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Data personalizzabile: <code class="bg-surface-100 px-1.5 py-0.5 rounded text-xs font-mono">Spesa 25/03</code> o <code class="bg-surface-100 px-1.5 py-0.5 rounded text-xs font-mono">Benzina 15/01/2025</code></p>
                        </div>
                    </div>
                </div>

                <!-- Visual Telegram mock -->
                <div class="relative bg-white rounded-2xl p-6 sm:p-8 shadow-soft-lg border border-surface-200">
                    <div class="flex items-center gap-3 mb-5 pb-4 border-b border-surface-100">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-sky-400 to-cyan-600 flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-surface-900">FinanzaMente Bot</p>
                            <p class="text-xs text-sky-600">● online</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-end">
                            <div class="bg-sky-500 text-white text-sm rounded-2xl rounded-tr-sm px-4 py-2 max-w-xs">
                                Caffè 1.50 @Contante #Ristoranti
                            </div>
                        </div>
                        <div class="flex justify-start">
                            <div class="bg-surface-100 text-surface-800 text-sm rounded-2xl rounded-tl-sm px-4 py-2 max-w-xs">
                                ✅ Registrata: <strong>Caffè €1,50</strong><br>
                                <span class="text-xs text-surface-500">Conto: Contante · Categoria: Ristoranti</span>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <div class="bg-sky-500 text-white text-sm rounded-2xl rounded-tr-sm px-4 py-2 max-w-xs">
                                /saldo
                            </div>
                        </div>
                        <div class="flex justify-start">
                            <div class="bg-surface-100 text-surface-800 text-sm rounded-2xl rounded-tl-sm px-4 py-2 max-w-xs">
                                💰 <strong>I tuoi saldi:</strong><br>
                                <span class="text-xs">Conto principale: €3.420,50</span><br>
                                <span class="text-xs">Contante: €145,00</span><br>
                                <span class="text-xs">Carta credito: €-280,00</span>
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
                    Per chi vuole le finanze automatizzate
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Integrazione tecnica avanzata e gestione intelligente dei dati in arrivo
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 max-w-6xl mx-auto">
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-sky-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-sky-500 to-sky-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/>
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Integrazione Telegram</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Collega il tuo account al bot Telegram e registra spese, visualizza saldi e gestisci transazioni senza aprire l'app.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-cyan-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-cyan-500 to-cyan-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Inbox intelligente</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Le transazioni inviate via Telegram atterrano nell'inbox per la revisione. Conferma, modifica o rifiuta prima che entrino nel registro definitivo.
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
                        Sessione rapida nell'app per inserire più transazioni di fila. Alternativa al bot Telegram per chi preferisce l'interfaccia grafica.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-amber-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Import automatico</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Importa il CSV della tua banca con layout salvati che rilevano automaticamente le colonne. Zero configurazione dopo il primo setup.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Tag per transazioni</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Organizza le transazioni con tag liberi. Usa <code class="bg-surface-100 px-1 py-0.5 rounded text-xs font-mono">#tag</code> direttamente dal bot Telegram per categorizzare al volo.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-blue-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Dashboard completa</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Tutti i dati inseriti via Telegram appaiono in tempo reale nella dashboard. Grafici, trend e analisi sempre aggiornati.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Sezione prezzi -->
    @php $targetId = 'tech-savvy'; @endphp
    @include('partials.landing.pricing')

    <!-- CTA finale -->
    @include('partials.landing.cta-finale', [
        'ctaTitle' => 'Inizia a gestire le finanze dal tuo Telegram',
        'ctaSubtitle' => 'Registrati gratis e collega subito il bot Telegram. Inserisci la prima spesa in 10 secondi senza aprire l\'app.',
    ])
@endsection
