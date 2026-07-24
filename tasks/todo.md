# Tabular formula widgets (liste + aggregate)

## Plan
- [x] Migration `display_type` VARCHAR + `DISPLAY_TABLE` + `chart_types.table`
- [x] `metric_queries`: investment_pacs, list_columns, group_by_fields, limits
- [x] List/group builders + MetricQueryService + PayloadBuilder `buildTable` + validator
- [x] Create recipe tabular + FE mode/datasource/group_by + FormulaTableWidget
- [x] Preset ufficiali (tx, PAC, spese per categoria) + unit/feature + E2E smoke
- [x] Verify: `make test` → `make pint-check` → `make build` → `make playwright` (1 flake pin-chooser ritentato OK; test hardening)

## Review
- `display_type` string(32) in create migration (SQLite CHECK non blocca `table`); MySQL widen migration resta per DB legacy ENUM
- Template ufficiali: `ultime_transazioni`, `pac_attivi`, `spese_per_categoria` (count da config nei test)
- Create recipe «Tabella / lista»: mode rows|aggregate, datasource, group_by, row_limit
- E2E: `table-widget.spec.ts`; create metric-query tollera redirect pin `aggiungi`
