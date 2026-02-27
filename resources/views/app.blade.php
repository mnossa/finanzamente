<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        {{-- Inertia React setup: solo entrypoint principale. Le pagine vengono caricate dinamicamente via import.meta.glob in app.tsx --}}
        {{-- Usa wrapper JS per allinearsi al manifest e ambiente dev --}}
        @vite('resources/js/app.tsx')
        @inertiaHead

        {{-- Umami Analytics --}}
        @php
            $umamiId = app()->environment('production')
                ? '6366c67f-6c67-402f-a09b-43f6d73b780c'
                : '804a1613-7828-42fb-9cd8-c6ed1f34644d';
        @endphp
        <script defer src="https://cloud.umami.is/script.js" data-website-id="{{ $umamiId }}"></script>
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
