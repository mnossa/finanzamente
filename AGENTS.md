# AGENTS.md

## Cursor Cloud / contributor notes

### Dev stack

All commands use the Makefile (UID/GID). Key targets:

| Action | Command |
|--------|---------|
| Start stack | `make up` |
| PHP deps | `make composer-install` |
| Node deps | `make npm-install` |
| Migrate | `make migrate` |
| Frontend build | `make build` |
| Vite HMR | `make dev` |
| Tests | `make test` |
| Lint PHP | `make pint-check` |
| E2E | `make e2e-seed` then `make playwright` |

See [README.md](README.md) and [docs/technical.md](docs/technical.md).

### Gotchas

- PHPUnit uses SQLite in-memory (`.env.testing`), not Docker MySQL.
- Frontend build only inside the `node` container (`make build` / `make npm-install`).
- Demo users after `make demo-data`: `mario.rossi@example.com` / `laura.bianchi@example.com`, password `password`.
- App: port **8080**. E2E isolated app: port **8081**.
- No production deploy workflow in this repository (CI = lint + tests only).
