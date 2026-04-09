@extends('layouts.guest')

@section('meta-tags')
<title>In arrivo — Finanzamente</title>
<meta name="robots" content="noindex">
@endsection

@section('content')
<div class="min-h-[80vh] flex items-center justify-center py-16 px-4 bg-surface-50">
    <div class="max-w-lg w-full">

        {{-- Icona --}}
        <div class="flex justify-center mb-6">
            <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center text-3xl" aria-hidden="true">
                🚧
            </div>
        </div>

        {{-- Titolo --}}
        <div class="text-center mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-surface-900 mb-3">
                Sto costruendo Finanzamente
            </h1>
            <p class="text-surface-600 leading-relaxed">
                L'app è in sviluppo attivo. Presto sarà disponibile —
                nel frattempo lasciami la tua email per ricevere l'accesso anticipato,
                oppure dimmi cosa ti serve.
            </p>
        </div>

        {{-- Card azioni --}}
        <div class="bg-white rounded-2xl border border-surface-200 shadow-soft-md divide-y divide-surface-100">

            {{-- Azione 1: waitlist --}}
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="text-2xl mt-0.5" aria-hidden="true">📬</div>
                    <div class="flex-1">
                        <h2 class="text-base font-semibold text-surface-900 mb-1">
                            Accesso anticipato con offerta riservata
                        </h2>
                        <p class="text-sm text-surface-600 mb-4">
                            I primi iscritti riceveranno un'offerta speciale al lancio.
                        </p>
                        <a href="{{ route('home') }}#piani"
                            class="inline-flex items-center px-5 py-2.5 bg-accent-600 hover:bg-accent-700 text-white text-sm font-semibold rounded-xl transition-colors">
                            Iscriviti alla lista d'attesa
                        </a>
                    </div>
                </div>
            </div>

            {{-- Azione 2: survey Tally (opzionale) --}}
            @if(config('prelaunch.tally_form_id'))
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="text-2xl mt-0.5" aria-hidden="true">💬</div>
                    <div class="flex-1">
                        <h2 class="text-base font-semibold text-surface-900 mb-1">
                            Dimmi cosa ti serve
                        </h2>
                        <p class="text-sm text-surface-600 mb-4">
                            30 secondi per aiutarmi a costruire la cosa giusta.
                        </p>
                        @include('partials.landing.tally-survey', [
                            'tallyLabel'   => 'Apri il sondaggio →',
                            'tallyClasses' => 'inline-flex items-center px-5 py-2.5 bg-primary-50 hover:bg-primary-100 text-primary-700 text-sm font-semibold rounded-xl transition-colors no-underline',
                        ])
                    </div>
                </div>
            </div>
            @endif

            {{-- Azione 3: torna alla home --}}
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="text-2xl mt-0.5" aria-hidden="true">🏠</div>
                    <div class="flex-1">
                        <h2 class="text-base font-semibold text-surface-900 mb-1">
                            Scopri cosa farà Finanzamente
                        </h2>
                        <p class="text-sm text-surface-600 mb-4">
                            Tutte le funzionalità previste, già dettagliate nella homepage.
                        </p>
                        <a href="{{ route('home') }}"
                            class="inline-flex items-center px-5 py-2.5 bg-surface-100 hover:bg-surface-200 text-surface-700 text-sm font-semibold rounded-xl transition-colors">
                            Vai alla homepage
                        </a>
                    </div>
                </div>
            </div>

        </div>

        {{-- Nota footer --}}
        <p class="text-center text-xs text-surface-400 mt-6">
            Finanzamente è un progetto indipendente che sto sviluppando con ❤️ in Italia.
        </p>
    </div>
</div>
@endsection
