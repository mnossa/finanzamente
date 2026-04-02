@extends('layouts.guest')

@section('content')
    <!-- Hero Section -->
    <section class="relative pt-24 sm:pt-32 pb-12 sm:pb-20 overflow-hidden" aria-labelledby="hero-title">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-50 via-white to-indigo-50 opacity-70" aria-hidden="true"></div>
        <div class="absolute top-0 right-0 -translate-y-12 translate-x-12 w-72 h-72 bg-primary-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 translate-y-12 -translate-x-12 w-72 h-72 bg-indigo-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>

        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <span class="inline-flex items-center px-4 py-1.5 rounded-full bg-primary-100 text-primary-700 text-sm font-semibold mb-6">
                    📈 Per chi punta alla crescita personale
                </span>
                <h1 id="hero-title" class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-surface-900 leading-tight mb-4 sm:mb-6">
                    Stai cadendo nella trappola
                    <span class="bg-gradient-to-r from-primary-600 to-indigo-700 bg-clip-text text-transparent">dell'inflazione del tenore di vita?</span>
                </h1>
                <p class="text-base sm:text-lg md:text-xl text-surface-600 mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed">
                    Il Lifestyle Inflation Score di FinanzaMente Pro è un indicatore unico: misura se le tue spese voluttuarie crescono più velocemente delle entrate. Un segnale d'allarme precoce per chi vuole costruire ricchezza, non solo spenderla.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center mb-8 sm:mb-12">
                    @if (Route::has('plan.select'))
                        <a href="{{ route('plan.select') }}?plan=pro" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-white bg-gradient-to-r from-accent-600 to-accent-700 hover:from-accent-700 hover:to-accent-800 rounded-xl shadow-accent transition-all duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Scopri il tuo Score
                        </a>
                    @endif
                    <a href="#funzionalita" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-medium text-primary-700 bg-white hover:bg-surface-50 rounded-xl border-2 border-primary-200 hover:border-primary-300 transition-all duration-200">
                        Come funziona
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Highlight Lifestyle Score -->
    <section id="funzionalita" class="py-12 sm:py-20 bg-gradient-to-br from-primary-50 to-indigo-50" aria-labelledby="score-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-16 items-center">
                <div>
                    <span class="inline-flex items-center px-3 py-1 rounded-full bg-primary-100 text-primary-700 text-sm font-medium mb-4">
                        📈 Lifestyle Inflation Score
                    </span>
                    <h2 id="score-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-4 sm:mb-6">
                        Un punteggio che rivela quanto stai scivolando verso il consumismo
                    </h2>
                    <p class="text-base sm:text-lg text-surface-600 mb-6 leading-relaxed">
                        La lifestyle inflation è la tendenza — spesso inconscia — ad aumentare le spese voluttuarie man mano che crescono le entrate. Il risultato? Lo stipendio aumenta, ma i risparmi restano fermi. FinanzaMente Pro ti mostra subito il segnale d'allarme.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-primary-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-primary-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Confronto automatico tra la crescita delle entrate e la crescita delle spese voluttuarie nel tempo</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-primary-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-primary-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Score da 0 a 100: più è alto, più le spese voluttuarie stanno crescendo più in fretta delle entrate</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-primary-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-primary-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Analisi per categoria: qual è la voce di spesa che cresce di più rispetto alle entrate?</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 w-6 h-6 bg-primary-100 rounded-full flex items-center justify-center mt-0.5">
                                <svg class="w-4 h-4 text-primary-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm sm:text-base text-surface-700">Feature originale e difficilmente replicabile — disponibile solo su FinanzaMente Pro</p>
                        </div>
                    </div>
                </div>

                <!-- Visual Lifestyle Score mock -->
                <div class="relative bg-white rounded-2xl p-6 sm:p-8 shadow-soft-lg border border-surface-200">
                    <p class="text-xs text-surface-500 uppercase font-semibold tracking-wider mb-5">Il tuo Lifestyle Inflation Score</p>

                    <!-- Score gauge mock -->
                    <div class="flex flex-col items-center mb-6">
                        <div class="relative w-40 h-24 mb-3">
                            <svg viewBox="0 0 200 110" class="w-full" aria-hidden="true">
                                <!-- Background arc -->
                                <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="#f1f5f9" stroke-width="18" stroke-linecap="round"/>
                                <!-- Score arc (58% = warning) -->
                                <path d="M 20 100 A 80 80 0 0 1 180 100" fill="none" stroke="url(#scoreGrad)" stroke-width="18" stroke-linecap="round" stroke-dasharray="251" stroke-dashoffset="105"/>
                                <defs>
                                    <linearGradient id="scoreGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#10b981"/>
                                        <stop offset="50%" stop-color="#f59e0b"/>
                                        <stop offset="100%" stop-color="#ef4444"/>
                                    </linearGradient>
                                </defs>
                                <!-- Needle -->
                                <line x1="100" y1="100" x2="68" y2="42" stroke="#0f172a" stroke-width="3" stroke-linecap="round"/>
                                <circle cx="100" cy="100" r="5" fill="#0f172a"/>
                                <!-- Labels -->
                                <text x="15" y="118" font-size="12" fill="#64748b" text-anchor="middle">0</text>
                                <text x="100" y="10" font-size="12" fill="#64748b" text-anchor="middle">50</text>
                                <text x="185" y="118" font-size="12" fill="#64748b" text-anchor="middle">100</text>
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

                    <div class="space-y-3">
                        <p class="text-xs text-surface-500 font-medium uppercase tracking-wide">Voci in accelerazione</p>
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

    <!-- Funzionalità specifiche -->
    <section class="py-12 sm:py-20 bg-white" aria-labelledby="features-title">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10 sm:mb-16">
                <h2 id="features-title" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    Per chi vuole crescere finanziariamente, non solo guadagnare di più
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Strumenti Pro per la consapevolezza finanziaria e la crescita personale
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 max-w-6xl mx-auto">
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Lifestyle Inflation Score</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Misura se le spese voluttuarie crescono più velocemente delle entrate. Un indicatore originale e unico nel panorama delle finanze personali.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-violet-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-violet-500 to-violet-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Simulazioni finanziarie</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Proietta il futuro finanziario con scenari personalizzati. Visualizza quanto risparmeresti ottimizzando le spese voluttuarie.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-indigo-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Obiettivi illimitati</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Crea quanti obiettivi vuoi e monitora i progressi. Ogni obiettivo raggiunto è la dimostrazione concreta che la consapevolezza funziona.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-emerald-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Analisi dei trend</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Grafici storici per categoria che mostrano come cambiano le tue abitudini di spesa nel tempo. Identifica le aree di miglioramento.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-amber-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-amber-500 to-amber-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Budget consapevole</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Imposta budget mensili e ricevi segnali quando ti stai avvicinando ai limiti. La consapevolezza inizia dal budget.
                    </p>
                </div>

                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-blue-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <h3 class="text-lg sm:text-xl font-semibold text-surface-900">Portafoglio investimenti</h3>
                        <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full">Pro</span>
                    </div>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Tieni traccia degli investimenti accanto alle spese. Misura il rapporto reale tra quanto spendi e quanto fai crescere il patrimonio.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Sezione prezzi -->
    @php $targetId = 'crescita'; @endphp
    @include('partials.landing.pricing')

    <!-- CTA finale -->
    @include('partials.landing.cta-finale', [
        'ctaTitle' => 'Scopri il tuo Lifestyle Inflation Score',
        'ctaSubtitle' => 'Registrati gratis e inizia a tracciare le spese. Passa a Pro per sbloccare il Lifestyle Inflation Score e le simulazioni finanziarie avanzate.',
    ])
@endsection
