# Makefile commands (agent reference)

Always use `make` (never raw `docker compose` / host `npm`) for correct UID/GID.

## Containers
| Command | Action |
|---------|--------|
| `make up` | Start stack |
| `make setup` | After `up`: composer, APP_KEY, storage:link, migrate, npm, build |
| `make down` | Stop stack |
| `make restart` | down + up |
| `make logs` | Container logs |
| `make ps` | Status |

## Shell access
| Command | Action |
|---------|--------|
| `make app` / `make bash` | PHP/Laravel container |
| `make node` | Node container |
| `make mysql-root` | MySQL root CLI |

## Database
| Command | Action |
|---------|--------|
| `make migrate` | Run migrations |
| `make formula-widgets-release` | One-shot Formula Widget release bootstrap (templates + dashboard migration); also auto via migration `2026_06_10_100200_*` |
| `make fresh` | migrate:fresh |
| `make seed` | Seeders |
| `make db-pull-prod` | Dump MySQL da produzione → `storage/backups/` (`.env.db-pull`) |
| `make db-import-local` | Import ultimo dump da `storage/backups/` nel DB locale |
| `make db-import-local FILE=...` | Import dump specifico |
| `make db-anonymize` | Anonimizza PII nel DB locale (dopo import prod) |
| `make db-backup-restore-test` | Verifica dump/restore MySQL locale (WFI-100) |

Comandi artisan **solo local/development/testing** (bloccati su production/staging se eseguiti a mano):
- `transactions:delete-for-account` — usa `make` / `docker compose` **senza** `docker-compose.prod.yml`
- `db:anonymize` — vedi `make db-anonymize`
- `transaction-imports:mark-stale` — manuale solo in local; in prod solo via scheduler (`--scheduled`)

## Testing
| Command | Action |
|---------|--------|
| `make test` | Full PHPUnit (SQLite in-memory in container) |
| `make pint-check` | PHP style check (CI) |
| `make pint-fix` | Apply Pint fixes |
| `make test-ci` | pint-check + test |
| `make e2e-seed` | E2E DB fresh + E2ESeeder |
| `make playwright` | E2E headless (port 8081) |
| `make playwright-ui` | E2E interactive UI |
| `make playwright-report` | Open HTML report |

## Frontend
| Command | Action |
|---------|--------|
| `make dev` | Vite HMR (container, port 5174) |
| `make build` | Production assets (**in node container**) |
| `make clear-cache` | Laravel caches |

## Dependencies
| Command | Action |
|---------|--------|
| `make composer-install` | PHP deps |
| `make composer pkg=vendor/package` | Add PHP package |
| `make npm-install` | Node deps |
| `make exec cmd="..."` | Custom command in app container |

## Utilities
| Command | Action |
|---------|--------|
| `make fix-perms` | Fix file permissions |
| `make demo-data` | Demo users/data |
| `make export-google-sheets spreadsheet=ID` | Export household → Google Sheet esistente (`user=` / `household=` opzionali) |
| `make ci` | Full CI gate locally |
| `make python-services-*` | Helper servizio FastAPI |

See `Makefile` for the full target list.
