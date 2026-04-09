<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f4ce5">
    <link rel="icon" type="image/webp" href="{{ asset('images/finanzamente-logo.webp') }}">

    {{-- SEO Tags dinamici tramite artesaos/seotools (impostati nei controller) --}}
    {!! SEOMeta::generate() !!}
    {!! OpenGraph::generate() !!}
    {!! Twitter::generate() !!}
    {!! JsonLd::generate() !!}

    {{-- Slot opzionale per override manuali nelle singole viste --}}
    @yield('meta-tags')
    
    <!-- Preconnect + preload font per ridurre render-blocking -->
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link rel="preload" href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet"></noscript>
    
    <!-- Vite for CSS -->
    @vite('resources/js/app-blade.js')
    
    @stack('styles')

    {{-- Umami Analytics --}}
    <script defer src="https://cloud.umami.is/script.js" data-website-id="{{ env('UMAMI_ID') }}"></script>
</head>
<body class="antialiased bg-surface-50 text-surface-900">
    <!-- Skip to main content link for accessibility -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:px-4 focus:py-2 focus:bg-primary-600 focus:text-white focus:rounded-lg focus:shadow-lg">
        Vai al contenuto principale
    </a>
    
    <!-- Header / Navigation -->
    <x-public-nav />
    
    <!-- Main content -->
    <main id="main-content">
        @if(session('info') || session('success') || session('error'))
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                @if(session('info'))
                    <div role="alert" class="flex items-start gap-3 p-4 rounded-xl bg-primary-50 border border-primary-200 text-primary-800 text-sm">
                        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('info') }}</span>
                    </div>
                @endif
                @if(session('success'))
                    <div role="alert" class="flex items-start gap-3 p-4 rounded-xl bg-accent-50 border border-accent-200 text-accent-800 text-sm">
                        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif
                @if(session('error'))
                    <div role="alert" class="flex items-start gap-3 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
                        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif
            </div>
        @endif
        @yield('content')
    </main>
    
    <!-- Footer -->
    <x-footer />
    
    @stack('scripts')
</body>
</html>
