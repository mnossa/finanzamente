<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/webp" href="{{ asset('images/finanzamente-logo.webp') }}">

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
