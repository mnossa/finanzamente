@extends('layouts.guest')

@section('meta-tags')
<title>Cookie Policy — Finanzamente</title>
<meta name="description" content="Cookie Policy di Finanzamente: cookie tecnici, autenticazione, analytics e tecnologie di terze parti attive o future.">
@endsection

@section('content')
@php
    $sessionCookieName = config('session.cookie', 'finanzamente-session');
    $umamiActive = filled(env('UMAMI_ID'));
    $tallyActive = filled(config('prelaunch.tally_form_id')) || filled(config('services.tally.webhook_secret'));
    $googleDriveActive = filled(config('services.google_drive.client_id')) && filled(config('services.google_drive.api_key'));
    $tradingViewActive = true;
@endphp

<x-legal-page-shell
    title="Cookie Policy"
    subtitle="Questa pagina descrive i cookie e le tecnologie simili usati da Finanzamente. Le integrazioni senza credenziali attive in ambiente corrente sono indicate come future e non ancora operative."
    updated-at="09/04/2026"
>
    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">1. Cosa sono cookie e tecnologie simili</h2>
        <p>I cookie sono piccoli file di testo che il browser salva sul dispositivo dell’utente. Oltre ai cookie, alcune integrazioni di terze parti possono usare storage locale, script remoti, pixel o altre tecnologie equivalenti per far funzionare parti del servizio, misurare utilizzo o mostrare contenuti esterni.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">2. Cookie tecnici e strettamente necessari</h2>
        <div class="overflow-x-auto rounded-2xl border border-surface-200">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-surface-100 text-surface-900">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Nome / tecnologia</th>
                        <th class="px-4 py-3 font-semibold">Fornitore</th>
                        <th class="px-4 py-3 font-semibold">Finalità</th>
                        <th class="px-4 py-3 font-semibold">Durata indicativa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-200">
                    <tr>
                        <td class="px-4 py-3 font-medium text-surface-900">{{ $sessionCookieName }}</td>
                        <td class="px-4 py-3">Finanzamente / Laravel</td>
                        <td class="px-4 py-3">Mantiene la sessione dell’utente, il login e le informazioni strettamente tecniche necessarie al funzionamento dell’app.</td>
                        <td class="px-4 py-3">Sessione o fino alla scadenza configurata lato server</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-medium text-surface-900">XSRF-TOKEN</td>
                        <td class="px-4 py-3">Finanzamente / Laravel</td>
                        <td class="px-4 py-3">Protegge i form e le richieste autenticate da tentativi di CSRF.</td>
                        <td class="px-4 py-3">Breve durata, rinnovata durante la navigazione</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-medium text-surface-900">Cookie di “remember me”</td>
                        <td class="px-4 py-3">Finanzamente / Laravel</td>
                        <td class="px-4 py-3">Permettono di mantenere l’accesso se l’utente seleziona l’opzione di login persistente.</td>
                        <td class="px-4 py-3">Persistente, fino a logout o scadenza</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">3. Analytics e misurazione</h2>
        <div class="rounded-2xl border {{ $umamiActive ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} px-4 py-4 text-sm">
            <p><strong>Umami Cloud:</strong> {{ $umamiActive ? 'attivo' : 'futuro / non attivo in questo ambiente' }}.</p>
            <p class="mt-2">Se attivo, viene caricato uno script esterno per misurare visite, pagine ed eventi relativi alle CTA. Prima della pubblicazione definitiva valuta se mantenerlo come strumento privacy-friendly basato su legittimo interesse o se introdurre un meccanismo di consenso esplicito.</p>
        </div>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">4. Tecnologie di terze parti attivate da funzionalità opzionali</h2>
        <div class="overflow-x-auto rounded-2xl border border-surface-200">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-surface-100 text-surface-900">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Servizio</th>
                        <th class="px-4 py-3 font-semibold">Stato</th>
                        <th class="px-4 py-3 font-semibold">Quando si attiva</th>
                        <th class="px-4 py-3 font-semibold">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-200">
                    <tr>
                        <td class="px-4 py-3 font-medium text-surface-900">Tally.so</td>
                        <td class="px-4 py-3">{{ $tallyActive ? 'Attivo' : 'Futuro' }}</td>
                        <td class="px-4 py-3">Quando l’utente apre il micro-sondaggio o interagisce con il widget.</td>
                        <td class="px-4 py-3">Può comportare richieste verso domini Tally e l’uso di tecnologie impostate dal relativo fornitore.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-medium text-surface-900">Google Drive / Google Identity Services</td>
                        <td class="px-4 py-3">{{ $googleDriveActive ? 'Attivo' : 'Futuro' }}</td>
                        <td class="px-4 py-3">Solo quando l’utente decide di importare file tramite Google Drive.</td>
                        <td class="px-4 py-3">Carica script Google e apre il picker Drive; il fornitore può usare propri cookie o tecnologie equivalenti.</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 font-medium text-surface-900">TradingView</td>
                        <td class="px-4 py-3">{{ $tradingViewActive ? 'Attivo' : 'Futuro' }}</td>
                        <td class="px-4 py-3">Quando si visitano le pagine che mostrano widget di mercato o calendario economico.</td>
                        <td class="px-4 py-3">Il caricamento dei widget avviene da domini TradingView e può generare richieste a terze parti.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">5. Servizi futuri non ancora attivi</h2>
        <p>Se in futuro verranno attivati servizi oggi non configurati nell’ambiente corrente, come pagamenti ricorrenti via Mollie o OCR scontrini via Mistral AI, questa policy sarà aggiornata indicando in modo esplicito eventuali cookie, script remoti o tecnologie equivalenti usate da tali fornitori.</p>
    </section>

    <section class="space-y-4">
        <h2 class="text-xl sm:text-2xl font-semibold text-surface-900">6. Gestione preferenze e contatti</h2>
        <p>L’utente può gestire i cookie tecnici tramite il browser, fermo restando che la loro disattivazione può compromettere il funzionamento dell’app. Per richieste privacy, rinvio alla <a href="{{ route('legal.privacy') }}" class="font-semibold text-primary-700 hover:text-primary-800 underline underline-offset-2">Privacy Policy</a>.</p>
    </section>
</x-legal-page-shell>
@endsection