# Inbox — proposta unione voci simili + tag

## Piano
- [x] `TagResolutionService` (reuse da TransactionController)
- [x] `InboxSimilarClusterService`: stesso desc/conto/categoria/tipo, data ±1 giorno
- [x] Controller: cluster in index; tag su confirm; merge + confirm-separate
- [x] UI Inbox: card gruppo + TagAutocomplete (unisci / mantieni separate / conferma singola)
- [x] Test Feature + Unit
- [x] `make test`, `make pint-check`, `make playwright`

## Review
- Cluster su voci pending: stessa descrizione (case-insensitive), conto, categoria, tipo; date entro 1 giorno (mezzanotte). Importi possono differire → merge somma in valuta conto.
- UI: card “Voci simili” sopra lista; modal con Unisci / Mantieni separate + etichette comuni; conferma singola ora ha TagAutocomplete.
- Gate: PHPUnit 990 passed, pint verde, Playwright 258 passed / 8 skipped.
