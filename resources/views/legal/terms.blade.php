@extends('layouts.guest')

@section('meta-tags')
<title>Termini di Servizio — Finanzamente</title>
<meta name="description" content="Termini di servizio di Finanzamente: regole d'uso, limitazioni, account, pre-lancio, piano Pro e integrazioni esterne.">
@endsection

@section('content')
@php
    $mollieActive = filled(config('services.mollie.key'));
    $mistralActive = filled(config('services.mistral.api_key'));
@endphp

<x-legal-page-shell
    title="Termini di Servizio"
    subtitle="Questi termini disciplinano l’uso di Finanzamente. I moduli o i provider esterni non ancora configurati nell’ambiente corrente sono indicati come futuri e non devono essere considerati disponibili finché non saranno effettivamente attivati."
    updated-at="09/04/2026"
>
    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">1. Oggetto del servizio</h2>
        <p>Finanzamente è una web app per la gestione delle finanze personali. Consente di registrare movimenti, organizzare conti e categorie, monitorare budget, obiettivi, household e altri dati finanziari personali. Alcune funzioni possono dipendere da integrazioni esterne o essere disponibili solo per determinati piani.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">2. Natura informativa del servizio</h2>
        <p>Finanzamente non fornisce consulenza finanziaria, fiscale, contabile o legale. Dati, simulazioni, OCR, prezzi di mercato, classificazioni automatiche e suggerimenti hanno finalità esclusivamente organizzative e informative. L’utente resta responsabile delle proprie decisioni economiche, fiscali e patrimoniali.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">3. Account e responsabilità dell’utente</h2>
        <ul class="list-disc pl-5 space-y-2">
            <li>l’utente deve fornire dati veritieri e mantenerli aggiornati;</li>
            <li>le credenziali di accesso devono essere custodite con cura e non condivise con terzi non autorizzati;</li>
            <li>l’utente è responsabile dei contenuti caricati, importati o inviati tramite integrazioni esterne, inclusi file, immagini e messaggi Telegram;</li>
            <li>è vietato usare il servizio per finalità illecite, fraudolente o lesive di diritti altrui.</li>
        </ul>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">4. Disponibilità del servizio e pre-lancio</h2>
        <p>Il servizio può trovarsi in fase di sviluppo, beta o pre-lancio. In tali fasi alcune funzionalità possono essere incomplete, cambiare senza preavviso o risultare non accessibili al pubblico. L’eventuale iscrizione alla waitlist o alla survey non garantisce accesso immediato, prezzo definitivo o disponibilità in una data certa.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">5. Piani, abbonamenti e pagamenti</h2>
        <p>Finanzamente può prevedere un piano Base e un piano Pro con funzioni diverse. L’attivazione del piano Pro può dipendere da un fornitore esterno di pagamento.</p>
        <div class="rounded-2xl border {{ $mollieActive ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} px-4 py-4 text-sm">
            <p><strong>Mollie:</strong> {{ $mollieActive ? 'attivo nell’ambiente corrente' : 'non ancora attivo nell’ambiente corrente' }}.</p>
            <p class="mt-2">{{ $mollieActive ? 'Se il checkout è attivo, i pagamenti e i rinnovi del piano Pro avvengono tramite Mollie, secondo le relative condizioni del fornitore.' : 'Finché il provider di pagamento non è attivo, i riferimenti a piano Pro, abbonamenti o rinnovi devono essere considerati informativi o di pre-lancio e potrebbero non essere acquistabili.' }}</p>
        </div>
        <p>Prima della pubblicazione definitiva completa questa sezione con prezzo, periodicità, regole di rinnovo, cancellazione, eventuale prova gratuita, fatturazione, rimborsi e gestione del mini-addebito tecnico di verifica del metodo di pagamento se manterrai il flusso attuale.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">6. Integrazioni esterne</h2>
        <p>Finanzamente può integrare servizi di terze parti, tra cui analytics, survey, email marketing, Google Drive, Telegram, provider dati finanziari, widget di mercato, OCR e pagamenti. Tali fornitori operano secondo proprie condizioni e informative privacy.</p>
        <div class="rounded-2xl border {{ $mistralActive ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} px-4 py-4 text-sm">
            <p><strong>Mistral AI / OCR scontrini:</strong> {{ $mistralActive ? 'attivo' : 'futuro / non ancora attivo' }}.</p>
            <p class="mt-2">Se attivato, l’utente accetta che immagini di scontrini o allegati simili possano essere inviate al provider esterno per estrarre dati strutturati. L’accuratezza dell’estrazione non è garantita.</p>
        </div>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">7. Accuratezza dei dati e limitazione di responsabilità</h2>
        <p>Pur adottando misure ragionevoli per mantenere il servizio affidabile, non garantisco che dati di mercato, import automatici, OCR, sincronizzazioni esterne o contenuti generati da integrazioni terze siano completi, aggiornati o privi di errori. Nei limiti consentiti dalla legge, il servizio è fornito “così com’è”.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">8. Sospensione, modifica o chiusura del servizio</h2>
        <p>Posso modificare, sospendere o interrompere in tutto o in parte il servizio, specialmente in fase di sviluppo, per ragioni tecniche, di sicurezza, manutenzione, sostenibilità del progetto o violazioni dei presenti termini.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">9. Proprietà intellettuale</h2>
        <p>Codice, interfaccia, testi, marchi, loghi, struttura e materiali originali di Finanzamente restano riservati al rispettivo titolare dei diritti. Restano esclusi i contenuti caricati dall’utente e i materiali di terze parti soggetti alle rispettive licenze.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">10. Legge applicabile e foro</h2>
        <p>Questa sezione va completata prima della pubblicazione finale con la legge applicabile e il foro competente coerenti con la tua struttura soggettiva e con l’eventuale natura consumer del servizio.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">11. Documenti collegati</h2>
        <p>L’uso del servizio è regolato congiuntamente ai presenti termini, alla <a href="{{ route('legal.privacy') }}" class="font-semibold text-primary-700 hover:text-primary-800 underline underline-offset-2">Privacy Policy</a> e alla <a href="{{ route('legal.cookies') }}" class="font-semibold text-primary-700 hover:text-primary-800 underline underline-offset-2">Cookie Policy</a>.</p>
    </section>
</x-legal-page-shell>
@endsection