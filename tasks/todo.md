# Fix overflow widget tabellare su mobile

## Plan
- [x] `FormulaTableWidget`: `table-fixed`, truncate affidabile, colonne secondarie `hidden sm:table-cell`, wrapper `min-w-0 overflow-x-auto`
- [x] Containment: `min-w-0` su preview panel, aside Create, wrapper table in `CustomFormulaWidget`
- [x] Evitare doppio padding list-body sul table (shell già padda)
- [x] E2E mobile: anteprima tabella non allarga `documentElement` oltre viewport
- [x] Verify: `make test` → `make pint-check` → `make playwright` (markup React)

## Review
- Root cause: `table-layout: auto` + 5 colonne + `min-width: auto` su grid/flex → overflow pagina su mobile in Anteprima «Elenco movimenti»
- Fix: `table-fixed` + colonne secondarie nascoste `< sm` + `min-w-0` sulla catena (preview, Create, card dashboard, ContentPanelShell)
- E2E: `table-widget.spec.ts` assert overflow + header secondari assenti su mobile
- Side fix: assert FAB desktop usa `toBeHidden()` (era `toHaveCount(0)` su nodo `lg:hidden`)
- Gates: `make test` 1058 passed; `make pint-check` PASS; table-widget + bottom-nav E2E green (suite full: 267 passed prima del fix FAB)
