@extends('layouts.landing-minimal')
@php $landingPage = 'investitori'; @endphp

@section('content')
    {{-- HERO --}}
    <section class="relative min-h-[85vh] flex items-center overflow-hidden bg-gradient-to-br from-blue-50 via-white to-primary-50" aria-labelledby="hero-title">
        <div class="absolute top-0 right-0 w-96 h-96 bg-blue-200 rounded-full blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-primary-200 rounded-full blur-3xl opacity-20 translate-y-1/2 -translate-x-1/2" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
            <div class="max-w-2xl mx-auto text-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-sm font-semibold mb-6">
                    💼 Per chi investe
                </span>
                <h1 id="hero-title" class="text-4xl sm:text-5xl md:text-6xl font-bold text-surface-900 leading-tight mb-5">
                    Il tuo portafoglio e le spese quotidiane,
                    <span class="bg-gradient-to-r from-blue-600 to-primary-700 bg-clip-text text-transparent">in un unico posto</span>
                </h1>
                <p class="text-lg sm:text-xl text-surface-600 mb-8 leading-relaxed">
                    Traccia ETF, azioni e crypto insieme alle spese di tutti i giorni. Visualizza asset allocation, indice di rischio e patrimonio netto aggiornato in tempo reale.
                </p>
                @include('partials.landing.pro-cta-button', [
                    'label'         => 'Abbonati a Pro — traccia il tuo portafoglio',
                    'umamiEvent'    => 'landing-cta-investitori',
                    'umamiPosition' => 'hero',
                    'classes'       => 'inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-accent-600 hover:bg-accent-700 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200',
                ])
                <div class="flex flex-wrap justify-center gap-x-5 gap-y-2 mt-5 text-sm text-surface-500">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-surface-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        €2,99/mese
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-surface-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        Nessun conto bancario da collegare
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-surface-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
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
                    <div class="text-3xl mb-3" aria-hidden="true">📊</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Asset allocation con indice di rischio</h3>
                    <p class="text-sm text-surface-600">Composizione del portafoglio e scala di rischio 1–7 calcolata in automatico.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">💼</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">ETF, azioni, crypto e obbligazioni</h3>
                    <p class="text-sm text-surface-600">Inserisci qualsiasi strumento tramite ISIN o ticker. Nessun broker collegato obbligatorio.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">💰</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Patrimonio netto in tempo reale</h3>
                    <p class="text-sm text-surface-600">Investimenti + conti correnti: il tuo valore netto reale, sempre aggiornato.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- PROOF VISUAL --}}
    <section class="py-12 sm:py-16 bg-surface-50" aria-label="Esempio di asset allocation">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-12 items-center">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-surface-900 mb-4">
                        Vedi dove sono i tuoi soldi, davvero
                    </h2>
                    <p class="text-surface-600 leading-relaxed">
                        Non solo spese: Finanzamente Pro <strong>unisce il tuo portafoglio di investimento con le finanze quotidiane</strong> in un’unica dashboard. Sai sempre <strong>quanto possiedi, quanto spendi e come è distribuito il tuo rischio</strong>.
                    </p>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-soft-lg border border-surface-200">
                    <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-4">Il tuo portafoglio</p>
                    <div class="space-y-3 mb-5">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-blue-500 flex-shrink-0"></div>
                            <span class="text-sm font-medium text-surface-800 flex-1">Azionario</span>
                            <span class="text-sm text-surface-600">52%</span>
                            <div class="w-20 h-2 bg-surface-100 rounded-full overflow-hidden"><div class="h-full bg-blue-500 rounded-full" style="width:52%"></div></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-cyan-400 flex-shrink-0"></div>
                            <span class="text-sm font-medium text-surface-800 flex-1">Liquidità</span>
                            <span class="text-sm text-surface-600">28%</span>
                            <div class="w-20 h-2 bg-surface-100 rounded-full overflow-hidden"><div class="h-full bg-cyan-400 rounded-full" style="width:28%"></div></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-violet-500 flex-shrink-0"></div>
                            <span class="text-sm font-medium text-surface-800 flex-1">Crypto</span>
                            <span class="text-sm text-surface-600">12%</span>
                            <div class="w-20 h-2 bg-surface-100 rounded-full overflow-hidden"><div class="h-full bg-violet-500 rounded-full" style="width:12%"></div></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 rounded-full bg-surface-500 flex-shrink-0"></div>
                            <span class="text-sm font-medium text-surface-800 flex-1">Obbligazionario</span>
                            <span class="text-sm text-surface-600">8%</span>
                            <div class="w-20 h-2 bg-surface-100 rounded-full overflow-hidden"><div class="h-full bg-surface-500 rounded-full" style="width:8%"></div></div>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-surface-100 flex justify-between">
                        <div>
                            <p class="text-xs text-surface-500 mb-1">Patrimonio totale</p>
                            <p class="text-xl font-bold text-surface-900">€ 42.350</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-surface-500 mb-1">Rischio</p>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 text-sm font-bold">4/7 — Moderato</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="py-14 sm:py-20 bg-gradient-to-r from-blue-600 to-primary-700 text-white text-center">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-2xl">
            <h2 class="text-2xl sm:text-3xl font-bold mb-4">Inizia a tracciare il tuo patrimonio completo</h2>
            <p class="text-blue-100 mb-5">Portafoglio, conti e patrimonio netto: tutto in un’unica dashboard.</p>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 mb-8 text-sm text-blue-200">
                <span>✓ €2,99/mese</span>
                <span>✓ Nessun conto bancario da collegare</span>
                <span>✓ Disdici quando vuoi</span>
            </div>
            @include('partials.landing.pro-cta-button', [
                'label'         => 'Attiva Pro — traccia il tuo portafoglio',
                'umamiEvent'    => 'landing-cta-investitori',
                'umamiPosition' => 'footer',
                'classes'       => 'inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-primary-700 bg-white hover:bg-blue-50 rounded-xl shadow-lg transition-all duration-200',
            ])
        </div>
    </section>
@endsection
