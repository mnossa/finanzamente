<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f4ce5">
    <link rel="icon" type="image/webp" href="{{ asset('images/finanzamente-logo.webp') }}">

    {!! SEOMeta::generate() !!}
    {!! OpenGraph::generate() !!}
    {!! Twitter::generate() !!}
    {!! JsonLd::generate() !!}

    @yield('meta-tags')

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="preload" href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet"></noscript>

    @vite('resources/js/app-blade.js')

    @stack('styles')

    {{-- Umami Analytics --}}
    <script defer src="https://cloud.umami.is/script.js" data-website-id="{{ env('UMAMI_ID') }}"></script>
</head>
<body class="antialiased bg-white text-surface-900">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-primary-600 focus:text-white focus:rounded-lg focus:shadow-lg">
        Vai al contenuto principale
    </a>

    {{-- Minimal header: solo logo + CTA. Nessun menu di navigazione. --}}
    <header class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-sm border-b border-surface-100" role="banner">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-14 sm:h-16">
                <a href="{{ url('/') }}" class="flex items-center space-x-2" aria-label="Finanzamente — torna alla home">
                    <img src="{{ asset('images/finanzamente-logo.webp') }}" alt="" class="w-7 h-7 sm:w-8 sm:h-8" loading="eager" aria-hidden="true">
                    <span class="text-lg sm:text-xl font-bold bg-gradient-to-r from-primary-700 to-primary-900 bg-clip-text text-transparent">Finanzamente</span>
                </a>
                @if (Route::has('plan.select'))
                    <a href="{{ route('plan.select') }}?plan=pro&billing_cycle=monthly"
                       class="inline-flex items-center px-4 sm:px-5 py-2 text-sm font-semibold text-white bg-accent-600 hover:bg-accent-700 rounded-lg transition-colors duration-200"
                       data-umami-event="landing-header-cta"
                       data-umami-event-page="{{ $landingPage ?? 'unknown' }}">
                        Abbonati a Pro
                    </a>
                @endif
            </div>
        </div>
    </header>

    <main id="main-content" class="pt-14 sm:pt-16">
        @yield('content')
    </main>

    <footer class="py-6 border-t border-surface-100 bg-white" role="contentinfo">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row justify-between items-center gap-3 text-sm text-surface-500">
            <span>© {{ date('Y') }} Finanzamente</span>
            <nav aria-label="Link legali" class="flex gap-4">
                <a href="{{ route('legal.privacy') }}" class="hover:text-surface-700 transition-colors">Privacy</a>
                <a href="{{ route('legal.terms') }}" class="hover:text-surface-700 transition-colors">Termini</a>
                <a href="{{ route('legal.cookies') }}" class="hover:text-surface-700 transition-colors">Cookie</a>
                <a href="{{ url('/') }}" class="hover:text-surface-700 transition-colors">Home</a>
            </nav>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
