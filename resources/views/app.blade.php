<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class([
    'dark' => auth()->check() && data_get(auth()->user()->preferences, 'theme') === 'dark',
    'fm-hide-balances' => auth()->check() && data_get(auth()->user()->preferences, 'hide_balances') === true,
])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#4f4ce5">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Finanzamente">
        @include('partials.pwa-head')

        <title inertia>{{ config('app.name', 'Finanzamente') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link rel="preload" href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet"></noscript>

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        {{-- Inertia React setup: solo entrypoint principale. Le pagine vengono caricate dinamicamente via import.meta.glob in app.tsx --}}
        {{-- Usa wrapper JS per allinearsi al manifest e ambiente dev --}}
        @vite('resources/js/app.tsx')
        @inertiaHead

    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
