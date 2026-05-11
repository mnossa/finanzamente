@extends('layouts.guest')

@section('meta-tags')
<title>Privacy Policy — Finanzamente</title>
<meta name="description" content="Informativa privacy di Finanzamente: dati trattati, finalità, basi giuridiche, fornitori esterni e diritti dell'interessato.">
@endsection

@section('content')
@php
    $services = [
        ['name' => 'Laravel sessione e autenticazione', 'status' => 'Attivo', 'type' => 'Infrastruttura applicativa', 'data' => 'Dati di sessione, autenticazione, preferenze tecniche', 'purpose' => 'Accesso all’area autenticata, sicurezza, mantenimento della sessione'],
        ['name' => 'Umami Cloud', 'status' => filled(env('UMAMI_ID')) ? 'Attivo' : 'Futuro', 'type' => 'Analytics', 'data' => 'Dati di navigazione ed eventi di utilizzo', 'purpose' => 'Statistiche aggregate, misurazione pagine e CTA'],
        ['name' => 'Brevo', 'status' => filled(config('services.brevo.api_key')) ? 'Attivo' : 'Futuro', 'type' => 'Email marketing / waitlist', 'data' => 'Email, stato double opt-in, attributi tecnici come SIGNATURE', 'purpose' => 'Gestione waitlist, accesso anticipato, email pre-lancio'],
        ['name' => 'Tally.so', 'status' => (filled(config('prelaunch.tally_form_id')) || filled(config('services.tally.webhook_secret'))) ? 'Attivo' : 'Futuro', 'type' => 'Form e survey', 'data' => 'Risposte al sondaggio ed eventuale email inserita', 'purpose' => 'Raccolta feedback e qualificazione della waitlist'],
        ['name' => 'Google Drive', 'status' => (filled(config('services.google_drive.client_id')) && filled(config('services.google_drive.api_key'))) ? 'Attivo' : 'Futuro', 'type' => 'Import file', 'data' => 'Access token OAuth, file selezionati e relativo contenuto importato', 'purpose' => 'Importazione dati bancari o di investimento da file Drive'],
        ['name' => 'Telegram Bot', 'status' => filled(config('services.telegram.bot_token')) ? 'Attivo' : 'Futuro', 'type' => 'Integrazione messaggistica', 'data' => 'Telegram chat ID, messaggi inviati al bot, foto/scontrini, metadata di collegamento', 'purpose' => 'Inserimento rapido spese e gestione Inbox'],
        ['name' => 'TradingView', 'status' => 'Attivo', 'type' => 'Widget dati finanziari', 'data' => 'Dati tecnici di caricamento pagina e interazione con il widget', 'purpose' => 'Visualizzazione panoramica mercati e calendario economico'],
        ['name' => 'Yahoo Finance / Alpha Vantage', 'status' => (filled(config('services.yahoo_finance.key')) || filled(config('services.alpha_vantage.key'))) ? 'Attivo' : 'Futuro', 'type' => 'Provider dati finanziari', 'data' => 'Ticker, ISIN, richieste dati finanziari', 'purpose' => 'Ricerca strumenti e prezzi di mercato'],
        ['name' => 'Mistral AI', 'status' => filled(config('services.mistral.api_key')) ? 'Attivo' : 'Futuro', 'type' => 'OCR / AI', 'data' => 'Immagini di scontrini e contenuto estratto', 'purpose' => 'Estrazione automatica di importo, negozio e data'],
        ['name' => 'Mollie', 'status' => filled(config('services.mollie.key')) ? 'Attivo' : 'Futuro', 'type' => 'Pagamenti e abbonamenti', 'data' => 'Dati cliente, dati di fatturazione, customer ID, mandate ID, subscription ID', 'purpose' => 'Gestione pagamenti e rinnovi del piano Pro'],
    ];
@endphp

<x-legal-page-shell
    title="Privacy Policy"
    subtitle="Questa informativa descrive quali dati personali tratto in Finanzamente, per quali finalità e con quali fornitori esterni. I servizi senza credenziali attive nell’ambiente corrente sono indicati come futuri e non ancora operativi."
    updated-at="11/05/2026"
>
    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">1. Titolare del trattamento</h2>
        <p>Il titolare del trattamento è il soggetto che gestisce Finanzamente. Prima della pubblicazione definitiva sostituisci questa sezione con nome e cognome o ragione sociale, indirizzo completo, eventuale partita IVA e un indirizzo email dedicato alle richieste privacy.</p>
        <p>Fino a tale integrazione, usa come contatto operativo almeno un indirizzo email verificato e monitorato con continuità.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">2. Dati personali trattati</h2>
        <ul class="list-disc pl-5 space-y-2">
            <li>dati anagrafici e di account: nome, cognome, email, password cifrata, data di nascita, impostazioni profilo;</li>
            <li>dati finanziari inseriti o importati: conti, transazioni, budget, categorie, obiettivi, household, allegati e note;</li>
            <li>dati fiscali o di fatturazione, se richiesti per il piano Pro: intestazione, indirizzo, partita IVA o codice fiscale, email di fatturazione;</li>
            <li>dati di pre-lancio: email waitlist, stato double opt-in, eventuali risposte ai sondaggi Tally;</li>
            <li>dati di integrazione facoltativa: chat ID Telegram, messaggi e immagini inviati al bot, file selezionati da Google Drive, immagini di scontrini elaborate via OCR;</li>
            <li>dati tecnici: log applicativi, informazioni di sessione, eventi analytics e metadati di sicurezza.</li>
        </ul>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">3. Finalità e basi giuridiche</h2>
        <div class="overflow-x-auto rounded-2xl border border-surface-200">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-surface-100 text-surface-900">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Finalità</th>
                        <th class="px-4 py-3 font-semibold">Base giuridica</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-200">
                    <tr>
                        <td class="px-4 py-3">Creazione account, autenticazione, accesso alla dashboard e gestione dei dati finanziari</td>
                        <td class="px-4 py-3">Esecuzione del contratto o misure precontrattuali</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Sicurezza, prevenzione abusi, rate limiting, logging tecnico e protezione dell’infrastruttura</td>
                        <td class="px-4 py-3">Legittimo interesse del titolare e obblighi di sicurezza</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Waitlist, accesso anticipato e comunicazioni pre-lancio tramite Brevo</td>
                        <td class="px-4 py-3">Consenso dell’interessato</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Survey Tally per capire priorità di prodotto e segmentare la domanda</td>
                        <td class="px-4 py-3">Consenso e legittimo interesse a validare il prodotto</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Analytics di utilizzo e misurazione delle pagine/CTA</td>
                        <td class="px-4 py-3">Legittimo interesse o consenso, in base alla configurazione finale che sarà mantenuta online</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Analisi interne in forma aggregata o anonimizzata (es. tendenze d’uso, qualità del servizio, sicurezza)</td>
                        <td class="px-4 py-3">Legittimo interesse del titolare e, per le fasi che coinvolgono ancora dati personali, coerenza con le basi giuridiche indicate per analytics e sicurezza</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3">Pagamenti, rinnovi e fatturazione del piano Pro</td>
                        <td class="px-4 py-3">Esecuzione del contratto e obblighi legali fiscali</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">4. Statistiche aggregate, anonimizzazione e miglioramento del prodotto</h2>
        <p>Finanzamente può utilizzare informazioni relative all’uso del servizio anche dopo averle <strong>aggregate</strong> (riepiloghi statistici che non consentono di risalire al singolo utente) oppure <strong>anonimizzate</strong> secondo tecniche che rendono i dati non più riconducibili a una persona fisica identificata o identificabile.</p>
        <p>Queste elaborazioni servono a finalità come: miglioramento dell’esperienza utente, affidabilità e sicurezza del servizio, comprensione macroscopica di come vengono usate le funzionalità e pianificazione del prodotto. Quando il risultato è effettivamente <strong>non personale</strong>, non costituisce trattamento di dati personali ai sensi del GDPR (cfr. considerando 26 del Regolamento UE 2016/679).</p>
        <p>Le fasi tecniche che precedono l’aggregazione o l’anonimizzazione possono comunque comportare il trattamento di dati personali (ad esempio log o eventi collegati all’account): per tali fasi si applicano le basi giuridiche già indicate in questa informativa per sicurezza, analytics e gestione del servizio. Non si effettuano decisioni che producono <strong>effetti giuridici o altri effetti significativi</strong> sull’interessato basate unicamente su trattamenti automatizzati di profilazione ai sensi dell’articolo 22 GDPR, salvo quanto diversamente previsto in futuro con informativa dedicata e, ove richiesto, consenso o altre misure di tutela.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">5. Fornitori esterni e stato di attivazione</h2>
        <p>I servizi che non hanno ancora credenziali attive nell’ambiente corrente sono considerati <strong>futuri</strong>: il codice può già prevederli, ma il trattamento non risulta ancora operativo in questa configurazione.</p>
        <div class="overflow-x-auto rounded-2xl border border-surface-200">
            <table class="min-w-full text-left text-sm align-top">
                <thead class="bg-surface-100 text-surface-900">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Servizio</th>
                        <th class="px-4 py-3 font-semibold">Stato</th>
                        <th class="px-4 py-3 font-semibold">Ruolo</th>
                        <th class="px-4 py-3 font-semibold">Dati trattati</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-200">
                    @foreach ($services as $service)
                        <tr>
                            <td class="px-4 py-3 font-medium text-surface-900">{{ $service['name'] }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $service['status'] === 'Attivo' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $service['status'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $service['type'] }}<br><span class="text-xs text-surface-500">{{ $service['purpose'] }}</span></td>
                            <td class="px-4 py-3">{{ $service['data'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">6. Conservazione dei dati</h2>
        <ul class="list-disc pl-5 space-y-2">
            <li>dati account e dati finanziari: per la durata dell’account e, dopo cancellazione, per il tempo strettamente necessario a backup, sicurezza e gestione di contestazioni;</li>
            <li>dati di fatturazione e documenti rilevanti fiscalmente: per i termini previsti dalla legge applicabile;</li>
            <li>dati waitlist e marketing: fino a revoca del consenso o, in assenza di interazioni, per un periodo da definire nella versione finale della presente informativa;</li>
            <li>log tecnici e dati di sicurezza: per il tempo strettamente necessario a prevenzione abusi, auditing e troubleshooting.</li>
        </ul>
        <p>Prima della pubblicazione definitiva conviene sostituire questa sezione con tempi precisi e coerenti con i backup reali, il provider hosting e la tua operatività fiscale.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">7. Trasferimenti verso Paesi terzi</h2>
        <p>Alcuni fornitori elencati sopra possono trattare dati su infrastrutture situate fuori dallo Spazio Economico Europeo o appartenenti a gruppi internazionali. Prima della pubblicazione definitiva verifica per ogni fornitore il luogo di trattamento, l’eventuale adesione a framework applicabili e le clausole contrattuali standard eventualmente utilizzate.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">8. Diritti dell’interessato</h2>
        <p>L’utente può chiedere accesso, rettifica, cancellazione, limitazione del trattamento, opposizione, portabilità dei dati e revoca del consenso per i trattamenti basati su consenso. Può inoltre proporre reclamo all’autorità di controllo competente.</p>
        <p>Nell’implementazione attuale dell’app, l’utente autenticato può già:
            revocare o aggiornare i consensi opzionali dal profilo,
            esportare lo storico consensi in formato JSON,
            richiedere la cancellazione dell’account dalla sezione profilo.</p>
        <p>Indica nella versione finale un canale semplice e stabile per esercitare tali diritti, ad esempio un indirizzo email privacy dedicato.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">9. Collegamenti utili</h2>
        <p>Per una descrizione specifica dei cookie e delle tecnologie equivalenti consulta anche la <a href="{{ route('legal.cookies') }}" class="font-semibold text-primary-700 hover:text-primary-800 underline underline-offset-2">Cookie Policy</a>. Per le regole di utilizzo del servizio consulta i <a href="{{ route('legal.terms') }}" class="font-semibold text-primary-700 hover:text-primary-800 underline underline-offset-2">Termini di servizio</a>.</p>
    </section>
</x-legal-page-shell>
@endsection