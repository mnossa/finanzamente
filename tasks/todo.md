# Analytics mobile, Pulse anon, cedole date, dividendi Acc/Dist

## Goal
1. Product analytics mobile-friendly + dettaglio errori (dimensioni aggregate)
2. Pulse: anonimizza nome/email in UI
3. Cedole BTP: date di cambio tasso (cedola crescente)
4. Azioni/obbligazioni/ETF: dividendi + accumulo/distribuzione

## Plan
- [x] Product analytics: mobile cards + error detail (dimensions)
- [x] Pulse::user() anonymization + test
- [x] coupon_rate_steps → {from, rate} date-keyed + UI + preview
- [x] income_policy (accumulating|distributing) on asset + UI
- [x] make test + pint-check
- [ ] make playwright (bloccato: stack E2E MySQL/network dopo restart dockerd)
- [x] Commit / push / PR

## Review
### Cosa
- **Product analytics**: card layout mobile; click Errori → breakdown exception/route/status
- **Pulse**: `Utente #{id}`, extra/avatar vuoti (no email/Gravatar)
- **Cedole BTP**: step `{from, rate}`; preview per data; legacy lista float ancora ok
- **Acc/Dist**: `income_policy` su ETF/stock/bond (create/edit + Show)

### Verifica
- PHPUnit: 1115 passed (+ focus 20/20 su analytics/pulse/cedole)
- Pint: PASS
- Playwright: non eseguito — MySQL E2E `Unable to lock ibdata1` / container networking dopo restart dockerd in Cloud VM
