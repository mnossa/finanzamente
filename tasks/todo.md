# Pre-Launch Features — Finanzamente

## Piano

- [x] Installare Brevo SDK (`sendinblue/api-v3-sdk ^8.4`)
- [x] Creare `config/prelaunch.php` (flag pre-lancio, owner email, waitlist)
- [x] Aggiornare `config/services.php` con configurazione Brevo
- [x] Creare `WaitlistService` (HMAC signature + Brevo API double opt-in)
- [x] Creare `WaitlistController` (rotta POST /waitlist)
- [x] Aggiungere rotta `POST /waitlist` in `routes/web.php` (rate limited 3/5min)
- [x] Aggiungere middleware `pre-launch` in `bootstrap/app.php`
- [x] Creare `PreLaunchMiddleware` (accesso solo owner in pre-lancio)
- [x] Applicare `pre-launch` ai route group autenticati (auth+verified e auth+verified+household)
- [x] Aggiornare `RegisteredUserController`: pre-launch check + early bird HMAC
- [x] Creare migrazione `add_is_early_bird_to_users_table`
- [x] Aggiornare `User` model (fillable + cast `is_early_bird`)
- [x] Aggiornare `HandleInertiaRequests` per condividere `isEarlyBird`
- [x] Aggiornare `welcome.blade.php`: form waitlist al posto del CTA Pro (quando `PRO_WAITLIST_ENABLED=true`)
- [x] Aggiornare `WelcomeController` per passare `waitlistEnabled` alla vista
- [x] Aggiornare TypeScript types (`PageProps.isEarlyBird`)
- [x] Aggiornare `.env.example` con le nuove variabili
- [x] Scrivere test Feature (`tests/Feature/WaitlistTest.php`) — 16 test, tutti passati

## Variabili d'ambiente aggiunte

| Variabile                         | Default  | Descrizione                                      |
|-----------------------------------|----------|--------------------------------------------------|
| `PRE_LAUNCH_MODE`                 | `false`  | Attiva modalità pre-lancio (solo owner)          |
| `PRE_LAUNCH_OWNER_EMAIL`          | `""`     | Email del proprietario autorizzato               |
| `PRO_WAITLIST_ENABLED`            | `false`  | Sostituisce CTA Pro con form waitlist            |
| `BREVO_API_KEY`                   | `""`     | API key Brevo                                    |
| `BREVO_WAITLIST_LIST_ID`          | `0`      | ID lista Brevo per waitlist Pro                  |
| `BREVO_DOUBLE_OPTIN_TEMPLATE_ID`  | `0`      | ID template double opt-in Brevo                  |
| `BREVO_DOUBLE_OPTIN_REDIRECT_URL` | `""`     | URL di redirect dopo conferma double opt-in      |

## Review

- Test suite completa: 427 test, tutti passati
- Nessun dato personale loggato in chiaro (GDPR compliant)
- Firma HMAC generata con `hash_hmac('sha256', email, APP_KEY)`, verificata con `hash_equals`
- Double opt-in delegato a Brevo via `createDoiContact` API
- Middleware pre-lancio: logout automatico per non-owner, redirect con messaggio informativo
- Early bird flag salvato su `users.is_early_bird` e condiviso via Inertia

---

## Feature Gap Remediation — Finanzamente

### Piano Remediation Gap

### P0 — Copertura E2E moduli critici

- [x] Creare smoke E2E Investimenti (`e2e/investments/investments.spec.ts`)
  - [x] verifica accesso pagina index
  - [x] creazione record investimento base
  - [x] import base (happy path)
- [x] Creare smoke E2E Subscription/Billing (`e2e/subscription/subscription.spec.ts`)
  - [x] apertura pagina piano/profilo subscription
  - [x] avvio checkout con mock callback/webhook
  - [x] verifica stato subscription aggiornato
- [x] Creare smoke E2E Inter-household transfers (`e2e/inter-household/inter-household.spec.ts`)
  - [x] creazione trasferimento
  - [x] verifica lista
  - [x] verifica dettaglio

### P1 — Coerenza dominio GDPR + codice orfano

- [x] Audit tecnico consensi GDPR
  - [x] confermare scope prodotto: consensi granulari richiesti ora o roadmap
  - [x] se richiesti ora: definire schema tabella consensi
  - [x] definire tracciamento eventi consenso/revoca
  - [x] definire policy retention minima documentata
- [x] Risolvere modulo charts orfano
  - [x] opzione A: aggiungere route + voce nav + test
  - [x] opzione B: rimuovere controller/pagina non usati
  - [x] decisione registrata in docs

### P2 — Miglioria documentazione e tracciabilità

- [x] Creare `docs/feature-matrix.md`
  - [x] colonna stato dichiarata
  - [x] colonna backend implementato
  - [x] colonna frontend implementato
  - [x] colonna test Unit/Feature
  - [x] colonna test E2E
- [x] Marcare feature in docs con label `Attiva` / `Parziale` / `Roadmap`
- [x] Aggiungere guida pre-push in doc contributor: `make test-ci` (+ eventuale smoke E2E)

## Criteri di completamento

- [x] Tutti nuovi spec E2E verdi in locale con `make playwright`
- [x] Nessun lint error su file toccati
- [x] Aggiornata documentazione stato feature
- [x] Gap P0 chiusi

### Review Remediation Gap

- Fonte analisi: `docs/feature-gap-audit.md`
- Data pianificazione: 2026-04-28

### Review aggiornamento 2026-04-28

- P0 E2E smoke completato e verificato con suite `make playwright` verde (172 passed, 12 skipped).
- Modulo charts orfano chiuso con decisione "legacy": rimozione codice non raggiungibile.
- Introdotto `docs/feature-matrix.md` con stato `Attiva/Parziale/Roadmap` e tracciabilità backend/frontend/test.
- Aggiunta `docs/contributor-guide.md` con workflow pre-push (`make test-ci`) e smoke E2E.
- Completato audit GDPR tecnico con schema implementabile in `docs/gdpr-consent-technical-audit.md`.
- Completato smoke E2E subscription con checkout mock + webhook mock + verifica stato finale.
- Implementato baseline consensi GDPR: migrazioni, model, `ConsentService`, comando retention schedulato, test Feature/Unit verdi.
- Integrato `ConsentService` nei flussi registrazione/profilo con UI preferenze privacy e test automatici aggiornati.
- Aggiunto export GDPR storico consensi (`/profilo/consensi/export`) con test Feature dedicato.
- Aggiunta revoca in blocco consensi opzionali (`/profilo/consensi/revoca-opzionali`) con UI e test Feature.

---

# Multi-currency support — 2026-05-08

## Decisioni prese

| Tema | Scelta |
|---|---|
| Provider FX automatico | **Frankfurter** (https://api.frankfurter.dev), no API key, basato su BCE |
| Sintassi override rate nel bot | Carattere `~` → `30 GBP cena ~1.18` (1 GBP = 1.18 EUR) |
| Scope intervento | Tutte e 3 le fasi insieme (PR singola): fondazioni FX + bot Telegram + UI web |
| Default currency | Preferenza utente in profilo (`users.default_currency_code`), fallback EUR |
| Valuta base | EUR (fissa, non configurabile in questa fase) |

## Fase 1 — Fondazioni FX

- [x] Migrazione `create_exchange_rates_table` (base_code, quote_code, date, rate, source, unique composto + id surrogato)
- [x] Migrazione `add_currency_columns_to_transactions` (`exchange_rate_to_base`, `amount_base`, `original_amount`, `original_currency_code` con FK + backfill inline)
- [x] Migrazione `add_currency_columns_to_inbox_items` (5 colonne + 2 FK + backfill inline)
- [x] Migrazione `add_default_currency_to_users` (`default_currency_code` nullable FK currencies)
- [x] Service `CurrencyConverter` con cache via tabella + fallback last-known + fallback rate=1
- [x] Adapter `Fx/FrankfurterClient` (timeout 5s, retry 1, fallback graceful, weekend gestito da API)
- [~] ~~Comando `php artisan currencies:backfill`~~ → **sostituito per scelta architetturale**: backfill inline nelle migration (decisione concordata in Q&A iniziale: opzione "inline_eur_1")
- [x] Test Unit `CurrencyConverterTest` + `FrankfurterClientTest` (Http::fake) — 15 verdi

## Fase 2 — Bot Telegram multi-currency

- [x] Estendere `parseTextMessage` con regex ISO code + simboli (£, $, €, ¥) + override `~rate`
- [x] Default valuta = `user.default_currency_code` con fallback EUR (`resolveCurrencyForUser`)
- [x] Aggiornare creazione `InboxItem` con `currency_code`/`exchange_rate_to_base`/`amount_base` + snapshot via `CurrencyConverter`
- [x] Aggiornare messaggi conferma bot per mostrare valuta originale + equivalente EUR (helper `formatAmount`)
- [x] Aggiornare `InboxController::confirm` e `confirmAll` per propagare valuta (no più hardcoded EUR) via `buildTransactionPayload`
- [x] Aggiornare `/aiuto` con sezione "💱 Valuta diversa da euro" e nuovi esempi
- [x] Test Feature: parsing ISO, simboli, override rate, conferma Inbox cross-currency — verdi

## Fase 3 — UI web multi-currency

- [x] Toggle "Valuta diversa dal conto" in `Transactions/Create.tsx` e `Edit.tsx` con campi `original_amount`/`original_currency_code`/`manual_rate`
- [x] `StoreTransactionRequest` / `UpdateTransactionRequest`: accettano i nuovi campi opzionali
- [x] `TransactionController::store/update`: usano `CurrencyConverter::convertToAccountCurrency` per popolare `original_*` + `exchange_rate_to_base` + `amount_base`
- [x] `TransactionController::create/edit`: passano `currencies` e `userDefaultCurrency` al frontend
- [x] Settings profilo: select default currency in `UpdateProfileInformationForm.tsx` + persistenza via `ProfileUpdateRequest`
- [x] Endpoint AJAX `transactions.fx-preview` (`GET /transazioni/anteprima-cambio`) + hook React `useFxPreview` con debounce + integrazione hint live nei form Create/Edit
- [x] Inbox UI (`Inbox/Index.tsx`): componente `<ItemAmount>` che mostra importo nella valuta nativa, equivalente EUR sotto se diversa, e — se presente — l'`original_amount` digitato dall'utente
- [ ] **TODO follow-up**: Dashboard/aggregati cross-conto in valute miste devono usare `amount_base` invece di `amount`. Oggi non rompe perché tutte le transazioni di un conto sono nella valuta del conto e `Account::balance` resta in valuta nativa (saldo coerente con estratto banca). Diventa rilevante solo quando l'utente avrà conti in valute diverse + dashboard globali. **Bloccante implementarlo SUBITO**: scelta esplicita dell'utente del 2026-05-08 di lasciarlo come follow-up.
- [x] Test Feature `TransactionMultiCurrencyTest` (8 verdi): store con manual rate, store retro-compatibile, update con campi originali, profile update default currency, fx-preview identity, fx-preview cache miss, fx-preview validation, fx-preview auth required

## E2E

- [x] `e2e/transactions/transactions.spec.ts`: toggle "valuta diversa dal conto" + campi correlati
- [x] `e2e/transactions/transactions.spec.ts`: anteprima del cambio dopo selezione `original_currency_code`
- [x] `e2e/transactions/transactions.spec.ts`: presenza del conto "Revolut GBP" creato dal seeder E2E
- [x] `e2e/profile/profile.spec.ts`: selezione e persistenza di `default_currency_code`
- [x] `E2ESeeder`: nuovo `Account` "Revolut GBP E2E" in valuta GBP, agganciato alla household principale, per testare flussi multi-currency end-to-end

## Risultati

- `make test-ci` (PHPUnit + Pint): **598 test passed (2024 assertions)** — Pint OK (+4 vs precedente: 4 test fxPreview)
- `make playwright`: **186 test passed (12 skipped, 0 falliti)** (+4 vs precedente: 2 anteprima cambio + 2 conto GBP, su desktop+mobile)
- `make build`: asset compilati senza errori
- Migrazioni con backfill inline applicate: `exchange_rates` (nuova), `transactions` (4 colonne nuove + index), `inbox_items` (5 colonne nuove + 2 FK), `users.default_currency_code`

## Risposta ai 3 problemi dell'utente

1. **"Tracciare in valuta locale via Telegram in modo rapido"** → ora la sintassi `30 GBP cena pub`, `£30 cena pub`, `$50 hotel` viene riconosciuta dal parser. Il bot risponde con l'equivalente EUR (es. `≈ €35,29`) e la transazione finale viene creata nella valuta del conto al momento della conferma in Inbox.
2. **"Inserire spese sostenute in £/$ dal web"** → toggle "💱 Ho pagato in valuta diversa dal conto" nel form Crea/Modifica transazione: tre campi (importo originale, valuta, cambio manuale opzionale). I valori vengono memorizzati in `transactions.original_amount` / `original_currency_code` per audit.
3. **"Cambio fisso vs istantaneo"** → due strategie complementari:
   - **istantaneo**: nessun rate manuale → `CurrencyConverter` chiama Frankfurter (BCE-based, gratis) e popola lo snapshot. Cache giornaliera in `exchange_rates`. Weekend gestito automaticamente da Frankfurter (rate del venerdì).
   - **fisso storico** (sterline cambiate tempo fa): bot `~1.18` o campo "Cambio manuale" nel form. Il rate viene salvato come snapshot e non viene mai più ricalcolato.

## Note di follow-up (deliberatamente fuori scope di questo PR)

- ~~UI Inbox che renda `original_amount`/`original_currency_code`~~ → **CHIUSO** in questo round: aggiunto componente `<ItemAmount>` con valuta nativa + equivalente EUR + `orig.` se presente.
- ~~Endpoint AJAX "anteprima rate" nel form Transactions~~ → **CHIUSO** in questo round: route `transactions.fx-preview` + hook `useFxPreview` con debounce + integrazione Create/Edit.
- ~~`E2ESeeder` con secondo account in valuta diversa~~ → **CHIUSO** in questo round: aggiunto "Revolut GBP E2E" nella household principale.
- **APERTO**: dashboard cross-conto multi-valuta che sommi `amount_base` invece di `amount`. Decisione esplicita dell'utente del 2026-05-08 di rinviare: oggi non rompe nulla perché tutte le transazioni di un conto restano nella valuta del conto e `Account::balance` resta in valuta nativa, quindi i saldi sono allineati agli estratti bancari. Diventa rilevante solo quando l'utente avrà conti in valute diverse + widget di dashboard che aggregano cross-conto.

---

# Ricorrenze, offline PWA e duplicati — 2026-05-30

## Piano

- [x] Aggiungere configurazione ricorrenze per giorno fisso/ultimo giorno e policy festivi
- [x] Aggiornare generazione, prossima scadenza, riconciliazione e fork ricorrenze
- [x] Esporre i nuovi campi nei form e nei dettagli ricorrenze
- [x] Estrarre rilevamento duplicati in service riusabile e aggiungere CTA web
- [x] Aggiornare offline gate PWA con sfondo viola e copy leggibile
- [x] Aggiungere test Feature/Unit mirati
- [ ] Verificare con `make test`, `make pint-check`, `make playwright`

## Review

- In corso.
