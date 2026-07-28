# WFI-116 — Cedole e dividendi collegati a investimento

## Goal
Associare stacchi cedola/dividendo a posizione investimento (BTP ecc.), totale ritorno, calendario manuale per ISIN (niente API a pagamento).

## Plan
- [x] Migration `investment_event` + schedule fields + backfill
- [x] `InvestmentCouponService` + metrics total return
- [x] Sync discriminato per event (coupon non sovrascritto da sale)
- [x] Routes/controller store/destroy/schedule + Show props
- [x] Show.tsx UI cedole + calendario
- [x] Feature tests `InvestmentCouponTest`
- [x] `make test` → `make pint-check` → `make playwright` (TS/UI)
- [x] Jira WFI-116 → Completato

## Review
### Cosa
- `transactions.investment_event`: purchase | sale | coupon
- Cedola = entrata categoria «Cedole e dividendi», nel cashflow operativo
- Show: lista, registra, elimina, KPI totale cedole + ritorno complessivo
- Calendario manuale su asset (frequenza / prossima data / tasso %); preview prossime date
- Nessun feed ISIN free universale → niente calendario auto

### Verifica
- PHPUnit: 1105 passed
- Pint: pass
- Playwright: green (exit 0)
- Migrate + build OK
