# Conti ticket: valore storico + label dashboard

## Piano
- [x] Rimuovere vincolo `effective_from >= oggi` (Form Request + `MealVoucherLedgerService` + UI Show)
- [x] Test: salvare valore ticket nel passato; aggiornare test che lo vietava
- [x] Form transazione: mostrare valore ticket vigente alla data selezionata (history in account options)
- [x] Dashboard: label italiana «Buoni pasto» + conteggio ticket
- [x] `make test` + `make pint-check` (+ accounts E2E; full playwright: 255 passed, 3 fail preesistenti non correlati)

## Review
- Valore ticket: date passate ammesse; lotti esistenti non ricalcolati; accrediti usano `unitValueOn(data TX)`
- Dashboard: `type_label` + `ticket_count` da `AccountBalanceService::mapAccountsWithBalance`
- UI copy: «Salva valore»; hint date storiche su Show e Create TX
- Verifica: PHPUnit 1011 passed, pint PASS, e2e accounts 17/17 (incluso buoni pasto)
