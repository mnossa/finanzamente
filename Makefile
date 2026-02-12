# Makefile helper per sviluppo Finanzamente
# Usa UID/GID dell'utente host per evitare problemi di permessi nei volumi

LOCAL_UID ?= $(shell id -u)
LOCAL_GID ?= $(shell id -g)
export LOCAL_UID LOCAL_GID

.PHONY: up down restart logs ps dev bash app node fix-perms migrate fresh seed mysql-root

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


#
exec-recurring:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan recurring:generate

# Controlla duplicati senza eliminare
check-duplicates:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan recurring:clean-duplicates --dry-run

# Elimina duplicati (richiede conferma)
clean-duplicates:
	LOCAL_UID=$(LOCAL_UID) LOCAL_GID=$(LOCAL_GID) docker compose exec app php artisan recurring:clean-duplicates