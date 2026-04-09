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
