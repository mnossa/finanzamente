# Fix overflow widget tabellare su mobile

## Plan
- [ ] `FormulaTableWidget`: `table-fixed`, truncate affidabile, colonne secondarie `hidden sm:table-cell`, wrapper `min-w-0 overflow-x-auto`
- [ ] Containment: `min-w-0` su preview panel, aside Create, wrapper table in `CustomFormulaWidget`
- [ ] Evitare doppio padding list-body sul table (shell già padda)
- [ ] E2E mobile: anteprima tabella non allarga `documentElement` oltre viewport
- [ ] Verify: `make test` → `make pint-check` → `make playwright` (markup React)

## Review
(pending)
