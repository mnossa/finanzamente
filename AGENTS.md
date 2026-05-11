# AGENTS.md

## Cursor Cloud specific instructions

### Docker-in-Docker (nested containers)

Cloud Agent VMs run inside Docker, causing cgroup v2 issues with the default `runc` runtime. The update script installs `crun` 1.20+ and configures Docker to use a wrapper (`crun-nocg`) that passes `--cgroup-manager=disabled`. This bypasses cgroup controller limitations in the nested environment.

Key daemon.json config:
```json
{
  "storage-driver": "fuse-overlayfs",
  "default-runtime": "crun-nocg",
  "runtimes": {
    "crun-nocg": { "path": "/usr/local/bin/crun-nocg" }
  }
}
```

### Environment setup

After Docker is running, create a `.env` file from `.env.example` and add Docker-specific overrides:

```bash
cp .env.example .env
# Then add/override these values in .env:
# DB_CONNECTION=mysql
# DB_HOST=db
# DB_PORT=3306
# DB_DATABASE=finanzamente
# DB_USERNAME=finanzamente
# DB_PASSWORD=finanzamente
# APP_URL=http://localhost:8080
# ADV_THROTTLE_SALT=dev-cloud-agent-salt
# MAIL_MAILER=smtp
# MAIL_HOST=mailpit
# MAIL_PORT=1025
# PYTHON_SERVICES_URL=http://python-services:8000
```

Then generate the app key: `docker compose exec app php artisan key:generate`

### Service startup and development

All commands use the Makefile (see README.md for the full list). Key commands:

| Action | Command |
|---|---|
| Start stack | `make up` |
| Install PHP deps | `make composer-install` |
| Install Node deps | `make npm-install` |
| Run migrations | `make migrate` |
| Build frontend | `make build` |
| Vite dev server (HMR) | `make dev` |
| Run tests | `make test` |
| Lint (PHP style) | `make pint-check` |
| CI gate (lint+test) | `make test-ci` |
| Generate demo data | `make demo-data` |

### Gotchas

- **PHPUnit tests use SQLite in-memory** (`.env.testing`), not the Docker MySQL instance. No MySQL needed for `make test`.
- **Frontend assets must be built inside the `node` container** — never run `npm run build` on the host. Use `make build`.
- **Vite dev server** runs inside the `node` container on port 5174. Use `make dev` to start it.
- **Demo users**: `mario.rossi@example.com` and `laura.bianchi@example.com`, both with password `password` (after `make demo-data`).
- The app on port 8080 serves via Nginx → PHP-FPM. Port 8081 is the E2E-isolated instance.
- **`deploy.resources` in `docker-compose.yml`** (python-services service) has memory limits that are silently ignored with crun's `--cgroup-manager=disabled`. This is expected and harmless in the Cloud Agent environment.
- The Docker socket needs `chmod 666 /var/run/docker.sock` after each `dockerd` restart since the agent user is not root.
