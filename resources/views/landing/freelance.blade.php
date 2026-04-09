@extends('layouts.landing-minimal')
@php $landingPage = 'freelance'; @endphp

@section('content')
    {{-- HERO --}}
    <section class="relative min-h-[85vh] flex items-center overflow-hidden bg-gradient-to-br from-orange-50 via-white to-amber-50" aria-labelledby="hero-title">
        <div class="absolute top-0 right-0 w-96 h-96 bg-orange-200 rounded-full blur-3xl opacity-20 -translate-y-1/2 translate-x-1/2" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-amber-200 rounded-full blur-3xl opacity-20 translate-y-1/2 -translate-x-1/2" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
            <div class="max-w-2xl mx-auto text-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-orange-100 text-orange-700 text-sm font-semibold mb-6">
                    🧾 Per Freelance e Partita IVA
                </span>
                <h1 id="hero-title" class="text-4xl sm:text-5xl md:text-6xl font-bold text-surface-900 leading-tight mb-5">
                    IVA e spese deducibili:
                    <span class="bg-gradient-to-r from-orange-600 to-amber-600 bg-clip-text text-transparent">smettila di rincorrere scontrini</span>
                </h1>
                <p class="text-lg sm:text-xl text-surface-600 mb-8 leading-relaxed">
                    Marca ogni spesa come deducibile durante l'anno. Al momento della liquidazione IVA o del 730, hai già tutto pronto — senza sorprese e senza rincorrere il commercialista.
                </p>
                @include('partials.landing.pro-cta-button', [
                    'label'         => 'Abbonati a Pro — gestisci IVA e deducibili',
                    'umamiEvent'    => 'landing-cta-freelance',
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
                    <div class="text-3xl mb-3" aria-hidden="true">🏷️</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Tag deducibilità su ogni spesa</h3>
                    <p class="text-sm text-surface-600">Marca ogni transazione con aliquota IVA e tipo di deducibilità. Professionale o personale: sempre separati.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">📊</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Riepilogo IVA trimestrale</h3>
                    <p class="text-sm text-surface-600">Totali IVA per trimestre calcolati in automatico. Quanto devi versare? Lo sai sempre in anticipo.</p>
                </div>
                <div class="text-center p-4">
                    <div class="text-3xl mb-3" aria-hidden="true">📤</div>
                    <h3 class="text-base font-semibold text-surface-900 mb-1">Export per il commercialista</h3>
                    <p class="text-sm text-surface-600">Un PDF con tutte le spese deducibili, pronto da inviare al commercialista in un click.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- PROOF VISUAL --}}
    <section class="py-12 sm:py-16 bg-surface-50" aria-label="Esempio riepilogo IVA">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-12 items-center">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-surface-900 mb-4">
                        Il tuo commercialista ti ringrazierà
                    </h2>
                    <p class="text-surface-600 leading-relaxed">
                        <strong>Niente più fogli Excel o scontrini in una scatola.</strong> Con Finanzamente Pro ogni spesa professionale è taggata, ogni aliquota IVA registrata. Al momento della liquidazione, <strong>esporti il PDF e via</strong> — il commercialista ha già tutto.
                    </p>
                </div>
                <div class="bg-white rounded-2xl p-6 shadow-soft-lg border border-surface-200">
                    <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-4">Riepilogo IVA — Q1 2025</p>
                    <div class="space-y-3 mb-5">
                        <div class="flex justify-between items-center py-2 border-b border-surface-100">
                            <div>
                                <p class="text-sm font-medium text-surface-800">IVA 22% — Acquisti</p>
                                <p class="text-xs text-surface-500">Software, attrezzatura, servizi</p>
                            </div>
                            <span class="text-sm font-bold text-surface-900">€ 1.265,00</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-surface-100">
                            <div>
                                <p class="text-sm font-medium text-surface-800">IVA 22% — Vendite</p>
                                <p class="text-xs text-surface-500">Fatture emesse Q1</p>
                            </div>
                            <span class="text-sm font-bold text-surface-900">€ 4.840,00</span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <div>
                                <p class="text-sm font-medium text-surface-800">Spese deducibili</p>
                                <p class="text-xs text-surface-500">12 transazioni taggate</p>
                            </div>
                            <span class="text-sm font-bold text-surface-900">€ 5.750,00</span>
                        </div>
                    </div>
                    <div class="p-3 bg-orange-50 rounded-xl border border-orange-100 flex justify-between items-center">
                        <span class="text-sm font-semibold text-orange-800">IVA da versare Q1</span>
                        <span class="text-lg font-extrabold text-orange-700">€ 3.575,00</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- FINAL CTA --}}
    <section class="py-14 sm:py-20 bg-gradient-to-r from-orange-500 to-amber-600 text-white text-center">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-2xl">
            <h2 class="text-2xl sm:text-3xl font-bold mb-4">Non aspettare la prossima liquidazione IVA</h2>
            <p class="text-orange-100 mb-5">IVA, deducibili e liquidazioni trimestrali: tutto sotto controllo.</p>
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 mb-8 text-sm text-orange-200">
                <span>✓ €2,99/mese</span>
                <span>✓ Nessun conto bancario da collegare</span>
                <span>✓ Disdici quando vuoi</span>
            </div>
            @include('partials.landing.pro-cta-button', [
                'label'         => "Attiva Pro — tieni l'IVA sotto controllo",
                'umamiEvent'    => 'landing-cta-freelance',
                'umamiPosition' => 'footer',
                'classes'       => 'inline-flex items-center justify-center px-8 py-4 text-base font-semibold text-orange-700 bg-white hover:bg-orange-50 rounded-xl shadow-lg transition-all duration-200',
            ])
        </div>
    </section>
@endsection
