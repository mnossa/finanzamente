@extends('layouts.landing-minimal')
@php $landingPage = 'lavoratori'; @endphp

@section('content')
    {{-- HERO --}}
    <section class="relative min-h-[85vh] flex items-center overflow-hidden bg-gradient-to-br from-teal-50 via-white to-emerald-50" aria-labelledby="hero-title">
        <div class="absolute top-0 right-0 w-96 h-96 bg-teal-200 rounded-full blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-emerald-200 rounded-full blur-3xl opacity-20 translate-y-1/2 -translate-x-1/2" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
            <div class="max-w-2xl mx-auto text-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-teal-100 text-teal-700 text-sm font-semibold mb-6">
                    🇮🇹 Per lavoratori dipendenti
                </span>
                <h1 id="hero-title" class="text-4xl sm:text-5xl md:text-6xl font-bold text-surface-900 leading-tight mb-5">
                    Non dimenticare più
                    <span class="bg-gradient-to-r from-teal-600 to-emerald-600 bg-clip-text text-transparent">una sola spesa detraibile</span>
                </h1>
                <p class="text-lg sm:text-xl text-surface-600 mb-8 leading-relaxed">
                    Marca le spese durante l'anno: mediche, scolastiche, ristrutturazioni, mutuo. Quando serve la dichiarazione, hai tutto pronto in PDF per il CAF — senza frenetiche ricerche di scontrini.
                </p>
                @include('partials.landing.pro-cta-button', [
                    'label'         => 'Abbonati a Pro — traccia le spese detraibili',
                    'umamiEvent'    => 'landing-cta-lavoratori',
                    'umamiPosition' => 'hero',
                    'classes'       => 'inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-accent-600 hover:bg-accent-700 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200',
                ])
                <div class="flex flex-wrap justify-center gap-x-5 gap-y-2 mt-5 text-sm text-surface-500">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        €2,99/mese
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Nessun conto bancario da collegare
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Disdici quando vuoi
                    </span>
                </div>
                @if (Route::has('login'))
                    <p class="mt-3 text-sm text-surface-500">
                        Hai già un account?
                        <a href="{{ route('login') }}" class="text-primary-600 hover:text-primary-700 font-medium underline">Accedi</a>
                    </p>
                @endif
            </div>
        </div>
    </section>

    {{-- 3 BENEFITS --}}
    <section class="py-12 sm:py-16 bg-white" aria-label="Funzionalità principali">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto grid grid-cols-1 sm:grid-cols-3 gap-6 sm:gap-8">
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">🏥</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Tag detrazioni su ogni spesa</h3>
                    <p class="text-sm text-surface-600">Sanitaria, istruzione, mutuo, ristrutturazione: ogni spesa detraibile etichettata al momento stesso.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">📅</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Tracciamento durante l'anno intero</h3>
                    <p class="text-sm text-surface-600">Non solo a marzo. Marchi le spese quando le fai — così a fine anno non dimentichi nulla.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">📄</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Export PDF per il CAF</h3>
                    <p class="text-sm text-surface-600">Un documento completo con importi, date e categorie: consegnalo al CAF o al commercialista.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- PROOF VISUAL --}}
    <section class="py-12 sm:py-16 bg-surface-50" aria-label="Esempio riepilogo detrazioni">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-12 items-center">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-surface-900 mb-4">
                        Le spese detraibili organizzate durante l'anno, non ad aprile
                    </h2>
                    <p class="text-surface-600 leading-relaxed">
                        Ogni volta che fai una visita medica, paghi la retta scolastica o sostieni una spesa per la casa, la marchi su Finanzamente. <strong>A fine anno hai già tutto il materiale pronto</strong> — un <strong>export PDF chiaro e completo per il CAF</strong>.
                    </p>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-soft-lg border border-surface-200">
                    <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-4">Riepilogo detrazioni 2025</p>
                    <div class="space-y-3 mb-5">
                        <div class="flex items-center justify-between py-2 border-b border-surface-100">
                            <div class="flex items-center gap-2">
                                <span class="text-lg" aria-hidden="true">🏥</span>
                                <div>
                                    <p class="text-sm font-medium text-surface-800">Spese sanitarie</p>
                                    <p class="text-xs text-surface-500">19% su importo &gt; €129,11</p>
                                </div>
                            </div>
                            <span class="text-sm font-bold text-surface-900">€ 842</span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-surface-100">
                            <div class="flex items-center gap-2">
                                <span class="text-lg" aria-hidden="true">📚</span>
                                <div>
                                    <p class="text-sm font-medium text-surface-800">Istruzione e formazione</p>
                                    <p class="text-xs text-surface-500">19% detraibile</p>
                                </div>
                            </div>
                            <span class="text-sm font-bold text-surface-900">€ 350</span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <div class="flex items-center gap-2">
                                <span class="text-lg" aria-hidden="true">🏠</span>
                                <div>
                                    <p class="text-sm font-medium text-surface-800">Interessi mutuo</p>
                                    <p class="text-xs text-surface-500">19% su max €4.000</p>
                                </div>
                            </div>
                            <span class="text-sm font-bold text-surface-900">€ 3.200</span>
                        </div>
                    </div>
                    <div class="p-3 bg-teal-50 rounded-xl border border-teal-100 flex justify-between items-center">
                        <span class="text-sm font-semibold text-teal-800">Totale spese marcate</span>
                        <span class="text-lg font-extrabold text-teal-700">€ 4.392</span>
                    </div>
                    <p class="mt-2 text-xs text-surface-500">Esempio illustrativo: l'app traccia e esporta le spese, non calcola la dichiarazione.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="py-14 sm:py-20 bg-gradient-to-r from-teal-600 to-emerald-600 text-white text-center">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-2xl">
            <h2 class="text-2xl sm:text-3xl font-bold mb-4">Organizza le spese detraibili adesso, non ad aprile</h2>
            <p class="text-teal-100 mb-5">Detrazioni, rimborsi: tutto tracciato e pronto quando serve.</p>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 mb-8 text-sm text-teal-200">
                <span>✓ €2,99/mese</span>
                <span>✓ Nessun conto bancario da collegare</span>
                <span>✓ Disdici quando vuoi</span>
            </div>
            @include('partials.landing.pro-cta-button', [
                    'label'         => 'Attiva Pro — zero spese dimenticate',
                'umamiEvent'    => 'landing-cta-lavoratori',
                'umamiPosition' => 'footer',
                'classes'       => 'inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-teal-700 bg-white hover:bg-teal-50 rounded-xl shadow-lg transition-all duration-200',
            ])
        </div>
    </section>
@endsection
