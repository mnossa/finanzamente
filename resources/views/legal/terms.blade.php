@extends('layouts.guest')

@section('meta-tags')
<title>Termini di servizio — {{ config('app.name') }}</title>
<meta name="description" content="Placeholder termini di servizio. Sostituisci con il testo della tua installazione.">
@endsection

@section('content')
<x-legal-page-shell
    title="Termini di servizio"
    subtitle="Questa pagina è un placeholder. Il software applicativo è rilasciato sotto licenza MIT (vedi LICENSE nel repository). I termini qui sotto riguardano l’uso della tua istanza online, non la licenza del codice."
    updated-at="06/08/2026"
>
    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">1. Oggetto</h2>
        <p><strong>[PLACEHOLDER]</strong> Descrivi il servizio che offri con la tua installazione di Finanzamente (self-host personale, uso interno, ecc.).</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">2. Account e responsabilità</h2>
        <p><strong>[PLACEHOLDER]</strong> Regole di registrazione, uso corretto, responsabilità dell’utente sui dati inseriti.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">3. Disponibilità e garanzie</h2>
        <p><strong>[PLACEHOLDER]</strong> Il software è fornito “as is” secondo la MIT. Per il servizio online definisci SLA (se esistono) e limiti di responsabilità.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">4. Licenza del codice</h2>
        <p>Il codice sorgente di Finanzamente è rilasciato sotto <strong>MIT License</strong>, Copyright (c) 2026 Matteo Nossa. Consulta il file <code>LICENSE</code> nel repository.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">5. Contatti</h2>
        <p><strong>[PLACEHOLDER]</strong> Indirizzo email o altri canali per segnalazioni relative a questa istanza.</p>
    </section>
</x-legal-page-shell>
@endsection
