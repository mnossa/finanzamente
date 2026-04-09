@extends('layouts.guest')

@section('meta-tags')
<title>Scegli il tuo piano — Finanzamente</title>
<meta name="description" content="Scegli tra il piano Base gratuito e il piano Pro di Finanzamente.">
<meta name="robots" content="noindex">
@endsection

@section('content')
<div class="py-10 sm:py-16">
    <div class="text-center mb-6 sm:mb-10">
        <h1 class="text-2xl sm:text-3xl font-bold text-surface-900 mb-2">
            Scegli il piano giusto per te
        </h1>
        <p class="text-surface-500 text-sm">
            Hai già un account?
            <a href="{{ route('login') }}" class="text-primary-600 hover:text-primary-700 font-medium underline">Accedi</a>
        </p>
    </div>

    @include('partials.landing.pricing', ['targetId' => 'select-plan', 'waitlistEnabled' => $waitlistEnabled])
</div>
@endsection
