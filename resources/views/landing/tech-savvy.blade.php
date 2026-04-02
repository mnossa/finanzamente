@extends('layouts.landing-minimal')
@php $landingPage = 'tech-savvy'; @endphp

@section('content')
    {{-- HERO --}}
    <section class="relative min-h-[85vh] flex items-center overflow-hidden bg-gradient-to-br from-sky-50 via-white to-cyan-50" aria-labelledby="hero-title">
        <div class="absolute top-0 right-0 w-96 h-96 bg-sky-200 rounded-full blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-cyan-200 rounded-full blur-3xl opacity-20 translate-y-1/2 -translate-x-1/2" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
            <div class="max-w-2xl mx-auto text-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-sky-100 text-sky-700 text-sm font-semibold mb-6">
                    ⚡ Per tech-savvy
                </span>
                <h1 id="hero-title" class="text-4xl sm:text-5xl md:text-6xl font-bold text-surface-900 leading-tight mb-5">
                    Inserisci le spese da Telegram.
                    <span class="bg-gradient-to-r from-sky-600 to-cyan-600 bg-clip-text text-transparent">Perché puoi.</span>
                </h1>
                <p class="text-lg sm:text-xl text-surface-600 mb-8 leading-relaxed">
                    Un bot Telegram per registrare le spese al volo. L'inbox intelligente le raccoglie, le categorizza e le aggiunge al tuo registro — tutto senza aprire l'app una volta sola.
                </p>
                @if (Route::has('plan.select'))
                    <a href="{{ route('plan.select') }}?plan=pro&billing_cycle=monthly"
                       class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-accent-600 hover:bg-accent-700 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200"
                       data-umami-event="landing-cta-tech-savvy"
                       data-umami-event-position="hero">
                        Abbonati a Pro — attiva il bot Telegram
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
                    <div class="text-3xl mb-3" aria-hidden="true">🤖</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Bot Telegram per registrazione istantanea</h3>
                    <p class="text-sm text-surface-600">Scrivi "Caffè 1.50 #Ristoranti" e la spesa è registrata. Sintassi naturale, zero form.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">📥</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Inbox transazioni da revisionare</h3>
                    <p class="text-sm text-surface-600">Le spese dal bot atterrano nell'inbox. Confermi, modifichi o scarti prima che entrino nel registro.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">⚡</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Inserimento lampo nell'app</h3>
                    <p class="text-sm text-surface-600">Sessione rapida per più transazioni di fila. Alternativa al bot per chi preferisce l'interfaccia.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- PROOF VISUAL --}}
    <section class="py-12 sm:py-16 bg-surface-50" aria-label="Esempio bot Telegram">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-12 items-center">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-surface-900 mb-4">
                        La tua contabilità personale in una chat Telegram
                    </h2>
                    <p class="text-surface-600 leading-relaxed">
                        Collega il tuo account FinanzaMente Pro al bot Telegram. Da quel momento, <strong>registrare una spesa è più veloce che farlo a mano</strong>: scrivi in linguaggio naturale, il bot capisce e conferma. Puoi anche <strong>inviare la foto dello scontrino con la caption</strong>.
                    </p>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-soft-lg border border-surface-200">
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-surface-100">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-sky-400 to-cyan-600 flex items-center justify-center flex-shrink-0" aria-hidden="true">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
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
                            <div class="bg-sky-500 text-white text-sm rounded-2xl rounded-tr-sm px-4 py-2 max-w-[75%]">
                                Caffè 1.50 @Contante #Ristoranti
                            </div>
                        </div>
                        <div class="flex justify-start">
                            <div class="bg-surface-100 text-surface-800 text-sm rounded-2xl rounded-tl-sm px-4 py-2 max-w-[75%]">
                                ✅ <strong>Caffè €1,50</strong> registrata<br>
                                <span class="text-xs text-surface-500">Conto: Contante · #Ristoranti</span>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <div class="bg-sky-500 text-white text-sm rounded-2xl rounded-tr-sm px-4 py-2 max-w-[75%]">
                                /saldo
                            </div>
                        </div>
                        <div class="flex justify-start">
                            <div class="bg-surface-100 text-surface-800 text-sm rounded-2xl rounded-tl-sm px-4 py-2 max-w-[75%]">
                                💰 <strong>Saldi:</strong><br>
                                <span class="text-xs">Principale: €3.420 · Contante: €145</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="py-14 sm:py-20 bg-gradient-to-r from-sky-600 to-cyan-600 text-white text-center">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-2xl">
            <h2 class="text-2xl sm:text-3xl font-bold mb-4">Attiva il bot e inserisci la prima spesa in 10 secondi</h2>
            <p class="text-sky-100 mb-5">Bot Telegram, inbox intelligente e inserimento lampo per chi vuole velocità.</p>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 mb-8 text-sm text-sky-200">
                <span>✓ €2,99/mese</span>
                <span>✓ Nessun conto bancario da collegare</span>
                <span>✓ Disdici quando vuoi</span>
            </div>
            @if (Route::has('plan.select'))
                <a href="{{ route('plan.select') }}?plan=pro&billing_cycle=monthly"
                   class="inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-sky-700 bg-white hover:bg-sky-50 rounded-xl shadow-lg transition-all duration-200"
                   data-umami-event="landing-cta-tech-savvy"
                   data-umami-event-position="footer">
                    Attiva Pro — collega il bot Telegram
                    <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            @endif
        </div>
    </section>
@endsection
