@extends('layouts.landing-minimal')
@php $landingPage = 'famiglie'; @endphp

@section('content')
    {{-- HERO --}}
    <section class="relative min-h-[85vh] flex items-center overflow-hidden bg-gradient-to-br from-pink-50 via-white to-rose-50" aria-labelledby="hero-title">
        <div class="absolute top-0 right-0 w-96 h-96 bg-pink-200 rounded-full blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-rose-200 rounded-full blur-3xl opacity-20 translate-y-1/2 -translate-x-1/2" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
            <div class="max-w-2xl mx-auto text-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-pink-100 text-pink-700 text-sm font-semibold mb-6">
                    👨‍👩‍👧 Per famiglie e coppie
                </span>
                <h1 id="hero-title" class="text-4xl sm:text-5xl md:text-6xl font-bold text-surface-900 leading-tight mb-5">
                    Gestite i soldi insieme,
                    <span class="bg-gradient-to-r from-pink-600 to-rose-600 bg-clip-text text-transparent">senza stress e senza sorprese</span>
                </h1>
                <p class="text-lg sm:text-xl text-surface-600 mb-8 leading-relaxed">
                    Uno spazio condiviso per spese, budget e obiettivi di coppia o famiglia. Ogni membro vede il quadro completo — nessuna discussione sui soldi a fine mese.
                </p>
                @if (Route::has('plan.select'))
                    <a href="{{ route('plan.select') }}?plan=pro&billing_cycle=monthly"
                       class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-accent-600 hover:bg-accent-700 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200"
                       data-umami-event="landing-cta-famiglie"
                       data-umami-event-position="hero">
                        Abbonati a Pro — gestisci le finanze di famiglia
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
                    <div class="text-3xl mb-3" aria-hidden="true">🏠</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Household condivisi con ruoli</h3>
                    <p class="text-sm text-surface-600">Invita il partner o i familiari. Ognuno ha il suo ruolo: admin, membro o visualizzatore.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">🎯</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Budget e obiettivi condivisi</h3>
                    <p class="text-sm text-surface-600">Vacanza, fondo emergenze, casa: create obiettivi condivisi e monitorate i progressi insieme.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">🔄</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Rimborsi sempre tracciati</h3>
                    <p class="text-sm text-surface-600">Chi ha pagato cosa? I rimborsi tra membri sono collegati alle spese originali. Zero confusione.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- PROOF VISUAL --}}
    <section class="py-12 sm:py-16 bg-surface-50" aria-label="Esempio household condiviso">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-12 items-center">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-surface-900 mb-4">
                        Un household, tanti membri, zero confusione
                    </h2>
                    <p class="text-surface-600 leading-relaxed">
                        Con household condivisi Pro, <strong>ogni membro gestisce le proprie transazioni</strong> ma <strong>tutti vedono il quadro comune</strong>. Budget condivisi, spese ricorrenti e obiettivi: tutto in un unico spazio, accessibile a chiunque inviti.
                    </p>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-soft-lg border border-surface-200">
                    <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-4">Household "Casa Rossi"</p>
                    <div class="space-y-3 mb-5">
                        <div class="flex items-center gap-3 p-3 bg-surface-50 rounded-xl">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-pink-400 to-pink-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">LR</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-surface-900">Laura Rossi</p>
                                <p class="text-xs text-surface-500">3 transazioni oggi</p>
                            </div>
                            <span class="text-xs bg-pink-100 text-pink-700 font-semibold px-2 py-0.5 rounded-full">Admin</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-surface-50 rounded-xl">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">MR</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-surface-900">Marco Rossi</p>
                                <p class="text-xs text-surface-500">1 transazione oggi</p>
                            </div>
                            <span class="text-xs bg-blue-100 text-blue-700 font-semibold px-2 py-0.5 rounded-full">Membro</span>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-surface-100 flex justify-between">
                        <div>
                            <p class="text-xs text-surface-500 mb-1">Spese del mese</p>
                            <p class="text-lg font-bold text-surface-900">€ 2.840</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-surface-500 mb-1">Saldo condiviso</p>
                            <p class="text-lg font-bold text-accent-600">€ 8.530</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="py-14 sm:py-20 bg-gradient-to-r from-pink-600 to-rose-600 text-white text-center">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-2xl">
            <h2 class="text-2xl sm:text-3xl font-bold mb-4">Inizia a gestire le finanze di famiglia con Pro</h2>
            <p class="text-pink-100 mb-5">Household condivisi, budget e obiettivi per tutta la famiglia.</p>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 mb-8 text-sm text-pink-200">
                <span>✓ €2,99/mese</span>
                <span>✓ Nessun conto bancario da collegare</span>
                <span>✓ Disdici quando vuoi</span>
            </div>
            @if (Route::has('plan.select'))
                <a href="{{ route('plan.select') }}?plan=pro&billing_cycle=monthly"
                   class="inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-pink-700 bg-white hover:bg-pink-50 rounded-xl shadow-lg transition-all duration-200"
                   data-umami-event="landing-cta-famiglie"
                   data-umami-event-position="footer">
                    Attiva Pro — gestisci le finanze di famiglia
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            @endif
        </div>
    </section>
@endsection
