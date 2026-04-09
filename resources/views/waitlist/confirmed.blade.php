@extends('layouts.guest')

@section('meta-tags')
<title>Iscrizione confermata — Finanzamente</title>
<meta name="robots" content="noindex">
@endsection

@section('content')
<div class="min-h-[60vh] flex items-center justify-center py-16 px-4">
    <div class="max-w-md w-full text-center">
        <div class="text-6xl mb-6">🎉</div>
        <h1 class="text-2xl sm:text-3xl font-bold text-surface-900 mb-3">
            Sei nella waitlist!
        </h1>
        <p class="text-surface-600 mb-8">
            La tua iscrizione è stata confermata. Sarai tra i primi a sapere quando Finanzamente Pro sarà disponibile — con condizioni speciali riservate agli early bird.
        </p>
        <a href="{{ route('home') }}" class="inline-flex items-center px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors">
            Torna alla home
        </a>
    </div>
</div>
@endsection
