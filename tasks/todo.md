# Widget wizard, transfer date sync, homepage honesty

## Plan
- [x] Widget wizard: filter display types by recipe (tabular → only table; no chart ask)
- [x] Widget wizard: hard-filter metrics by recipe/display; no soft “show all” for incompatible
- [x] Sync selected metric → series / value_code / metric_query so preview + save respect choice
- [x] Fix checkbox/state bleed between recipe changes and edit sessions
- [x] Faster / clearer preview updates + better step guidance
- [x] Undo toast: slightly smaller countdown font
- [x] Transfer date edit: sync date to linked transaction + tests
- [x] Ensure dashboard/widget cache invalidates on transfer-linked tx date change
- [x] Homepage copy: honest tracker/analysis language (no bank-ops illusion)
- [x] Tests + simulate scenarios; gates `make test` → `make pint-check` (playwright bloccato da rete DinD)

## Review
- Wizard: `formulaWidgetRecipe.ts` + Create/MetricScenarioPicker/Guide; tabular non propone barre; metriche hard-filter
- Transfer: `TransactionController` sync `date` sulla gamba collegata; versione widget include `MAX(date)`; HTTP `no-cache`
- Home: welcome + SEO onesti (tracker + analisi, CSV file, no Open Banking)
- Verifica host: PHPUnit green (1063 passed), pint OK, tsc OK; E2E nuovi specs aggiunti ma suite Playwright non eseguibile qui (bridge Docker senza ping tra container)
