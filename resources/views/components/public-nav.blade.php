<div x-data="{ open: false }">
<header class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-sm border-b border-surface-200 transition-all duration-300 relative" role="banner">
    <nav class="container mx-auto px-4 sm:px-6 lg:px-8" role="navigation" aria-label="Menu principale">
        <div class="flex justify-between items-center h-16 sm:h-20">
            <!-- Logo -->
            <a href="{{ url('/') }}" class="flex items-center space-x-2">
                <img src="{{ asset('images/finanzamente-logo.webp') }}" alt="Logo Finanzamente" class="w-8 h-8 sm:w-10 sm:h-10" loading="eager">
                <span class="hidden sm:inline text-xl sm:text-2xl font-bold bg-gradient-to-r from-primary-700 to-primary-900 bg-clip-text text-transparent">Finanzamente</span>
            </a>

            <!-- Hamburger (mobile) -->
            <button @click="open = !open"
                    class="sm:hidden inline-flex items-center justify-center p-2 rounded-lg text-surface-700 hover:text-primary-700 hover:bg-surface-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    aria-label="Apri menu"
                    :aria-expanded="open.toString()">
                {{-- Hamburger icon: visibile quando chiuso --}}
                <svg x-show="!open" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                {{-- X icon: nascosto di default con style inline (prima di Alpine) --}}
                <svg x-show="open" style="display:none" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Navigation Links (desktop) -->
            <div class="hidden sm:flex items-center space-x-2 sm:space-x-4">
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

        <!-- Mobile menu -->
        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="sm:hidden absolute left-0 right-0 top-full bg-white/95 backdrop-blur-sm border-b border-surface-200 shadow-lg z-50">
            <div class="flex flex-col py-3 px-6 space-y-1">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ route('dashboard') }}" class="px-3 py-2.5 text-base font-medium text-primary-700 hover:text-primary-900 transition-colors rounded-lg hover:bg-surface-50" @click="open = false">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-3 py-2.5 text-base font-medium text-surface-700 hover:text-primary-700 transition-colors rounded-lg hover:bg-surface-50" @click="open = false">
                            Accedi
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="px-3 py-2.5 text-base font-medium text-primary-600 hover:text-primary-800 transition-colors rounded-lg hover:bg-primary-50" @click="open = false">
                                Registrati gratis
                            </a>
                        @endif
                    @endauth
                @endif
            </div>
        </div>
    </nav>
</header>

<!-- Overlay mobile: copre solo il contenuto sotto l'header -->
<div x-show="open" x-cloak
     class="fixed inset-x-0 bottom-0 top-16 sm:top-20 z-40 bg-surface-900/40 backdrop-blur-[2px] sm:hidden"
     @click="open = false"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
</div>
</div>
