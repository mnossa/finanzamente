# Liquidità: escludi vincolati

## Goal
`household_balance` / widget Liquidità / dashboard total = solo spendibile (no deposito, no pensione).

## Plan
- [x] `Account::isLockedBalance()`
- [x] `computeHouseholdTotal(..., includeLocked: false)` default
- [x] Accounts index: `includeLocked: true`
- [x] `resolveLiquidAt` + patrimonio series include locked a parte
- [x] NetWorth cash mode: solo liquidi
- [x] Tests + pint

## Review
### Cosa
Liquidità homepage allineata a Patrimonio `liquidValue`.
Lista Conti mantiene somma di tutti i conti attivi.

### Verifica
- Full PHPUnit: green
- Pint: PASS
