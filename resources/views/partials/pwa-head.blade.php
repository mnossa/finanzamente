@php
    $manifestPath = public_path('build/manifest.webmanifest');
    $manifestHref = file_exists($manifestPath) ? asset('build/manifest.webmanifest') : null;
@endphp
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('pwa/icon-192.png') }}">
<link rel="apple-touch-icon" href="{{ asset('pwa/apple-touch-icon.png') }}">
@if ($manifestHref)
    <link rel="manifest" href="{{ $manifestHref }}">
@endif
<link rel="apple-touch-startup-image" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)" href="{{ asset('pwa/apple-splash-1170x2532.png') }}">
<link rel="apple-touch-startup-image" media="(device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3)" href="{{ asset('pwa/apple-splash-1284x2778.png') }}">
