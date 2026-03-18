# Makefile helper per sviluppo Finanzamente
# Usa UID/GID dell'utente host per evitare problemi di permessi nei volumi

LOCAL_UID ?= $(shell id -u)
LOCAL_GID ?= $(shell id -g)
CI_APP_WAIT_TIMEOUT ?= 300
CI_APP_WAIT_INTERVAL ?= 5
export LOCAL_UID LOCAL_GID

.PHONY: up down restart logs ps dev build bash app node fix-perms migrate fresh seed mysql-root test ci test-auth test-households test-households-feature test-households-unit clear-cache demo-data demo-reset merge-to-staging merge-staging-to-main composer-install npm-install prune-logs scheduler-logs

up:
	@echo "[+] Avvio stack con UID=$(LOCAL_UID) GID=$(LOCAL_GID)";
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose up -d

down:
	docker compose down

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

clear-cache:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan optimize:clear

migrate:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan migrate

fresh:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan migrate:fresh

seed:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan db:seed

fix-perms:
	bash ./fix-permissions.sh

mysql-root:
	docker compose exec db mysql -uroot -proot

# Reset password root MySQL (procedura guidata)
reset-mysql-root-password-step:
	@echo "[STEP 1] Stoppa il container MySQL principale:"
	docker compose stop db
	@echo "[STEP 2] Avvia un container temporaneo in shell:"
	echo 'Esegui: docker compose run --rm --name temp-mysql-reset db sh'
	@echo "[STEP 3] All'interno del container, esegui:"
	echo 'mysqld --skip-networking --skip-grant-tables'
	@echo "[STEP 4] In un altro terminale, esegui:"
	echo 'docker compose exec temp-mysql-reset mysql -e \"UPDATE mysql.user SET authentication_string=PASSWORD(\'root\') WHERE User=\'root\'; FLUSH PRIVILEGES;\"'
	@echo "[STEP 5] Termina e rimuovi il container temporaneo, poi riavvia db:"
	echo 'exit'
	docker compose rm -f temp-mysql-reset
	docker compose start db

# Esempio: make exec cmd="php artisan tinker"
exec:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app $(cmd)

composer-install:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app composer install

npm-install:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec node npm install

prune-logs:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan logs:prune

scheduler-logs:
	docker compose logs -f --tail=100 scheduler

# Installa un pacchetto composer nel container app
composer:
	@if [ -z "$(pkg)" ]; then \
		echo "[ERRORE] Specificare il pacchetto con make composer pkg=vendor/package"; \
		exit 1; \
	fi; \
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app composer require $(pkg)

# Merge del branch corrente dentro staging
merge-to-staging:
	@current_branch=$$(git branch --show-current); \
	if [ -z "$$current_branch" ]; then \
		echo "[ERRORE] Impossibile determinare il branch corrente."; \
		exit 1; \
	fi; \
	if [ "$$current_branch" = "staging" ]; then \
		echo "[ERRORE] Sei gia' su staging."; \
		exit 1; \
	fi; \
	echo "[+] Merge di $$current_branch in staging"; \
	git fetch origin && \
	git checkout staging && \
	git pull --ff-only origin staging && \
	git merge --no-ff "$$current_branch" && \
	git push origin staging

# Merge di staging dentro main
merge-staging-to-main:
	@git fetch origin && \
	git checkout staging && \
	git pull --ff-only origin staging && \
	git checkout main && \
	git pull --ff-only origin main && \
	echo "[+] Merge di staging in main" && \
	git merge --no-ff staging && \
	git push origin main




#
exec-recurring:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan recurring:generate

# Controlla duplicati senza eliminare
check-duplicates:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan recurring:clean-duplicates --dry-run

# Elimina duplicati (richiede conferma)
clean-duplicates:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan recurring:clean-duplicates

test:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -e APP_ENV=testing app php artisan test

# Simula la pipeline CI/CD in locale (identica a GitHub Actions)
ci:
	@echo "[CI] Simulazione pipeline CI/CD in locale..."
	@echo "[CI] Step 1/4 - Avvio stack Docker..."
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
	@echo "[CI] Step 2/4 - Installazione dipendenze PHP..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -T app composer install --optimize-autoloader --no-interaction
	@echo "[CI] Step 3/4 - Esecuzione suite di test..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -T -e APP_ENV=testing app php artisan test; \
	EXIT_CODE=$$?; \
	echo "[CI] Step 4/4 - Pulizia..."; \
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

# Reset completo database con dati demo
demo-reset:
	@echo "[+] Reset completo database e generazione dati demo..."
	@echo "[!] ATTENZIONE: tutti i dati esistenti saranno eliminati!"
	@read -p "Continuare? [y/N] " confirm; \
	if [ "$$confirm" = "y" ] || [ "$$confirm" = "Y" ]; then \
		LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan migrate:fresh --seed && \
		LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan db:seed --class=DemoDataSeeder; \
	else \
		echo "Operazione annullata."; \
	fi
