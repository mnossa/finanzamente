<header class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-sm border-b border-surface-200 transition-all duration-300" role="banner">
    <nav class="container mx-auto px-4 sm:px-6 lg:px-8" role="navigation" aria-label="Menu principale">
        <div class="flex justify-between items-center h-16 sm:h-20">
            <!-- Logo -->
            <a href="{{ url('/') }}" class="flex items-center space-x-2">
                <img src="{{ asset('images/finanzamente-logo.webp') }}" alt="Logo FinanzaMente" class="w-8 h-8 sm:w-10 sm:h-10" loading="eager">
                <span class="text-xl sm:text-2xl font-bold bg-gradient-to-r from-primary-700 to-primary-900 bg-clip-text text-transparent">FinanzaMente</span>
            </a>
            
            <!-- Navigation Links -->
            <div class="flex items-center space-x-2 sm:space-x-4">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center px-3 sm:px-4 py-2 text-sm font-medium text-primary-700 hover:text-primary-900 transition-colors">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center px-3 sm:px-4 py-2 text-sm font-medium text-surface-700 hover:text-primary-700 transition-colors">
                            Accedi
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center px-4 sm:px-6 py-2 sm:py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 rounded-lg shadow-md hover:shadow-lg transition-all duration-200">
                                Registrati gratis
                            </a>
                        @endif
                    @endauth
                @endif
            </div>
        </div>
    </nav>
</header>
