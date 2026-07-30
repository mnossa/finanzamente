## Audit sicurezza & robustezza — WFI-102

### 1. Panoramica generale

- **Contesto**: web app Laravel + Inertia/React per finanza personale, multi‑household, piano Base/Pro, bot Telegram, integrazione pagamenti (Mollie).
- **Obiettivo audit**: individuare vulnerabilità e punti critici di sicurezza/robustezza e proporre mitigazioni pratiche.
- **Aree analizzate**:
  - Autenticazione, 2FA, sessioni.
  - Autorizzazioni e multi‑tenant (household).
  - Validazione input e protezione contro abusi.
  - Upload file e storage (allegati, Inbox).
  - Webhook e integrazioni esterne (Telegram, Mollie, servizi terzi).
  - Dati sensibili e logging.
  - Configurazione e infrastruttura.

Valutazione generale: **buon livello di sicurezza di base**, con diverse misure difensive già presenti; restano margini di hardening soprattutto su allegati investment, cifratura sessioni e consolidamento delle policy di autorizzazione.

---

### 2. Autenticazione, 2FA e sessioni

**Analisi**

- `config/auth.php`
  - Guard predefinito `web` con driver `session` e provider `eloquent` (`User`).
  - Reset password configurato con expiry 60 minuti e throttle 60s → mitigazione attacchi reset massivi.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
  - Login:
    - Usa `LoginRequest::validateCredentials()` per validazione credenziali (incl. controlli di base).
    - Usa `Auth::login($user, remember)` e **`$request->session()->regenerate()` dopo il login** → protezione contro session fixation.
  - Logout:
    - `Auth::guard('web')->logout()`, `session()->invalidate()`, `session()->regenerateToken()` → buona pratica.
- `app/Http/Controllers/Auth/TwoFactorChallengeController.php`
  - 2FA opzionale, con:
    - verifica che la sessione contenga `login.id` (forced flow dopo login).
    - 2FA code/recovery code validati tramite `TwoFactorAuthenticationService` (non analizzato nei dettagli ma correttamente incapsulato).
    - `Auth::login($user, $remember)` + `session()->regenerate()` dopo challenge superata.
- `config/session.php`
  - Driver default: `database` (più robusto di `file` per ambienti multi‑instance).
  - Cookie:
    - `http_only` abilitato.
    - `same_site = 'lax'` per mitigare CSRF.
    - `secure` delegato a `SESSION_SECURE_COOKIE`.
  - Di default, `encrypt` è `false` (possibile hardening).

**Valutazione parziale**

- **Punteggio**: **8/10** (buon livello).
- **Punti di forza**:
  - Session regeneration sistematico dopo login / 2FA.
  - Login e reset password soggetti a rate limit avanzato (`adv-throttle`) e, per registrazione, anche a honeypot.
  - 2FA integrata in modo ordinato nel flusso di login.

**Raccomandazioni**

- **R1 — Abilitare cifratura sessione in produzione (priorità media)**  
  Se il carico lo consente, valutare `SESSION_ENCRYPT=true` in produzione per rendere il contenuto della sessione illeggibile in caso di leak del DB sessioni.  
  *Impatto*: CPU leggermente maggiore ma maggiore protezione dei dati di contesto.

- **R2 — Blindare feature flag e2e (priorità bassa)**  
  Verificare che `E2E_TWO_FACTOR_ENABLED` e simili siano sempre `false` in ambienti di produzione (eventuale assert in boot se `app()->environment('production')` && `E2E_TWO_FACTOR_ENABLED=true`).

---

### 3. Autorizzazioni e multi‑tenant (household)

**Analisi**

- Middleware chiave:
  - `EnsureHasActiveHousehold`: obbliga ogni utente autenticato ad avere una household attiva, altrimenti redirect a creazione/selezione.
  - `RequiresPro`: blocca rotte Pro se `!$user->isPro()` (usa `User::isPro()` con controllo `plan_expires_at`).
  - Middleware di autorizzazione household / can-modify (gating permessi).
- Model `User`:
  - `isPro()` tiene conto sia di `plan` sia di `plan_expires_at`.
  - Metodi come `excessAccountsCount()` e `excessHouseholdsCount()` rispettano i limiti del piano configurati.
- Diversi controller usano controlli espliciti su household e privacy, ad esempio:
  - `TransactionController::authorizeTransaction()` e `AttachmentController::authorizeTransaction()`:
    - verifica che la transazione appartenga alla household attiva,
    - se `is_private`, l’utente deve essere il creatore.

**Valutazione parziale**

- **Punteggio**: **7.5/10**.
- **Punti di forza**:
  - Multi‑tenancy basata su `household_id` coerente in molti punti chiave.
  - Limitazioni funzionalità Pro ben centralizzate (`RequiresPro`, `User::isPro()`).

**Raccomandazioni**

- **R3 — Consolidare le autorizzazioni in Policy Laravel (priorità media‑bassa)**  
  Alcuni controlli sono in metodi privati di controller. Centralizzare la logica di autorizzazione per `Transaction`, `Account`, `Investment`, `InboxItem` in Policy Laravel:
  - riduce duplicazioni,
  - rende più facile un audit futuro,
  - diminuisce il rischio di dimenticare un controllo in nuovi endpoint.

---

### 4. Validazione input & protezione contro abusi

**Analisi**

- Uso diffuso di **FormRequest**:
  - `StoreTransactionRequest`, `UpdateTransactionRequest`, `StoreAccountRequest`, `UpdateAccountRequest` ecc.
  - Uso di `Rule::in` con liste di ID “accessibili” alla household attiva per account e tag → buona segmentazione.
- Route `routes/auth.php`:
  - Login, registrazione, reset password, 2FA sono tutti protetti da `adv-throttle` (rate limit avanzato) e, per la registrazione, anche `ProtectAgainstSpam` (honeypot).
- Alcuni endpoint usano `Request` diretto ma con `validate()` inline (es. `AttachmentController::store`).

**Valutazione parziale**

- **Punteggio**: **7.5/10**.
- **Punti di forza**:
  - Regole di validazione dettagliate per importi, date, ID foreign key, ecc.
  - Rate limiting coerente sulle aree sensibili (auth, password, 2FA).

**Raccomandazioni**

- **R4 — Promuovere ulteriori endpoint critici a FormRequest (priorità media‑bassa)**  
  Per endpoint mutanti ad alta criticità (es. modifiche massive, API AJAX che scrivono dati), valutare di spostare la validazione da `Request::validate(...)` a FormRequest dedicati, per:
  - coerenza,
  - riuso,
  - migliore testabilità.

---

### 5. Upload file & storage

**Analisi**

- `config/filesystems.php`:
  - Disk `private` e `local` puntano a `storage/app/private`, `public` a `storage/app/public`.
  - Link simbolico `public/storage → storage/app/public`.
- `AttachmentController`:
  - `store`:
    - valida:
      - `attachable_type`: `Transaction|Investment`,
      - `attachable_id`: integer,
      - `file`: `mimes:jpg,jpeg,png,pdf,doc,docx|max:5120` (5 MB max).
    - costruisce `attachableType = 'App\\Models\\'.$attachable_type` (limitato dalla validation).
    - per `Transaction`: autorizzazione tramite `authorizeTransaction()` (controllo household + privacy).
    - salvataggio su disk `private` con filename univoco; nessun link pubblico.
  - `download`:
    - usa `attachment->attachable` per verificare autorizzazione (solo per Transaction al momento).
    - scarica dal disk `private` con nome originale.
  - `destroy`:
    - riusa stessa logica di autorizzazione e cancella file + record.

**Punti critici individuati**

- Per allegati collegati a `Investment`:
  - non c’è un controllo di autorizzazione parallelo a quello per `Transaction` (household + privacy).
  - L’accesso è mediato da route protette, ma manca un guard centrale a livello di `AttachmentController`.

**Valutazione parziale**

- **Punteggio**: **7/10**.

**Raccomandazioni**

- **R5 — Estendere l’autorizzazione allegati a tutti i tipi di attachable (priorità alta)**  
  Introdurre un metodo generico, ad esempio:
  ```php
  private function authorizeAttachable(Model $attachable): void
  ```
  che:
  - per `Transaction` esegue la logica attuale,
  - per `Investment` verifica `household_id`, `user_id` e `is_private` (se previsto),
  - eventualmente per altri modelli futuri.
  Usare questo metodo in `download` e `destroy` indipendentemente dal tipo concreto.

- **R6 — Validazione MIME lato server (priorità bassa)**  
  In aggiunta alle estensioni e alla dimensione già validate, si può:
  - usare `finfo`/`mime_content_type` per verificare il tipo reale del file,
  - rifiutare mismatch evidenti (es. file eseguibili camuffati da `.pdf`).

---

### 6. Webhook & integrazioni esterne

#### 6.1 Telegram (`TelegramWebhookController`)

**Analisi**

- Verifica del secret:
  - se `services.telegram.webhook_secret` è settato, viene confrontato con l’header `X-Telegram-Bot-Api-Secret-Token` via `hash_equals` → difesa efficace contro chiamate non autorizzate.
- Idempotenza:
  - uso di `Cache::add("telegram_webhook:update:$updateId")` per ignorare update duplicati.
- Flussi:
  - comandi (`/saldo`, `/ultime`, `/lista`, `/casa`, `/debiti`, ecc.) e ingest transazioni via Inbox.

**Rischi / raccomandazioni**

- **R7 — Continuare a mantenere il webhook “stupido” (priorità bassa)**  
  L’handler risponde sempre `200 OK` e logga eventuali problemi senza esporre stack trace all’utente Telegram: mantenere questa scelta per non rivelare dettagli di implementazione in canale pubblico.

#### 6.2 Mollie (`MollieWebhookController`)

**Analisi**

- Verifica del secret:
  - header `X-Mollie-Webhook-Secret` confrontato con `services.mollie.webhook_secret` tramite `hash_equals`.
- Idempotenza:
  - `Cache::add('mollie_webhook:id:'.$mollieId)` evita riprocessamenti in caso di retry.
- Gestione errori:
  - avvolta in `try/catch` con `report($e)` + `Log::error`, ma sempre risposta `200` → best practice consigliata da Mollie.

**Rischi / raccomandazioni**

- **R8 — Limitare logging a dati non sensibili (priorità bassa)**  
  Già oggi si loggano solo ID tecnici (`subscription_id`, `payment_id`, `mandate_id`). Evitare di loggare interi payload webhook, per non inserire in log eventuali dati personali o di pagamento.

---

### 7. Dati sensibili & logging

**Analisi**

- `App\Models\User`:
  - campi nascosti: `password`, token di remember, secret 2FA e recovery codes.
  - cast:
    - `two_factor_secret` → `encrypted`,
    - `two_factor_recovery_codes` → `encrypted:array`.
- In generale:
  - non risultano log evidenti di password o codici 2FA.

**Valutazione parziale**

- **Punteggio**: **8/10**.

**Raccomandazioni**

- **R9 — Evitare logging di oggetti User completi (priorità bassa)**  
  In caso di errori, loggare solo ID utente o email, non strutture complete serializzate che potrebbero includere campi sensibili.

---

### 8. Configurazione & infrastruttura

**Analisi**

- Filesystem:
  - allegati e contenuti sensibili collocati su disk `private` non esposto via `storage:link`.
- `config/services.php`:
  - tutte le chiavi e token sono letti da env; nessun secret hard‑coded.
  - servizi opzionali controllati da flag (`BREVO_ENABLED`, `TELEGRAM_BOT_TOKEN`, ecc.).

**Raccomandazioni**

- **R10 — Verifica sistematica delle variabili d’ambiente in produzione (priorità bassa)**  
  Assicurarsi che:
  - `SESSION_SECURE_COOKIE=true` in produzione dietro HTTPS,
  - `SESSION_SAME_SITE` resti almeno `lax` (o `strict` se l’UX lo consente),
  - servizi non usati in prod (es. Mistral, Unsplash, Google Drive) siano disabilitati per ridurre possibili superfici di attacco/config error.

---

### 9. Valutazioni parziali (riassunto)

- **Auth & 2FA**: **8/10**  
  Sessioni rigenerate, 2FA integrata, rate limit avanzato.
- **Autorizzazione & multi‑tenant**: **7.5/10**  
  Household e piani Pro gestiti correttamente; possibile ulteriore centralizzazione in Policy.
- **Validazione & anti‑abuso**: **7.5/10**  
  FormRequest diffusi e `adv-throttle` sulle rotte sensibili.
- **File upload & storage**: **7/10**  
  Buone basi (disk privato, whitelist estensioni); da estendere ai casi Investment per autorizzazione.
- **Webhook & integrazioni**: **8/10**  
  Secret header + idempotenza + gestione errori corretta.
- **Dati sensibili & logging**: **8/10**  
  Password e segreti cifrati/nascosti; attenzione a non loggare PII superflue.

---

### 10. Valutazione complessiva & priorità di intervento

**Valutazione complessiva sicurezza/robustezza: _Buona (≈7.8/10)_**

Non emergono vulnerabilità critiche immediate; l’impianto è solido e già orientato alla sicurezza. Le priorità concrete per migliorare ulteriormente:

1. **R5 – Autorizzazione allegati Investment (alta)**  
   - Estendere i controlli di accesso degli allegati a tutti i tipi di `attachable`, partendo da `Investment`.
2. **R1 – Cifratura sessioni in produzione (media)**  
   - Abilitare `SESSION_ENCRYPT` se sostenibile, per proteggere il contenuto delle sessioni.
3. **R3 – Policy Laravel centralizzate (media‑bassa)**  
   - Consolidare la logica di autorizzazione in Policy per `Transaction`, `Account`, `Investment`, `InboxItem`.
4. **R8/R9/R10 – Hardening fine (bassa)**  
   - Pulizia logging, configurazione stretta dei cookie di sessione, disabilitazione servizi non usati.

Questi interventi, applicati progressivamente, alzerebbero ulteriormente il livello di sicurezza percepita e reale del progetto, riducendo al minimo la superficie di attacco e migliorando la manutenibilità delle regole di autorizzazione nel tempo.

