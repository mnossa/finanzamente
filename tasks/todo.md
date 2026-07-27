# WFI-115 — Product analytics privacy-first

## Goal
Dashboard ops per usage/friction/bottlenecks; zero PII; Telescope solo non-prod; retention+purge.

## Plan
- [x] Migration `product_analytics_daily` + retention policy seed
- [x] `ProductAnalyticsRecorder` (sanitize + upsert aggregati)
- [x] Ingest endpoint (consent `analytics_tracking`) + dual-write da `analytics.ts`
- [x] Admin Inertia dashboard (`owner` middleware) + audit access
- [x] Retention command + schedule
- [x] Telescope require-dev gated (`!production` + `TELESCOPE_ENABLED`)
- [x] Privacy policy bump + first-party in tabella servizi
- [x] Tests Feature/Unit
- [x] `make test` → `make pint-check` → `make playwright`
- [x] Aggiorna Jira WFI-115 → Completato

## Review
### Cosa
- First-party aggregates `product_analytics_daily` + recorder sanitizer
- Ingest + admin dashboard + slow-route middleware
- Telescope require-dev, local/staging only
- Privacy `2026-07-27-v1`, docs/product-analytics.md

### Verifica
- PHPUnit: 1095 passed
- Pint: pass
- Playwright: 277 passed / 9 skipped
