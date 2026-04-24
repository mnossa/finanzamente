# Makefile helper per sviluppo Finanzamente
# Usa UID/GID dell'utente host per evitare problemi di permessi nei volumi

LOCAL_UID ?= $(shell id -u)
LOCAL_GID ?= $(shell id -g)
CI_APP_WAIT_TIMEOUT ?= 300
CI_APP_WAIT_INTERVAL ?= 5
export LOCAL_UID LOCAL_GID

.PHONY: up down restart logs ps dev build build-check bash app node fix-perms migrate fresh seed mysql-root test ci test-auth test-households test-households-feature test-households-unit clear-cache demo-data demo-reset merge-to-staging merge-staging-to-main rebase-staging-from-main composer-install npm-install prune-logs scheduler-logs set-telegram-webhook get-telegram-webhook ngrok ngrok-url ngrok-logs prune-copilot-branches prune-renovate-branches e2e-seed playwright playwright-prelaunch playwright-waitlist playwright-ui playwright-report set-plan waitlist-check magazine-demo composer-update linker-build linker-logs linker-shell link-suggestions prod-local

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

build-check:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec node npm run build 2>&1 | cat

clear-cache:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan optimize:clear

migrate:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan migrate

fresh:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan migrate:fresh

seed:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan db:seed

# Prepara il database per i test E2E (migrate:fresh + E2ESeeder) sul servizio app_e2e dedicato.
# Il servizio app_e2e usa db_e2e — il database principale non viene mai toccato.
e2e-seed:
	@echo "[+] Preparazione database per test E2E (servizio app_e2e → db_e2e)..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app_e2e php artisan config:cache
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app_e2e php artisan migrate:fresh --force
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app_e2e php artisan db:seed --class=E2ESeeder --force
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app_e2e php artisan cache:clear
	@echo "[+] Database E2E pronto (utente: e2e@finanzamente.test)"

###############################################################
# Esegui i test Playwright E2E in modalità headless (modalità normale).
#
# Questo target automatizza tutti i passaggi necessari per garantire
# che l'ambiente sia sempre pronto e consistente prima di lanciare i test E2E:
#   1. Compila gli asset frontend (make build)
#   2. Pulisce tutte le cache Laravel (make clear-cache)
#   3. Prepara il database E2E (make e2e-seed)
#   4. Rimuove public/hot per evitare conflitti con dev server
#   5. Verifica la presenza della build asset
#   6. Lancia i test Playwright su nginx_e2e (porta 8081 → app_e2e → db_e2e)
#
# Il server principale (porta 8080) e il database reale non vengono mai toccati.
#
# Utilizzare SEMPRE questo target per eseguire i test E2E, sia in locale che in CI.
###############################################################
playwright:
	@echo "[+] Build asset frontend (make build)..."
	$(MAKE) build
	@echo "[+] Clear cache Laravel (make clear-cache)..."
	$(MAKE) clear-cache
	@echo "[+] Seed database E2E (make e2e-seed)..."
	$(MAKE) e2e-seed
	@echo "[+] Rimozione public/hot (usa build compilata, non dev server)..."
	@rm -f public/hot
	@test -f public/build/manifest.json || (echo "ERRORE: Esegui 'make build' prima di 'make playwright'" && exit 1)
	@echo "[+] Esecuzione test E2E Playwright (porta 8081 → app_e2e → db_e2e)..."
	@E2E_APP_MODE=normal PLAYWRIGHT_BASE_URL=http://localhost:8081 npx playwright test

# Esegui i test E2E simulando la modalità PRE-LANCIO
# Legge PRE_LAUNCH_OWNER_EMAIL da .env — non modifica .env
playwright-prelaunch:
	@echo "[+] Rimozione public/hot (usa build compilata, non dev server)..."
	@rm -f public/hot
	@test -f public/build/manifest.json || (echo "ERRORE: Esegui 'make build' prima di 'make playwright-prelaunch'" && exit 1)
	@echo "[+] Compilazione config E2E (pre-lancio=on, mail=log)..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec \
		-e PRE_LAUNCH_MODE=true \
		-e PRO_WAITLIST_ENABLED=false \
		-e MAIL_MAILER=log \
		app php artisan config:cache
	@echo "[+] Pulizia cache..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan cache:clear
	@echo "[+] Esecuzione test E2E Playwright (modalità: prelaunch)..."
	@E2E_APP_MODE=prelaunch PLAYWRIGHT_BASE_URL=http://localhost:8080 npx playwright test e2e/public/modes.spec.ts; PLAYWRIGHT_STATUS=$$?; \
	echo "[+] Ripristino config cache dai valori .env originali..."; \
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan config:cache; \
	exit $$PLAYWRIGHT_STATUS

# Esegui i test E2E simulando la modalità WAITLIST (PRO_WAITLIST_ENABLED=true, PRE_LAUNCH_MODE=false)
# Non modifica .env — passa i valori direttamente alla compilazione della config.
playwright-waitlist:
	@echo "[+] Rimozione public/hot (usa build compilata, non dev server)..."
	@rm -f public/hot
	@test -f public/build/manifest.json || (echo "ERRORE: Esegui 'make build' prima di 'make playwright-waitlist'" && exit 1)
	@echo "[+] Compilazione config E2E (waitlist=on, pre-lancio=off, mail=log)..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec \
		-e PRE_LAUNCH_MODE=false \
		-e PRO_WAITLIST_ENABLED=true \
		-e MAIL_MAILER=log \
		app php artisan config:cache
	@echo "[+] Pulizia cache..."
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan cache:clear
	@echo "[+] Esecuzione test E2E Playwright (modalità: waitlist)..."
	@E2E_APP_MODE=waitlist PLAYWRIGHT_BASE_URL=http://localhost:8080 npx playwright test e2e/public/modes.spec.ts; PLAYWRIGHT_STATUS=$$?; \
	echo "[+] Ripristino config cache dai valori .env originali..."; \
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan config:cache; \
	exit $$PLAYWRIGHT_STATUS

# Esegui i test Playwright in modalità UI interattiva (solo locale)
playwright-ui:
	PLAYWRIGHT_BASE_URL=http://localhost:8081 npx playwright test --ui

# Apri l'ultimo report HTML generato da Playwright
playwright-report:
	npx playwright show-report

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

composer-update:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app composer update

npm-install:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec node npm install

prune-logs:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan logs:prune

scheduler-logs:
	docker compose logs -f --tail=100 scheduler

# Registra il webhook Telegram. Uso: make set-telegram-webhook url=https://tuodominio.it
set-telegram-webhook:
	@if [ -z "$(url)" ]; then \
		echo "[ERRORE] Specificare l'URL con: make set-telegram-webhook url=https://tuodominio.it"; \
		exit 1; \
	fi; \
	TOKEN=$$(grep TELEGRAM_BOT_TOKEN .env | cut -d= -f2); \
	curl -s -X POST "https://api.telegram.org/bot$$TOKEN/setWebhook" \
		-d "url=$(url)/telegram/webhook" | python3 -m json.tool

# Mostra lo stato attuale del webhook Telegram
get-telegram-webhook:
	@TOKEN=$$(grep TELEGRAM_BOT_TOKEN .env | cut -d= -f2); \
	curl -s "https://api.telegram.org/bot$$TOKEN/getWebhookInfo" | python3 -m json.tool

# Avvia il tunnel ngrok (profilo Docker 'tunnel') — URL disponibile su http://localhost:4040
ngrok:
	NGROK_AUTHTOKEN=$$(grep NGROK_AUTHTOKEN .env | cut -d= -f2) \
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) \
	docker compose --profile tunnel up ngrok -d
	@echo "[+] ngrok avviato. Controlla l'URL su http://localhost:4040"

# Mostra l'URL pubblico ngrok corrente
ngrok-url:
	@curl -s http://localhost:4040/api/tunnels 2>/dev/null \
		| python3 -c "import sys,json; d=json.load(sys.stdin); print(d['tunnels'][0]['public_url'] if d.get('tunnels') else 'ngrok non attivo')" \
		|| echo 'ngrok non attivo — esegui: make ngrok'

# Log del container ngrok
ngrok-logs:
	docker compose logs -f --tail=100 ngrok

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
	merge_msg=$$(git log main..staging --no-merges --format=%s -1); \
	if [ -z "$$merge_msg" ]; then \
		merge_msg="Merge staging into main"; \
	fi; \
	echo "[+] Merge di staging in main: $$merge_msg" && \
	git merge --no-ff -m "$$merge_msg" staging && \
	git push origin main

# Rebase di staging sull'ultimo main remoto
rebase-staging-from-main:
	@git fetch origin && \
	git checkout main && \
	git pull --ff-only origin main && \
	git checkout staging && \
	git pull --ff-only origin staging && \
	echo "[+] Rebase di staging su main" && \
	git rebase main && \
	git push --force-with-lease origin staging

# Elimina tutti i branch locali e remoti con prefisso copilot/
prune-copilot-branches:
	@current_branch=$$(git branch --show-current); \
	local_branches=$$(git for-each-ref --format='%(refname:short)' refs/heads/copilot/); \
	if [ -z "$$local_branches" ]; then \
		echo "[+] Nessun branch locale copilot/* da eliminare"; \
	else \
		echo "[+] Eliminazione branch locali copilot/*"; \
		for branch in $$local_branches; do \
			if [ "$$branch" = "$$current_branch" ]; then \
				echo "[!] Salto $$branch perche' e' il branch corrente"; \
				continue; \
			fi; \
			git branch -D "$$branch"; \
		done; \
	fi; \
	git fetch origin --prune; \
	remote_branches=$$(git for-each-ref --format='%(refname:strip=3)' refs/remotes/origin/copilot/); \
	if [ -z "$$remote_branches" ]; then \
		echo "[+] Nessun branch remoto origin/copilot/* da eliminare"; \
	else \
		echo "[+] Eliminazione branch remoti origin/copilot/*"; \
		for branch in $$remote_branches; do \
			git push origin --delete "$$branch"; \
		done; \
	fi

# Elimina tutti i branch locali e remoti con prefisso renovate/
prune-renovate-branches:
	@current_branch=$$(git branch --show-current); \
	local_branches=$$(git for-each-ref --format='%(refname:short)' refs/heads/renovate/); \
	if [ -z "$$local_branches" ]; then \
		echo "[+] Nessun branch locale renovate/* da eliminare"; \
	else \
		echo "[+] Eliminazione branch locali renovate/*"; \
		for branch in $$local_branches; do \
			if [ "$$branch" = "$$current_branch" ]; then \
				echo "[!] Salto $$branch perche' e' il branch corrente"; \
				continue; \
			fi; \
			git branch -D "$$branch"; \
		done; \
	fi; \
	git fetch origin --prune; \
	remote_branches=$$(git for-each-ref --format='%(refname:strip=3)' refs/remotes/origin/renovate/); \
	if [ -z "$$remote_branches" ]; then \
		echo "[+] Nessun branch remoto origin/renovate/* da eliminare"; \
	else \
		echo "[+] Eliminazione branch remoti origin/renovate/*"; \
		for branch in $$remote_branches; do \
			git push origin --delete "$$branch"; \
		done; \
	fi




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
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec -e APP_ENV=testing app php -d memory_limit=256M artisan test

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

# [Solo sviluppo] Imposta il piano di un utente (base o pro)
# Uso: make set-plan email=utente@email.it plan=pro
# Opzionale: make set-plan email=utente@email.it plan=pro expires-in=7  (simula scadenza tra 7 giorni)
set-plan:
	@if [ -z "$(email)" ] || [ -z "$(plan)" ]; then \
		echo "Uso: make set-plan email=<email> plan=<base|pro> [expires-in=<giorni>]"; \
		exit 1; \
	fi
	@if [ -n "$(expires-in)" ]; then \
		LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan dev:set-plan "$(email)" "$(plan)" --expires-in="$(expires-in)"; \
	else \
		LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan dev:set-plan "$(email)" "$(plan)"; \
	fi

# Verifica che la configurazione Brevo per la waitlist sia completa e funzionante
waitlist-check:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan waitlist:check

# Popola il magazine con 5-6 articoli fake per ogni categoria (solo locale)
magazine-demo:
	@echo [+] Popolamento magazine con articoli demo 5-6 per categoria...
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan db:seed --class=MagazineArticleDemoSeeder --force
	@echo [+] Articoli demo magazine generati.

# ---------- Python Semantic Linker ----------

# Builda (o rebuilda) l'immagine del servizio python-linker
linker-build:
	@echo [+] Build python-linker...
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose build python-linker
	@echo [+] Build completata. Riavvio del servizio...
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose up -d python-linker

# Log del container python-linker
linker-logs:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose logs -f python-linker

# Shell nel container python-linker
linker-shell:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec python-linker bash

# Esegui manualmente il comando di suggerimento link
link-suggestions:
	@echo [+] Avvio suggerimenti link interni magazine semantici...
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan magazine:link-suggestions
	@echo [+] Completato.

prod-local:
	@echo "[+] Build immagine di produzione (Dockerfile.prod)..."
	docker build -f Dockerfile.prod -t finanzamente:prod .
	@echo "[+] Avvio stack produzione (docker-compose.prod.yml)..."
	docker compose -f docker-compose.prod.yml up --build