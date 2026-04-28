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
