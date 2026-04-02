{{--
    Partial condiviso: sezione CTA finale per le landing page target.
--}}
<section class="py-12 sm:py-20 bg-gradient-to-br from-primary-600 to-primary-800 text-white">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto text-center">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-3 sm:mb-4">
                {{ $ctaTitle ?? 'Inizia oggi a capire davvero le tue finanze' }}
            </h2>
            <p class="text-base sm:text-lg text-primary-100 mb-6 sm:mb-8 max-w-2xl mx-auto">
                {{ $ctaSubtitle ?? 'Registrati in 30 secondi. Nessuna carta di credito, nessun abbonamento, nessuna connessione bancaria richiesta.' }}
            </p>
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center">
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-primary-700 bg-white hover:bg-surface-50 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200">
                        Crea il tuo account gratis
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                @endif
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-medium text-white hover:text-primary-100 transition-colors">
                        Hai già un account? Accedi
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>
