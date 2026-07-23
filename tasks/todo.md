# Soft delete widget + restore Saldo conti

## Plan
1. Migration one-shot: reseed `official.saldo_liquidita` + ensure clone + Home pin (D3)
2. Schema: `formula_widgets.deleted_at` SoftDeletes
3. Removal: soft delete → job purge 30s → forceDelete; restore undoes
4. Official (`is_official_template` o clone da ufficiale): no delete/uninstall
5. Community/custom: confirm + soft delete; clones altri utenti restano (detach `source_id` su purge)
6. UI: dialog conferma + flash undo 30s
7. Tests + pint + playwright

## Done
- [x] migration softDeletes (`2026_06_10_100150`) + restore Home (`2026_07_23_170100`)
- [x] FormulaWidget SoftDeletes + RemovalService + PurgeSoftDeletedFormulaWidgetJob + restore route
- [x] Policy / marketplace block official (+ clone da ufficiale)
- [x] UI confirm + undo toast (Annulla Ns)
- [x] tests green (1036 PHPUnit) + pint + playwright (264 passed)

## Review
- Official = `is_official_template` **o** `source` ufficiale → no delete/uninstall
- Soft delete 30s + queue Redis; sync in test = purge immediato se Queue non faked
- Clone community: su purge `source_id` → null, copie restano
- Home one-shot: reseed template + install missing home-essential + rebuild essentialConfigForUser
