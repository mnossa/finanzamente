# Remove P.IVA + operational stats filters

## Goal
1. Rimuovere termometro tasse e riferimenti prodotto Partita IVA
2. Applicare filtri operativi (no transfer, no investment, IH-safe) a budget/notifiche/tag/lifestyle/analisi

## Plan
- [x] Kill `getTaxThermometerData` + prop + TaxThermometerVisibilityTest + TaxCalculatorLogicTest + docs
- [x] Strip Lifestyle P.IVA tax path (FinancialMetricsService, FAQ, export)
- [x] Remove `vat_management` module; stop quiz writing has_vat/tax_rate/inps_rate
- [x] Add `tax_thermometer`/`annual_revenue` to REMOVED_WIDGET_IDS
- [x] `Transaction::scopeOperationalStats()` + apply budgets/notify/tags
- [x] FinancialMetrics: hard whereNull(investment_id)
- [x] 50/30/20 + analytics: excludeInterHouseholdStats
- [x] Dedup getPeriodStats → DashboardPeriodStatsService
- [x] Tests + pint-check

## Review
### Cosa cambiato
- P.IVA prodotto: termometro backend morto; lifestyle sempre persona (no tax); auth force persona; quiz senza has_vat/tax/inps; modulo vat_management rimosso
- `Transaction::operationalStats()` = no transfer + no investment + IH-safe
- Budget spent (lista/dettaglio/widget/notifiche) + entrate mese: filtri operativi + abs spent
- Tag show, notifica spese mensili, fixed expenses, analytics, formula metric query
- 50/30/20: exclude IH (investimenti restano nel bucket)
- Dashboard periodStats → `DashboardPeriodStatsService` unico

### Verifica
- PHPUnit: 1083 passed
- Pint: green
- Playwright: in corso
