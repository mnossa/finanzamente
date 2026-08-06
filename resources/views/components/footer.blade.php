<!-- Footer -->
<footer class="bg-surface-900 text-surface-300 py-12 sm:py-16" role="contentinfo">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 mb-8">
            <!-- Brand -->
            <div>
                <div class="flex items-center space-x-2 mb-4">
                    <img src="{{ asset('images/finanzamente-logo.webp') }}" alt="Logo Finanzamente" class="w-8 h-8">
                    <span class="text-xl font-bold text-white">Finanzamente</span>
                </div>
                <p class="text-sm text-surface-400 leading-relaxed">
                    Gestisci le tue finanze personali con consapevolezza e privacy totale.
                </p>
            </div>

            <!-- Prodotto -->
            <div>
                <h3 class="text-white font-semibold mb-4">Prodotto</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('home') }}#come-funziona" class="hover:text-white transition-colors">Come
                            funziona</a></li>
                    <li><a href="{{ route('home') }}#funzionalita"
                            class="hover:text-white transition-colors">Funzionalità</a></li>
                    @if (Route::has('register'))
                        <li><a href="{{ route('register') }}" class="hover:text-white transition-colors">Registrati</a>
                        </li>
                    @endif
                </ul>
            </div>

            <!-- Legale -->
            <div>
                <h3 class="text-white font-semibold mb-4">Legale</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('legal.privacy') }}" class="hover:text-white transition-colors">Privacy
                            Policy</a></li>
                    <li><a href="{{ route('legal.terms') }}" class="hover:text-white transition-colors">Termini di
                            servizio</a></li>
                    <li><a href="{{ route('legal.cookies') }}" class="hover:text-white transition-colors">Cookie
                            Policy</a></li>
                </ul>
            </div>
        </div>

        <!-- Bottom bar -->
        <div class="pt-8 border-t border-surface-800 flex flex-col sm:flex-row justify-between items-center gap-4">
            <p class="text-sm text-surface-400">
                &copy; {{ date('Y') }} Matteo Nossa — Finanzamente, licenza MIT.
            </p>
        </div>
    </div>
</footer>
