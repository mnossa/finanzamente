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
    
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    
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
        @yield('content')
    </main>
    
    <!-- Footer -->
    <x-footer />
    
    @stack('scripts')
</body>
</html>
