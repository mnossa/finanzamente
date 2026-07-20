# WFI-109 — Conto buoni pasto (meal_voucher)

## Plan
- [x] Migration `ticket_unit_value` on accounts + ENUM `meal_voucher`
- [x] `Account::TYPES` + helpers (`isMealVoucher`, ticket count formula)
- [x] Form requests store/update validation
- [x] Expense eligibility: meal_voucher allowed for expenses (already via non-deposit)
- [x] AccountController show/create/edit/store/update props
- [x] UI Create/Edit/Show/Guided
- [x] Feature + Unit tests
- [x] `make test` + `make pint-check` + playwright accounts meal voucher

## Formula
`ticket_count = max(0, floor(current_balance / ticket_unit_value))` when unit > 0  
Per TX on show: `tickets_delta = round(amount / ticket_unit_value, 2)` (signed)

## Review
- Backend: type `meal_voucher`, `ticket_unit_value`, show KPI + deltas
- UI ticket only on Account show (index/dashboard untouched)
- Verify: 998 PHPUnit passed, pint OK, e2e buoni pasto desktop+mobile OK
