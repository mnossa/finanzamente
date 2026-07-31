# Align liquidValue negatives with homepage

## Goal
Patrimonio `liquidValue` / `totalValue` = stessa regola di `household_balance` (negativi inclusi).

## Plan
- [x] PortfolioSnapshotService: liquid KPI include negativi
- [x] Allocazione/torta: solo saldi positivi (no pie rotta)
- [x] Update FinancialConsistencyTest + hint UI
- [x] make test + pint-check

## Review
### Cosa
Homepage e Patrimonio «Saldo conti» stessa formula: liquidi non vincolati, negativi inclusi.
Allocazione esclude saldi ≤0 dalla torta.

### Verifica
- Full PHPUnit green
- Pint PASS
