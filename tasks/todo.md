# Patrimonio: Liquidi vs Vincolati

## Goal
Fix duplicate instruments under Liquidità; regroup deposit + pension as Vincolati.

## Plan
- [x] Fix `enrichAllocationWithInstruments` → account under own `asset_class`
- [x] Merge `deposit` + `pension` → `locked` / «Vincolati»; label liquidity → «Liquidi»
- [x] Type label Conto Deposito for savings deposits in snapshot
- [x] Investment form labels: only investment classes (no Vincolati triplicato)
- [x] Tests Patrimonio + PensionFund
- [x] `make test` + `make pint-check`

## Review
### Cosa
- Bug: tutti i conti forzati in lista strumenti `liquidity` → duplicati
- Classe unica `locked` (Vincolati): conti deposito + fondi pensione
- Label allocazione: Liquidi / Vincolati

### Verifica
- Full PHPUnit: green
- Filtered recheck: 21 passed
- Pint: PASS
