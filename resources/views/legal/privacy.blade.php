@extends('layouts.guest')

@section('meta-tags')
<title>Privacy Policy — {{ config('app.name') }}</title>
<meta name="description" content="Placeholder informativa privacy. Sostituisci con il testo della tua installazione.">
@endsection

@section('content')
<x-legal-page-shell
    title="Privacy Policy"
    subtitle="Questa pagina è un placeholder. Chi installa Finanzamente deve pubblicare qui la propria informativa privacy prima di esporre il servizio a utenti reali."
    updated-at="06/08/2026"
>
    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">1. Titolare del trattamento</h2>
        <p><strong>[PLACEHOLDER]</strong> Indica nome e cognome o ragione sociale, indirizzo, eventuale P.IVA/C.F. e email dedicata alle richieste privacy.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">2. Dati trattati</h2>
        <p><strong>[PLACEHOLDER]</strong> Elenca i dati che la tua istanza tratta (account, dati finanziari inseriti dagli utenti, log tecnici, integrazioni opzionali come Telegram o Google Drive, ecc.).</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">3. Finalità e basi giuridiche</h2>
        <p><strong>[PLACEHOLDER]</strong> Descrivi finalità (erogazione del servizio, sicurezza, obblighi di legge) e basi giuridiche (contratto, legittimo interesse, consenso dove richiesto).</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">4. Conservazione e diritti</h2>
        <p><strong>[PLACEHOLDER]</strong> Tempi di conservazione, diritti degli interessati (accesso, rettifica, cancellazione, opposizione) e come esercitarli.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">5. Fornitori / trasferimenti</h2>
        <p><strong>[PLACEHOLDER]</strong> Elenca hosting, email, Telegram, Google e ogni altro fornitore usato dalla tua installazione.</p>
    </section>
</x-legal-page-shell>
@endsection
