# Audit tecnico GDPR — consensi granulari

Data: 2026-04-28

## Obiettivo

Definire implementazione tecnica pronta per:

- consenso granulare per finalita distinte
- tracciamento eventi consenso/revoca (audit trail)
- retention minima documentata e applicabile via job

## Stato attuale (codebase)

- Pagine legali presenti (`/privacy`, `/cookie`, `/termini`).
- Non presente modello dati dedicato ai consensi granulari.
- Non presente event log esplicito per revoche/versioning policy.
- Non presente policy retention centralizzata su tabella + job dedicato.

## Decisione di scope

Scope richiesto ora: **minimo compliance-ready** (non roadmap).

Include:

1. storage consensi per utente + finalita
2. storico eventi append-only
3. policy retention versionata
4. job schedulato per enforcement retention

## Schema DB proposto (pronto migrazione)

### 1) `consents`

Scopo: stato corrente per finalita.

Campi:

- `id` (bigint)
- `user_id` (fk users, index)
- `purpose` (string, index)  
  Valori iniziali consigliati:
  - `privacy_policy_ack`
  - `terms_ack`
  - `marketing_email`
  - `analytics_tracking`
- `status` (enum: `granted`, `revoked`, `pending`)
- `source` (string) es. `web_register`, `profile_settings`, `admin_import`
- `legal_basis` (string) es. `consent`, `contract`, `legal_obligation`
- `policy_version` (string) es. `privacy-v1.3`
- `granted_at` (timestamp nullable)
- `revoked_at` (timestamp nullable)
- `expires_at` (timestamp nullable)
- `metadata` (json nullable)
- timestamps

Vincoli:

- unique composite: (`user_id`, `purpose`)

### 2) `consent_events`

Scopo: storico append-only, forense/audit.

Campi:

- `id` (bigint)
- `consent_id` (fk consents, index)
- `user_id` (fk users, index)
- `event_type` (enum: `granted`, `revoked`, `updated`, `expired`, `imported`)
- `old_status` (string nullable)
- `new_status` (string nullable)
- `source` (string)
- `ip_hash` (char(64) nullable) — SHA256 con salt `ADV_THROTTLE_SALT`
- `user_agent_hash` (char(64) nullable)
- `policy_version` (string nullable)
- `occurred_at` (timestamp, index)
- `metadata` (json nullable)
- created_at

Note:

- tabella append-only: no update/delete applicativo, solo insert.

### 3) `retention_policies`

Scopo: policy versionata e tracciabile.

Campi:

- `id` (bigint)
- `policy_key` (string unique) es. `consent_events_default`
- `description` (string)
- `retention_days` (unsigned integer)
- `anonymize_after_days` (unsigned integer nullable)
- `is_active` (boolean default true)
- `version` (string) es. `2026-04-28-v1`
- `metadata` (json nullable)
- timestamps

## Regole applicative suggerite

1. `ConsentService::setConsent(user, purpose, status, context)`
   - upsert su `consents`
   - insert sempre su `consent_events`
2. revoca:
   - `status=revoked`, `revoked_at=now`
3. grant:
   - `status=granted`, `granted_at=now`, reset `revoked_at`
4. policy version:
   - obbligatoria su grant/revoke per tracciabilita legale
5. logging privacy-safe:
   - mai IP/user-agent in chiaro, solo hash con salt

## Retention minima proposta

- `consent_events`: 3650 giorni (10 anni) default compliance-safe
- `consents` correnti: finche account attivo + 365 giorni dopo delete richiesta
- backup logici retention: allineati a `retention_policies`

Enforcement:

- comando Artisan giornaliero: `consents:enforce-retention`
- scheduler in `routes/console.php`

## Piano implementazione (incrementale)

1. Migrazioni: `consents`, `consent_events`, `retention_policies`
2. Model Eloquent + enum PHP (`ConsentStatus`, `ConsentEventType`)
3. `ConsentService` con metodo unico di update/audit
4. Feature tests:
   - grant crea stato + evento
   - revoke aggiorna stato + evento
   - upsert stessa purpose non duplica `consents`
5. Job/command retention + test unit
6. UI minima profilo:
   - toggle consensi opzionali
   - timestamp ultimo update

## Stato implementazione (2026-04-28)

Completato nel codebase:

- migrazioni: `consents`, `consent_events`, `retention_policies`
- modelli: `Consent`, `ConsentEvent`, `RetentionPolicy`
- servizio: `ConsentService::setConsent(...)` con event append-only
- retention command: `consents:enforce-retention`
- schedule giornaliero in `routes/console.php`
- test automatici:
  - `tests/Feature/ConsentLifecycleTest.php`
  - `tests/Feature/ConsentAuditTrailTest.php`
  - `tests/Unit/ConsentRetentionTest.php`

Ancora da fare:

- eventuale schermata dedicata di storico consensi/eventi (oltre export JSON già disponibile)
- eventuale API esterna dedicata per portabilità/revoca (attualmente coperto da rotte web autenticate)

## Test plan minimo (obbligatorio)

- `tests/Feature/ConsentLifecycleTest.php`
  - grant/revoke/update flow
  - policy version persistita
- `tests/Feature/ConsentAuditTrailTest.php`
  - evento append-only per ogni cambio stato
  - hash IP/UA presenti, raw assenti
- `tests/Unit/ConsentRetentionTest.php`
  - pruning/anonymization secondo `retention_policies`
