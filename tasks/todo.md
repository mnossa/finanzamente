# Fix: salvataggio transazioni bloccato

## Goal
Salva → loader → resta su stessa schermata (no redirect, no errore visibile).

## Cause
Guided create default = primo conto alfabetico = «Buoni pasto». Senza `meal_voucher_lines` → 422 silenzioso su step review.

## Plan
- [x] Preferire conti liquidi come default (`transactionAccounts` + controller)
- [x] Guided: errori su review + jump allo step; UI buoni pasto se conto MV
- [x] Test helper + gate `make test` / pint / playwright focused
- [x] Review

## Review
### Cosa
- Default form: banca/carta/contanti prima di buoni pasto / deposito / fondo pensione
- Wizard: banner errori + salto allo step giusto; `MealVoucherSpendSection` su step conto
- Test: `create_form_prefers_liquid_account_over_meal_voucher_as_default`

### Verifica
- `make test` 1041 pass
- `make pint-check` OK
- `make build` OK
- Playwright focused transactions: pass
