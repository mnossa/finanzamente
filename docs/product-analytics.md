# Product analytics (first-party, privacy-first)

## Ruoli tool (importante)

| Tool | A cosa serve | Quando usarlo |
|------|----------------|---------------|
| **Laravel Pulse** (`/pulse`) | Debug runtime: eccezioni, richieste lente, job, cache, queue | Prima scelta per capire un 500 / bottleneck |
| **Telescope** (`/telescope`) | Request/query/job detail | Solo `local`/`staging` + `TELESCOPE_ENABLED=true` |
| **Product analytics** (`/admin/product-analytics`) | Trend prodotto aggregati (usage/friction/error) | Priorità backlog, non stacktrace |
| **Sentry / Flare** | Error tracking SaaS con stack + release | Opzionale, solo con DPA e valutazione privacy |

La dashboard custom **non** sostituisce Pulse/Flare: aggrega conteggi privacy-safe. I 500 appaiono come `exception.server` (classe + route, **senza** messaggio/PII).

## Cosa tracciamo (first-party)
- Eventi prodotto aggregati per giorno: `used`, `friction`, `error`, `performance`
- Dimensioni solo enum/bool/contatori corti (max 8 chiavi)
- Richieste lente: solo **nome rotta** + bucket ms + status class
- Eccezioni server: `exception` (basename classe) + `route` + `status`

## Cosa NON tracciamo
- email, nome, IP, `user_id`, `household_id`
- importi, descrizioni, note, IBAN, token, messaggi exception grezzi
- session replay / heatmaps
- payload request/response

## Flusso
1. Client (`analytics.ts`) → Umami + POST `/product-analytics/events` se consenso
2. `ProductAnalyticsRecorder` sanitizza e upsert su `product_analytics_daily`
3. `ProductAnalyticsExceptionRecorder` su `reportable` eccezioni
4. Dashboard admin + link a Pulse
5. Retention: `product-analytics:enforce-retention` (default 90 giorni)

## Pulse
- Storage: SQLite dedicato (`storage/pulse/pulse.sqlite`, connection `pulse`) — evita bug MySQL generated `md5`; in prod sul volume `storage`
- Schema: creato anche se `PULSE_ENABLED=false` (l’flag spegne solo il recording)
- Accesso: Gate `viewPulse` = email `MAGAZINE_ADMIN_EMAIL`
- Env: `PULSE_ENABLED`, `PULSE_DB_CONNECTION=pulse`

## Config
- `config/product_analytics.php`, `config/pulse.php`
- Env: `PRODUCT_ANALYTICS_*`, `TELESCOPE_ENABLED`, `PULSE_*`
