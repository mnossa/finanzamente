# Makefile helper per sviluppo Finanzamente
# Usa UID/GID dell'utente host per evitare problemi di permessi nei volumi
#
# Frontend (npm): usa sempre il container `node` (es. `make build`, `make npm-install`).
# Non eseguire `npm ci` / `npm run build` direttamente sull'host: Vite/Rolldown richiedono
# binding nativi Linux corretti nel container; una installazione solo su Windows/macOS rompe il volume condiviso.

LOCAL_UID ?= $(shell id -u)
LOCAL_GID ?= $(shell id -g)
CI_APP_WAIT_TIMEOUT ?= 300
CI_APP_WAIT_INTERVAL ?= 5
export LOCAL_UID LOCAL_GID

.PHONY: up down restart logs ps app node bash dev build build-check frontend-ci migrate fresh seed clear-cache test pint-check pint-fix test-ci ci composer-install composer-update composer npm-install e2e-seed e2e-wait-healthy playwright playwright-ui playwright-report demo-data python-services-build python-services-logs python-services-shell python-services-pyright-deps prune-logs scheduler-logs queue-logs exec pwa-icons formula-widgets-release fix-perms export-google-sheets setup

up:
	@echo "[+] Avvio stack con UID=$(LOCAL_UID) GID=$(LOCAL_GID)";
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose up -d --remove-orphans

# Primo avvio / clone fresco (dopo cp .env.example .env e ADV_THROTTLE_SALT).
# Stack già up: installa PHP/JS deps, genera APP_KEY se manca, migrate, build frontend, storage:link.
setup: composer-install
	@if ! grep -qE '^APP_KEY=base64:.+' .env 2>/dev/null; then \
		echo "[+] Genero APP_KEY..."; \
		LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan key:generate --force; \
	else \
		echo "[+] APP_KEY già presente"; \
	fi
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan storage:link --force
	$(MAKE) migrate
	$(MAKE) npm-install
	$(MAKE) build
	@echo "[+] Setup completato. Apri http://localhost:8080 — opzionale: make demo-data"

down:
	docker compose down --remove-orphans

restart: down up

logs:
	docker compose logs -f --tail=100

ps:
	docker compose ps

app:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app bash

node:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec node bash

bash: app

dev:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec node npm run dev -- --host --port 5174

build:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec node npm run build

build-check:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec node npm run build 2>&1 | cat

# Installazione lockfile + build frontend nel container Node (stesso flusso della CI locale dopo `make up`)
frontend-ci:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -T node npm ci
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -T node npm run build

clear-cache:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan optimize:clear

migrate:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan migrate

# One-shot Formula Widget Platform bootstrap (also runs automatically via migration 2026_06_10_100200_* on first deploy).
formula-widgets-release:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan formula-widgets:release-bootstrap

fresh:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan migrate:fresh

seed:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan db:seed

# Prepara il database per i test E2E (migrate:fresh + E2ESeeder) sul servizio app_e2e dedicato.
# Il servizio app_e2e usa db_e2e — il database principale non viene mai toccato.
e2e-seed:
	@echo "[+] Preparazione database per test E2E (servizio app_e2e → db_e2e)..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose up -d db_e2e app_e2e nginx_e2e
	$(MAKE) e2e-wait-healthy
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app_e2e php artisan config:cache
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app_e2e php artisan migrate:fresh --force
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app_e2e php artisan db:seed --class=E2ESeeder --force
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app_e2e php artisan cache:clear
	@echo "[+] Database E2E pronto (utente: e2e@finanzamente.test)"

# Attende che nginx_e2e → app_e2e risponda (evita 502 se PHP-FPM non è pronto).
e2e-wait-healthy:
	@echo "[+] Verifica stack E2E su http://localhost:8081 ..."
	@LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose restart app_e2e nginx_e2e >/dev/null
	@for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do \
		if curl -sf -o /dev/null http://localhost:8081/accedi; then \
			echo "[+] Stack E2E pronto."; \
			exit 0; \
		fi; \
		sleep 2; \
	done; \
	echo "ERRORE: stack E2E non risponde su :8081 (502?). Controlla: docker compose logs app_e2e nginx_e2e"; \
	exit 1

###############################################################
# Esegui i test Playwright E2E in modalità headless (modalità normale).
#
# Questo target automatizza tutti i passaggi necessari per garantire
# che l'ambiente sia sempre pronto e consistente prima di lanciare i test E2E:
#   1. npm ci nel container node (binding nativi Linux per Vite 8 / Rolldown sul volume condiviso)
#   2. Compila gli asset frontend (make build)
#   3. Pulisce tutte le cache Laravel (make clear-cache)
#   4. Prepara il database E2E (make e2e-seed)
#   5. Rimuove public/hot per evitare conflitti con dev server
#   6. Verifica la presenza della build asset
#   7. Installa browser Chromium nella cache del runner (host: stesso ambiente di `npx playwright test`)
#   8. Lancia i test Playwright su nginx_e2e (porta 8081 → app_e2e → db_e2e)
#
# Il server principale (porta 8080) e il database reale non vengono mai toccati.
#
# Utilizzare SEMPRE questo target per eseguire i test E2E, sia in locale che in CI.
###############################################################
playwright:
	@echo "[+] npm ci nel container node (Vite/Rolldown: binding @rolldown/binding-linux-x64-gnu)..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -T node npm ci
	@echo "[+] Build asset frontend (make build)..."
	$(MAKE) build
	@echo "[+] Clear cache Laravel (make clear-cache)..."
	$(MAKE) clear-cache
	@echo "[+] Seed database E2E (make e2e-seed)..."
	$(MAKE) e2e-seed
	$(MAKE) e2e-wait-healthy
	@echo "[+] Rimozione public/hot (usa build compilata, non dev server)..."
	@rm -f public/hot
	@test -f public/build/manifest.json || (echo "ERRORE: Esegui 'make build' prima di 'make playwright'" && exit 1)
	@echo "[+] Playwright: installazione browser Chromium (runner host, ~/.cache/ms-playwright)..."
	npx playwright install chromium
	@echo "[+] Esecuzione test E2E Playwright (porta 8081 → app_e2e → db_e2e)..."
	@E2E_APP_MODE=normal PLAYWRIGHT_BASE_URL=http://localhost:8081 npx playwright test

# Esegui i test Playwright in modalità UI interattiva (solo locale)
playwright-ui:
	npx playwright install chromium
	PLAYWRIGHT_BASE_URL=http://localhost:8081 npx playwright test --ui

# Apri l'ultimo report HTML generato da Playwright
playwright-report:
	npx playwright show-report

fix-perms:
	bash ./fix-permissions.sh

# Esempio: make exec cmd="php artisan tinker"
exec:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app $(cmd)

pwa-icons:
	$(MAKE) exec cmd="php scripts/generate-pwa-icons-from-logo.php"

# Esporta household su un Google Sheet esistente (solo local/development).
# Richiede GOOGLE_SHEETS_CREDENTIALS_PATH (+ opzionale GOOGLE_SHEETS_SHARE_WITH) in .env.
# Il foglio deve essere condiviso in Editor con il service account.
# Esempio:
#   make export-google-sheets spreadsheet=1AbC...xyz
# Opzionali: user=1 household=2
export-google-sheets:
	@if [ -z "$(spreadsheet)" ]; then \
		echo "[ERRORE] Specifica l'ID del foglio: make export-google-sheets spreadsheet=SPREADSHEET_ID"; \
		exit 1; \
	fi; \
	ARGS='--spreadsheet-id=$(spreadsheet)'; \
	if [ -n "$(user)" ]; then ARGS="$$ARGS --user=$(user)"; fi; \
	if [ -n "$(household)" ]; then ARGS="$$ARGS --household=$(household)"; fi; \
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app \
		php artisan finanzamente:export-google-sheets $$ARGS

composer-install:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec \
		-e HOME=/tmp \
		-e COMPOSER_HOME=/tmp/composer \
		-e COMPOSER_CACHE_DIR=/tmp/composer/cache \
		app sh -lc 'mkdir -p /tmp/composer/cache && composer install'

composer-update:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app composer update

npm-install:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec node npm install

prune-logs:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan logs:prune

scheduler-logs:
	docker compose logs -f --tail=100 scheduler

queue-logs:
	docker compose logs -f --tail=100 queue-worker

# Installa un pacchetto composer nel container app
composer:
	@if [ -z "$(pkg)" ]; then \
		echo "[ERRORE] Specificare il pacchetto con make composer pkg=vendor/package"; \
		exit 1; \
	fi; \
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app composer require $(pkg)

test:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -e APP_ENV=testing app php -d memory_limit=256M artisan test

# Verifica stile PHP come in CI (non modifica i file)
pint-check:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -T app php ./vendor/bin/pint --test

# Applica automaticamente la formattazione PHP con Pint
pint-fix:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -T app php ./vendor/bin/pint

# Replica i gate CI locali: style check + test
test-ci: pint-check test

# Simula la pipeline CI/CD in locale (frontend via container `node`, come in sviluppo)
ci:
	@echo "[CI] Simulazione pipeline CI/CD in locale..."
	@echo "[CI] Step 1/6 - Avvio stack Docker..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose up -d --build
	@echo "[CI] Attesa container app..."
	@max_wait=$(CI_APP_WAIT_TIMEOUT); \
	interval=$(CI_APP_WAIT_INTERVAL); \
	elapsed=0; \
	until docker compose exec -T app php --version > /dev/null 2>&1; do \
		if [ $$elapsed -ge $$max_wait ]; then \
			echo "[CI] Container app non pronto entro $$max_wait secondi"; \
			docker compose ps app; \
			docker compose logs --tail=50 app; \
			exit 1; \
		fi; \
		sleep $$interval; \
		elapsed=$$((elapsed + interval)); \
		echo "  ...attesa ($$elapsed/$$max_wait s)"; \
	done
	@echo "[CI] Step 2/6 - Dipendenze Node + build frontend (container node: tsc + vite)..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -T node npm ci
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -T node npm run build
	@echo "[CI] Step 3/6 - Installazione dipendenze PHP..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -T \
		-e HOME=/tmp \
		-e COMPOSER_HOME=/tmp/composer \
		-e COMPOSER_CACHE_DIR=/tmp/composer/cache \
		app sh -lc 'mkdir -p /tmp/composer/cache && composer install --optimize-autoloader --no-interaction'
	@echo "[CI] Step 4/6 - Verifica coding style PHP (Pint)..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -T app php ./vendor/bin/pint --test
	@echo "[CI] Step 5/6 - Esecuzione suite di test..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -T -e APP_ENV=testing app php artisan test; \
	EXIT_CODE=$$?; \
	echo "[CI] Step 6/6 - Pulizia..."; \
	if [ $$EXIT_CODE -eq 0 ]; then \
		echo "[CI] Pipeline completata con successo!"; \
	else \
		echo "[CI] Pipeline fallita con codice $$EXIT_CODE"; \
	fi; \
	exit $$EXIT_CODE

# Genera dati demo: 2 utenti, 4 household, 16000 transazioni totali, debiti e ricorrenze
demo-data:
	@echo "[+] Generazione dati demo (può richiedere alcuni minuti)..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan db:seed --class=DemoDataSeeder

# ---------- Servizi Python ausiliari (FastAPI: cohort insights, …) ----------

python-services-build:
	@echo [+] Build python-services...
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose build python-services
	@echo [+] Build completata. Riavvio del servizio...
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose up -d python-services

python-services-logs:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose logs -f python-services

python-services-shell:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec python-services bash

# Dipendenze leggere in python-services/.pyright-deps (gitignored) per Pyright/Pylance su pydantic.
python-services-pyright-deps:
	cd python-services && rm -rf .pyright-deps && python3 -m pip install -q --target .pyright-deps "pydantic>=2.7"

