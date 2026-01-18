# Makefile helper per sviluppo Finanzamente
# Usa UID/GID dell'utente host per evitare problemi di permessi nei volumi

LOCAL_UID ?= $(shell id -u)
LOCAL_GID ?= $(shell id -g)
export LOCAL_UID LOCAL_GID

.PHONY: up down restart logs ps dev bash app node fix-perms migrate fresh seed

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