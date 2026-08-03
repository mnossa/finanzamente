<<<<<<< HEAD
# Fix: split pagamento con buoni pasto

## Goal
Consentire pagamento diviso ticket interi + altro conto (es. contanti). Blocco solo su frazionamento singolo ticket.

## Plan
- [x] Backend: togliere ban split+meal_voucher; validare ticket interi su riga MV
- [x] TransactionSplitService: applySpend/applyIncome su righe MV
- [x] UI Create + Guided: split anche con buoni; MealVoucherSpendSection su riga split
- [x] Test Feature: split ticket+cash OK; importo non multiplo KO
- [x] E2E comment / copy WFI-109 aggiornato
- [ ] Verifica: make test, pint-check, playwright (se UI)

## Review
### Cosa
- Split con un conto buoni pasto + altri conti: OK se ticket interi (`meal_voucher_lines`)
- UI mostra sezione ticket quando una riga split usa buoni pasto
- Messaggio help: ticket interi + contanti

### Verifica
(pending)
=======
# Pulizia totale intro box

## Goal
Rimuovere tutti i box intro decorativi (badge + descrizione) da Index/Show/Create/Edit/Profile.

## Plan
- [x] Index + Show (sessione precedente)
- [x] Create/Edit: header SectionBadge
- [x] Profile: hero + header badge; titoli h2 funzionali ripristinati
- [x] Elimina SectionBadge.tsx
- [x] tsc OK
- [x] make playwright

## Review
### Cosa
- Via: IndexIntroSection, Show gradient intro, Create/Edit header badge, Profile hero
- Profilo: restano solo `h2` sezione (come Sharing/Notifiche)
- `SectionBadge.tsx` eliminato

### Verifica
- tsc OK
- make playwright: 283 passed, 9 skipped
>>>>>>> 1cea806 (Refactor account balance updates to improve performance and maintainability)
