# Servizi Integrati In Finanzamente

Documento operativo per censire i servizi esterni e le tecnologie rilevanti ai fini di privacy policy, cookie policy, termini di servizio e configurazioni future con strumenti come Iubenda.

Questo documento non contiene token, chiavi API o segreti.

## Stato attuale

La classificazione qui sotto segue l'ambiente corrente del progetto:
- `Attivo`: il servizio è integrato e risulta configurato o utilizzato nel codice corrente.
- `Futuro`: il servizio è previsto dal codice ma non risulta attivo perché mancano credenziali o configurazione nell'ambiente corrente.
- `Condizionale`: il servizio è attivo solo quando l'utente usa una funzione specifica o visita una pagina specifica.

## Elenco servizi

| Servizio | Stato | Categoria | Uso nel prodotto | Dati coinvolti | Note operative |
|---|---|---|---|---|---|
| Laravel sessione/autenticazione | Attivo | Core app | Login, sessione, CSRF, remember me | sessione, autenticazione, token tecnici | Cookie tecnici propri |
| Umami Cloud | Attivo | Analytics | Misurazione pagine e CTA | dati di navigazione, eventi | Script caricato nei layout Blade e React |
| Brevo | Attivo | Email marketing / waitlist | Double opt-in, waitlist, early access | email, stato opt-in, attributi contatto | Collegato alla waitlist pubblica |
| Tally.so | Attivo | Form / survey | Micro-sondaggio pre-lancio | risposte survey, eventuale email | Widget JS + webhook verso Brevo |
| Google Drive | Attivo | Import file | Selezione e download file per importazioni | access token OAuth, file selezionati | Attivo solo se l'utente usa l'import |
| Telegram Bot | Attivo | Messaggistica / input rapido | Invio spese, foto scontrini, collegamento account | chat ID, messaggi, foto, token collegamento | Webhook pubblico |
| TradingView | Attivo | Widget dati finanziari | Panoramica mercati e calendario economico | dati tecnici di caricamento pagina | Script/widget terzi nelle pagine investimenti |
| Yahoo Finance | Attivo | Provider dati finanziari | Ricerca ticker e prezzi | ticker, ISIN, richieste dati mercato | Usato lato server |
| Alpha Vantage | Attivo | Provider dati finanziari | Fallback / ricerca e prezzi | ticker, ISIN, richieste dati mercato | Usato lato server |
| Bunny Fonts | Attivo | Font CDN | Caricamento font interfaccia | richieste HTTP verso CDN font | Presente nei layout pubblici e app |
| Mollie | Futuro | Pagamenti / abbonamenti | Checkout, rinnovi, mandate | dati fatturazione, customer ID, mandate ID | Codice presente ma provider non attivo nell'ambiente corrente |
| Mistral AI | Futuro | OCR / AI | Estrazione dati da scontrini | immagini scontrini, dati estratti | Codice presente ma API key assente |
| AWS S3 / SES | Futuro | Storage / mail | Storage cloud o invio email via provider AWS | file, email, metadati tecnici | Configurazione prevista ma non attiva nell'ambiente corrente |
| Postmark / Resend / Slack | Futuro | Mail / notifiche interne | Previsti da configurazione Laravel | variabile in base all'uso | Non risultano attivi nell'ambiente corrente |

## Servizi che possono installare cookie o tecnologie simili

| Servizio | Stato | Impatto potenziale |
|---|---|---|
| Laravel sessione | Attivo | Cookie tecnici di sessione e sicurezza |
| Remember me Laravel | Attivo | Cookie persistente di autenticazione |
| Umami Cloud | Attivo | Script analytics ed eventi |
| Tally.so | Attivo | Widget terzo che può usare cookie o storage propri |
| Google Drive / Google Identity Services | Condizionale | Script Google e possibili cookie terzi quando l'utente apre il picker |
| TradingView | Condizionale | Widget esterni e richieste verso domini TradingView |
| Mollie | Futuro | Checkout e pagina pagamento su dominio terzo |

## Fonti nel codice

### Configurazioni principali
- `config/services.php`
- `config/session.php`
- `config/filesystems.php`
- `config/mail.php`

### Integrazioni applicative
- `app/Services/WaitlistService.php`
- `app/Http/Controllers/WaitlistController.php`
- `app/Services/MollieService.php`
- `app/Http/Controllers/MollieWebhookController.php`
- `app/Services/TelegramService.php`
- `app/Http/Controllers/TelegramWebhookController.php`
- `app/Services/GoogleDriveService.php`
- `app/Services/VisionService.php`
- `app/Services/AssetPriceService.php`

### Frontend / script esterni
- `resources/views/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/layouts/landing-minimal.blade.php`
- `resources/views/partials/landing/tally-survey.blade.php`
- `resources/js/Components/UmamiAnalytics.tsx`
- `resources/js/Components/GoogleDrivePicker.tsx`
- `resources/js/Components/TradingViewWidget.tsx`

## Checklist utile per Iubenda o strumenti simili

- titolare del trattamento completo
- email privacy dedicata
- hosting reale e paese di trattamento
- provider email reale in produzione
- conferma se Umami resta attivo senza consenso o con banner
- tempi di conservazione definitivi
- politica rimborsi e rinnovi del piano Pro
- verifica trasferimenti extra SEE per ogni fornitore
- verifica testi pubblici che parlano di "nessun tracciamento"

## Nota finale

Se in futuro attivi nuovi provider o completi l'integrazione di servizi oggi segnati come futuri, aggiorna questo documento prima di aggiornare privacy policy, cookie policy e termini.