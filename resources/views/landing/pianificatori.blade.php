@extends('layouts.landing-minimal')
@php $landingPage = 'pianificatori'; @endphp

@section('content')
    {{-- HERO --}}
    <section class="relative min-h-[85vh] flex items-center overflow-hidden bg-gradient-to-br from-violet-50 via-white to-indigo-50" aria-labelledby="hero-title">
        <div class="absolute top-0 right-0 w-96 h-96 bg-violet-200 rounded-full blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-indigo-200 rounded-full blur-3xl opacity-20 translate-y-1/2 -translate-x-1/2" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
            <div class="max-w-2xl mx-auto text-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-violet-100 text-violet-700 text-sm font-semibold mb-6">
                    🔮 Per pianificatori finanziari
                </span>
                <h1 id="hero-title" class="text-4xl sm:text-5xl md:text-6xl font-bold text-surface-900 leading-tight mb-5">
                    Smetti di sperare.
                    <span class="bg-gradient-to-r from-violet-600 to-indigo-700 bg-clip-text text-transparent">Inizia a pianificare davvero.</span>
                </h1>
                <p class="text-lg sm:text-xl text-surface-600 mb-8 leading-relaxed">
                    Crea obiettivi finanziari, simula scenari e automatizza le ricorrenti. Vedi in anticipo quando raggiungerai i tuoi traguardi — basandoti sui tuoi dati reali.
                </p>
                @if (Route::has('plan.select'))
                    <a href="{{ route('plan.select') }}?plan=pro&billing_cycle=monthly"
                       class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-accent-600 hover:bg-accent-700 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200"
                       data-umami-event="landing-cta-pianificatori"
                       data-umami-event-position="hero">
                        Abbonati a Pro — inizia a pianificare
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
                    <div class="text-3xl mb-3" aria-hidden="true">🎯</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Obiettivi con data e progressi</h3>
                    <p class="text-sm text-surface-600">Fondo emergenze, anticipo casa, pensione: crea obiettivi illimitati e monitora il progresso in tempo reale.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">🔮</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Simulazioni "se risparmiassi..."</h3>
                    <p class="text-sm text-surface-600">Proiezioni basate sui tuoi dati reali: cambia le variabili e vedi quando raggiungi l'obiettivo.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">🔄</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Ricorrenti illimitate e automatiche</h3>
                    <p class="text-sm text-surface-600">Piani di risparmio, investimenti e spese automatici senza limiti. Imposti una volta, si gestiscono da soli.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- PROOF VISUAL --}}
    <section class="py-12 sm:py-16 bg-surface-50" aria-label="Esempio simulazione finanziaria">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-12 items-center">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-surface-900 mb-4">
                        "Se risparmio X al mese, quando raggiungo Y?"
                    </h2>
                    <p class="text-surface-600 leading-relaxed">
                        FinanzaMente Pro risponde a questa domanda con <strong>proiezioni basate sui tuoi dati reali</strong>. Non stime generiche, ma <strong>simulazioni che tengono conto delle tue ricorrenti, delle tue entrate e delle tue abitudini di spesa effettive</strong>.
                    </p>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-soft-lg border border-surface-200">
                    <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-4">Simulazione: Fondo emergenze 6 mesi</p>
                    <div class="space-y-4 mb-5">
                        <div>
                            <div class="flex justify-between text-sm mb-1.5">
                                <span class="text-surface-600">Scenario attuale</span>
                                <span class="font-bold text-violet-700">12 mesi</span>
                            </div>
                            <div class="w-full h-2.5 bg-surface-100 rounded-full overflow-hidden">
                                <div class="h-full bg-violet-400 rounded-full" style="width:55%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1.5">
                                <span class="text-surface-600">Con risparmio +€200/mese</span>
                                <span class="font-bold text-emerald-700">8 mesi</span>
                            </div>
                            <div class="w-full h-2.5 bg-surface-100 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-400 rounded-full" style="width:75%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1.5">
                                <span class="text-surface-600">Con ottimizzazione spese</span>
                                <span class="font-bold text-blue-700">6 mesi</span>
                            </div>
                            <div class="w-full h-2.5 bg-surface-100 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500 rounded-full" style="width:100%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-surface-100 flex justify-between">
                        <div>
                            <p class="text-xs text-surface-500 mb-1">Obiettivo</p>
                            <p class="text-lg font-bold text-surface-900">€ 12.000</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-surface-500 mb-1">Accantonato</p>
                            <p class="text-lg font-bold text-violet-700">€ 4.200</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="py-14 sm:py-20 bg-gradient-to-r from-violet-600 to-indigo-700 text-white text-center">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-2xl">
            <h2 class="text-2xl sm:text-3xl font-bold mb-4">Crea il tuo primo obiettivo finanziario oggi</h2>
            <p class="text-violet-100 mb-5">Simulazioni, obiettivi illimitati e ricorrenti automatiche.</p>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 mb-8 text-sm text-violet-200">
                <span>✓ €2,99/mese</span>
                <span>✓ Nessun conto bancario da collegare</span>
                <span>✓ Disdici quando vuoi</span>
            </div>
            @if (Route::has('plan.select'))
                <a href="{{ route('plan.select') }}?plan=pro&billing_cycle=monthly"
                   class="inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-violet-700 bg-white hover:bg-violet-50 rounded-xl shadow-lg transition-all duration-200"
                   data-umami-event="landing-cta-pianificatori"
                   data-umami-event-position="footer">
                    Attiva Pro — inizia a pianificare davvero
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            @endif
        </div>
    </section>
@endsection
