# Product analytics (first-party, privacy-first)

## Cosa tracciamo
- Eventi prodotto aggregati per giorno: `used`, `friction`, `error`, `performance`
- Dimensioni solo enum/bool/contatori corti (max 8 chiavi)
- Richieste lente: solo **nome rotta** + bucket ms + status class

## Cosa NON tracciamo
- email, nome, IP, `user_id`, `household_id`
- importi, descrizioni, note, IBAN, token
- session replay / heatmaps
- payload request/response

## Flusso
1. Client (`resources/js/utils/analytics.ts`) → Umami (se attivo) + POST `/product-analytics/events` se consenso `analytics_tracking`
2. `ProductAnalyticsRecorder` sanitizza e upsert su `product_analytics_daily`
3. Dashboard admin: `/admin/product-analytics` (middleware `owner`)
4. Retention: `product-analytics:enforce-retention` (default 90 giorni)

## Debug (non-prod)
- Laravel Telescope: solo `local`/`staging` e `TELESCOPE_ENABLED=true`
- Mai esporre Telescope su produzione pubblica

## Config
- `config/product_analytics.php`
- Env: `PRODUCT_ANALYTICS_ENABLED`, `PRODUCT_ANALYTICS_SLOW_MS`, `PRODUCT_ANALYTICS_SLOW_ENABLED`, `TELESCOPE_ENABLED`
