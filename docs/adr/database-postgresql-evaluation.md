# ADR — Valutazione migrazione MySQL → PostgreSQL (WFI-100)

- **Stato**: Accettato
- **Data**: 2026-07-07
- **Ticket**: WFI-100
- **Decisione**: **Opzione A** — restare su **MySQL 9.6**

## Contesto

Finanzamente usa un unico database MySQL 9.6 per:

- **Dashboard** (transazioni, budget, investimenti, analytics Inertia)
- **Magazine** (articoli blog in Markdown: `magazine_articles`, `magazine_categories`)

Stack: Laravel, Docker Compose (dev/prod/E2E), backup `mysqldump` cifrato, test PHPUnit su SQLite.

WFI-100 richiede valutazione pro/contro/costi di migrazione a PostgreSQL, con vincolo di **zero perdita dati** in caso di cutover.

## Analisi (sintesi)

| Area | Impatto migrazione PG |
|------|------------------------|
| Dashboard | Medio-alto (analytics SQL, regex, enum) |
| Magazine | Basso (Markdown in DB, nessun FULLTEXT) |
| Ops | Alto (backup, script, E2E tutti MySQL) |
| Effort stimato cutover | 12–20 giorni dev + ops |
| ROI immediato | Basso |

La migrazione a PostgreSQL è **fattibile** ma non giustificata oggi: nessun requisito business bloccante, rischio operativo elevato (cfr. incidente MySQL 8→9.6 in [`tasks/lessons.md`](../../tasks/lessons.md)).

## Decisione

**Restare su MySQL 9.6.** Nessun artefatto PostgreSQL nel codebase.

### Azioni adottate

- [x] Allineamento docs a MySQL 9.6
- [x] Runbook backup/restore: [`docs/runbooks/mysql-backup-restore.md`](../runbooks/mysql-backup-restore.md)
- [x] `make db-backup-restore-test` per verifica locale
- [x] `DatabaseDialect` — astrazione SQL sqlite/mysql per analytics e filtri (refactor interno, non legato a PG)

### Non adottato

- Cutover PostgreSQL
- Stack Docker PG, `pdo_pgsql`, CI su PG
- Runbook/script pgloader

## Riferimenti

- [`docker-compose.yml`](../../docker-compose.yml) — MySQL 9.6 dev/E2E
- [`docker-compose.prod.yml`](../../docker-compose.prod.yml) — MySQL 9.6 prod + backup
