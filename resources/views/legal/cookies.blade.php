@extends('layouts.guest')

@section('meta-tags')
<title>Cookie — {{ config('app.name') }}</title>
<meta name="description" content="Placeholder informativa cookie. Sostituisci con il testo della tua installazione.">
@endsection

@section('content')
<x-legal-page-shell
    title="Cookie"
    subtitle="Questa pagina è un placeholder. Adattala alla tua installazione (cookie tecnici di sessione, eventuali cookie di terze parti se li attivi)."
    updated-at="06/08/2026"
>
    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">1. Cookie tecnici</h2>
        <p><strong>[PLACEHOLDER]</strong> Di norma l’app usa cookie/sessioni tecnici per autenticazione e preferenze essenziali. Conferma cosa usa la tua istanza.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">2. Cookie di terze parti / analytics</h2>
        <p><strong>[PLACEHOLDER]</strong> Se attivi strumenti di misurazione o widget esterni, descrivili qui e gestisci il consenso dove richiesto.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">3. Gestione preferenze</h2>
        <p><strong>[PLACEHOLDER]</strong> Spiega come l’utente può gestire o revocare i consensi (browser e, se presenti, impostazioni in-app).</p>
    </section>
</x-legal-page-shell>
@endsection
