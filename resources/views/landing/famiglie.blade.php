@extends('layouts.guest')

@section('content')
    <!-- Hero Section -->
    <section class="relative pt-24 sm:pt-32 pb-12 sm:pb-20 overflow-hidden" aria-labelledby="hero-title">
        <div class="absolute inset-0 bg-gradient-to-br from-pink-50 via-white to-rose-50 opacity-70" aria-hidden="true"></div>
        <div class="absolute top-0 right-0 -translate-y-12 translate-x-12 w-72 h-72 bg-pink-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 translate-y-12 -translate-x-12 w-72 h-72 bg-rose-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <span class="inline-flex items-center px-4 py-1.5 rounded-full bg-pink-100 text-pink-700 text-sm font-semibold mb-6">
                    👨‍👩‍👧 Per famiglie e coppie
                </span>
                <h1 id="hero-title" class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-surface-900 leading-tight mb-4 sm:mb-6">
                    Gestisci le finanze di famiglia
                    <span class="bg-gradient-to-r from-pink-600 to-rose-700 bg-clip-text text-transparent">senza conflitti e senza sorprese</span>
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-surface-600 mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed">
                    Uno spazio condiviso per gestire entrate, uscite e obiettivi di coppia o famiglia. Ogni membro ha il suo ruolo, ogni conto è chiaro — nessuna discussione sui soldi a fine mese.
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

    <!-- Highlight household condiviso -->
    <section id="funzionalita" class="py-12 sm:py-20 bg-gradient-to-br from-pink-50 to-rose-50" aria-labelledby="household-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-16 items-center">
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-pink-100 text-pink-700 text-sm font-medium mb-4">
                        🏠 Household condiviso
                    </span>
                    <h2 id="household-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-4 sm:mb-6">
                        Un nucleo familiare, tante persone, zero confusione
                    </h2>
                    <p class="text-base sm:text-lg text-surface-600 mb-6 leading-relaxed">
                        Crea un household condiviso con Pro e invita il tuo partner, i familiari o i coinquilini. Ognuno gestisce le proprie transazioni, ma tutti vedono il quadro completo.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-pink-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-pink-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Invita i membri con un link — accettano con il loro account e iniziano subito a contribuire</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-pink-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-pink-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Ruoli e permessi differenziati: admin, membro attivo o visualizzatore</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-pink-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-pink-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Trasferimenti tra household diversi per chi gestisce più nuclei familiari (es. famiglia allargata)</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-pink-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-pink-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Household illimitati: puoi gestire casa, affitto studenti e famiglia di origine separatamente</p>
                        </div>
                    </div>
                </div>

                <!-- Visual household mock -->
                <div class="relative bg-white rounded-2xl p-6 sm:p-8 shadow-soft-lg border border-surface-200">
                    <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-5">Household "Casa Rossi"</p>
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center gap-3 p-3 bg-surface-50 rounded-xl">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-pink-400 to-pink-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">LR</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-surface-900 truncate">Laura Rossi</p>
                                <p class="text-xs text-surface-500">Admin · Ha aggiunto 3 transazioni oggi</p>
                            </div>
                            <span class="text-xs bg-pink-100 text-pink-700 font-semibold px-2 py-0.5 rounded-full flex-shrink-0">Admin</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-surface-50 rounded-xl">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">MR</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-surface-900 truncate">Marco Rossi</p>
                                <p class="text-xs text-surface-500">Membro · Ha aggiunto 1 transazione</p>
                            </div>
                            <span class="text-xs bg-blue-100 text-blue-700 font-semibold px-2 py-0.5 rounded-full flex-shrink-0">Membro</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-surface-50 rounded-xl opacity-70">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-surface-300 to-surface-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-surface-600">Invita un membro…</p>
                                <p class="text-xs text-surface-400">Invia il link di invito</p>
                            </div>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-surface-100">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-xs text-surface-500 mb-1">Spese del mese</p>
                                <p class="text-xl font-bold text-surface-900">€ 2.840,00</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-surface-500 mb-1">Saldo conti condivisi</p>
                                <p class="text-xl font-bold text-accent-600">€ 8.530,00</p>
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
                    Per famiglie che vogliono chiarezza, non discussioni
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Strumenti Pro per gestire le finanze condivise in modo trasparente e senza attriti
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 max-w-6xl mx-auto">
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-pink-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-pink-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Household multi-membro</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Invita partner, familiari o coinquilini. Ognuno gestisce le proprie transazioni, tutti vedono il quadro completo dell'household.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-pink-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-rose-500 to-rose-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Trasferimenti inter-household</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Gestisci più nuclei familiari e trasferisci denaro tra di essi in modo tracciabile. Perfetto per famiglie allargate o coinquilini con conti separati.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-red-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Rimborsi illimitati</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Gestisci rimborsi tra membri della famiglia senza limiti. Collega ogni rimborso alla spesa originale e chiudi i conti in sospeso.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-amber-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Budget familiare condiviso</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Imposta budget mensili per categoria visibili a tutti i membri. Monitoraggio in tempo reale di quanto ha speso ciascun membro.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-violet-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-violet-500 to-violet-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Spese ricorrenti condivise</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Affitto, utenze, abbonamenti streaming: automatizza le ricorrenti condivise e non perdere mai traccia di chi ha pagato cosa.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Obiettivi finanziari condivisi</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Crea obiettivi illimitati condivisi: vacanza, fondo emergenze, acquisto casa. Contribuite insieme e monitorate i progressi.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Sezione prezzi -->
    @php $targetId = 'famiglie'; @endphp
    @include('partials.landing.pricing')

    <!-- CTA finale -->
    @include('partials.landing.cta-finale', [
        'ctaTitle' => 'Inizia a gestire le finanze di famiglia con chiarezza',
        'ctaSubtitle' => 'Registrati gratis, crea il tuo household e invita i tuoi familiari. Passa a Pro per sbloccare household condivisi e tutte le funzionalità per famiglie.',
    ])
@endsection
