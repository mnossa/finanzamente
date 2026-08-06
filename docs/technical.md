# Documentazione tecnica — Finanzamente

Guida per chi clona, modifica o self-hosta il progetto. UI utente in **italiano**; codice, DB e API names in **inglese**.

## Architettura

```
Browser
  ├─ pagine pubbliche (Blade + Tailwind)  → Welcome, legal placeholder, auth guest
  └─ area autenticata (Inertia + React) → dashboard, conti, movimenti, …

Nginx → PHP-FPM (Laravel)
  ├─ MySQL
  ├─ Redis (cache, queue, session)
  └─ opzionale: python-services (cohort insights)
```

- Autenticazione: sessione web (niente REST API pubblica di default).
- Autorizzazione: policies + middleware household.
- Business logic: layer `app/Services`.
- Household: modalità `isDebtBalancingMode()` / `isSharedWalletMode()` su `App\Models\Household`.

Non esistono piani Base/Pro né billing: tutte le funzionalità del prodotto sono disponibili dopo registrazione e profilo.

## Requisiti

- Docker + Docker Compose
- Make
- Porte libere tipiche: `8080` (app), `5174` (Vite HMR in `make dev`), `8081` (E2E)

Comandi di sviluppo: **solo** `make *` (UID/GID host → container).

## Ambiente (`.env`)

Copia `.env.example` → `.env`. Ogni variabile è commentata lì.

**Minimo per avviare:**

| Chiave | Ruolo |
|--------|--------|
| `APP_KEY` | generata da `make setup` / `php artisan key:generate` |
| `ADV_THROTTLE_SALT` | **obbligatorio**: stringa lunga casuale (non lasciare il placeholder) |
| `APP_URL` | URL pubblico (default Docker: `http://localhost:8080`) |
| `DB_*` | MySQL (default compose: host `db`) |
| Redis / mail | già impostati per stack Docker + Mailpit |

**Opzionali:** Telegram, Google Drive/Sheets, Pulse/Telescope, passkeys, python cohort insights.

Dopo cambi env rilevanti: `make clear-cache` (o rebuild config E2E con `make e2e-seed`).

### Primo avvio verificato

Flusso collaudato su clone/stack vuoto (volumi MySQL ricreati):

```bash
cp .env.example .env          # poi ADV_THROTTLE_SALT
make up
make setup                    # deps + key + migrate + frontend
# smoke: http://localhost:8080/up → 200, / → homepage OSS
make demo-data                # opzionale
```

Schema pulito: nessuna colonna `plan` / tabella `subscriptions`.  
Login demo dopo `make demo-data`: `mario.rossi@example.com` / `password`.

### Make (essenziali)

| Target | Cosa fa |
|--------|---------|
| `make up` / `down` | Avvia / ferma lo stack |
| `make setup` | Post-`up`: composer, APP_KEY, storage:link, migrate, npm, build |
| `make dev` | Vite HMR nel container `node` |
| `make build` | Build frontend produzione |
| `make migrate` / `fresh` / `seed` | Schema DB |
| `make test` | PHPUnit |
| `make pint-check` / `pint-fix` | Stile PHP |
| `make playwright` | E2E |
| `make demo-data` | Utenti/demo |
| `make export-google-sheets spreadsheet=ID` | Export Sheets (local) |

Dettaglio: `docs/agent/makefile.md`.

**Nota:** `docker compose exec app php artisan tinker` può richiedere `HOME=/tmp` (utente non-root nel container):

```bash
docker compose exec -e HOME=/tmp app php artisan tinker
```

## Integrazioni

### Telegram

1. Crea bot con `@BotFather` → `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`.
2. Webhook: `POST {APP_URL}/telegram/webhook`.
3. Opzionale: `TELEGRAM_WEBHOOK_SECRET`.

### Google Drive / Sheets

Vedi commenti in `.env.example` (OAuth + API key per Picker; service account per export Sheets).

Export household su foglio esistente (solo `local`/`development`):

```bash
make export-google-sheets spreadsheet=SPREADSHEET_ID
# opzionali: user=1 household=2
```

Il foglio deve essere condiviso in **Editor** con l’email del service account (`GOOGLE_SHEETS_CREDENTIALS_PATH`).

## Contribuire

1. Branch feature, PR verso `main`.
2. Prima di merge: `make test`, `make pint-check`; se tocchi UI navigabile anche `make playwright`.
3. Nuove feature → almeno un test Feature o Unit.
4. CI GitHub: solo lint + PHPUnit (+ typecheck frontend se presente in workflow). Nessun deploy automatico.

Vedi anche `docs/contributor-guide.md`.

## Self-host / produzione

Questo repository **non** include pipeline di deploy né immagini Docker Hub ufficiali.  
Per produzione: costruisci/esegui tu lo stack (parti da `Dockerfile` + `docker-compose.yml` di sviluppo e adattali), gestisci backup, TLS e segreti.

Pagine `/privacy`, `/cookie`, `/termini` vanno valorizzate; bump `config/legal.php` → `privacy_policy_version` a ogni cambio sostanziale privacy.

## Licenza

MIT — `LICENSE`, Copyright (c) 2026 Matteo Nossa.
