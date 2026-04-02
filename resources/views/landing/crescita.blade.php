@extends('layouts.landing-minimal')
@php $landingPage = 'crescita'; @endphp

@section('content')
    {{-- HERO --}}
    <section class="relative min-h-[85vh] flex items-center overflow-hidden bg-gradient-to-br from-primary-50 via-white to-indigo-50" aria-labelledby="hero-title">
        <div class="absolute top-0 right-0 w-96 h-96 bg-primary-200 rounded-full blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-indigo-200 rounded-full blur-3xl opacity-20 translate-y-1/2 -translate-x-1/2" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
            <div class="max-w-2xl mx-auto text-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-primary-100 text-primary-700 text-sm font-semibold mb-6">
                    📈 Per la crescita personale
                </span>
                <h1 id="hero-title" class="text-4xl sm:text-5xl md:text-6xl font-bold text-surface-900 leading-tight mb-5">
                    Stai cadendo nella trappola
                    <span class="bg-gradient-to-r from-primary-600 to-indigo-700 bg-clip-text text-transparent">del lifestyle inflation?</span>
                </h1>
                <p class="text-lg sm:text-xl text-surface-600 mb-8 leading-relaxed">
                    Il Lifestyle Inflation Score di FinanzaMente ti mostra se le tue spese voluttuarie crescono più velocemente delle entrate — settimana per settimana, prima che sia troppo tardi.
                </p>
                @if (Route::has('plan.select'))
                    <a href="{{ route('plan.select') }}?plan=pro&billing_cycle=monthly"
                       class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-accent-600 hover:bg-accent-700 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200"
                       data-umami-event="landing-cta-crescita"
                       data-umami-event-position="hero">
                        Abbonati a Pro — scopri il tuo score
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                @endif
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
                    <div class="text-3xl mb-3" aria-hidden="true">📈</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Lifestyle Inflation Score unico</h3>
                    <p class="text-sm text-surface-600">Un punteggio da 0 a 100 che misura se le spese voluttuarie crescono più velocemente delle entrate.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">🔍</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Analisi delle categorie in crescita</h3>
                    <p class="text-sm text-surface-600">Qual è la voce che accelera di più? Ristoranti, abbigliamento, viaggi: lo sai esattamente.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">🚨</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Alert proattivi prima che sia tardi</h3>
                    <p class="text-sm text-surface-600">Ricevi segnali d'allerta quando lo score sale troppo velocemente, non quando è già fuori controllo.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- PROOF VISUAL --}}
    <section class="py-12 sm:py-16 bg-surface-50" aria-label="Esempio Lifestyle Inflation Score">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-12 items-center">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-surface-900 mb-4">
                        Un segnale d'allarme precoce, non un rimpianto tardivo
                    </h2>
                    <p class="text-surface-600 leading-relaxed">
                        La lifestyle inflation è la tendenza inconscia ad aumentare le spese voluttuarie man mano che crescono le entrate. Il risultato: <strong>lo stipendio aumenta, i risparmi restano fermi</strong>. FinanzaMente Pro ti mostra <strong>il pericolo in anticipo</strong> — non a fine anno.
                    </p>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-soft-lg border border-surface-200">
                    <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-4">Il tuo Lifestyle Inflation Score</p>
                    <div class="flex flex-col items-center mb-5">
                        <div class="relative w-36 h-20 mb-2">
                            <svg viewBox="0 0 200 110" class="w-full" aria-label="Gauge score 58 su 100">
                                <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="#f1f5f9" stroke-width="18" stroke-linecap="round"/>
                                <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="url(#scoreGrad)" stroke-width="18" stroke-linecap="round" stroke-dasharray="251" stroke-dashoffset="105"/>
                                <defs>
                                    <linearGradient id="scoreGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#10b981"/>
                                        <stop offset="50%" stop-color="#f59e0b"/>
                                        <stop offset="100%" stop-color="#ef4444"/>
                                    </linearGradient>
                                </defs>
                                <line x1="100" y1="100" x2="68" y2="42" stroke="#0f172a" stroke-width="3" stroke-linecap="round"/>
                                <circle cx="100" cy="100" r="5" fill="#0f172a"/>
                            </svg>
                        </div>
                        <div class="text-center">
                            <span class="text-4xl font-extrabold text-amber-600">58</span>
                            <span class="text-lg text-surface-500 ml-1">/ 100</span>
                        </div>
                        <span class="mt-2 inline-flex items-center px-3 py-1 rounded-full bg-amber-100 text-amber-700 text-sm font-semibold">
                            ⚠️ Attenzione — In crescita
                        </span>
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs text-surface-500 font-medium uppercase tracking-wide mb-2">Voci in accelerazione</p>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-surface-700">Ristoranti & Food delivery</span>
                            <span class="font-bold text-red-600">+34%</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-surface-700">Abbigliamento</span>
                            <span class="font-bold text-amber-600">+18%</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-surface-700">Intrattenimento</span>
                            <span class="font-bold text-emerald-600">+7%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="py-14 sm:py-20 bg-gradient-to-r from-primary-600 to-indigo-700 text-white text-center">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-2xl">
            <h2 class="text-2xl sm:text-3xl font-bold mb-4">Scopri il tuo Lifestyle Inflation Score adesso</h2>
            <p class="text-primary-100 mb-5">Lifestyle Inflation Score, alert proattivi e analisi delle categorie in crescita.</p>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 mb-8 text-sm text-primary-200">
                <span>✓ €2,99/mese</span>
                <span>✓ Nessun conto bancario da collegare</span>
                <span>✓ Disdici quando vuoi</span>
            </div>
            @if (Route::has('plan.select'))
                <a href="{{ route('plan.select') }}?plan=pro&billing_cycle=monthly"
                   class="inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-primary-700 bg-white hover:bg-primary-50 rounded-xl shadow-lg transition-all duration-200"
                   data-umami-event="landing-cta-crescita"
                   data-umami-event-position="footer">
                    Attiva Pro — scopri il tuo Score adesso
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            @endif
        </div>
    </section>
@endsection
