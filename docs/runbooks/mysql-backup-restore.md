# Runbook — Backup e restore MySQL

Verifica periodica che i dump siano ripristinabili. Stack: **MySQL 9.6** (dev/prod), backup cifrati AES-256 come in [`docker-compose.prod.yml`](../../docker-compose.prod.yml).

## Prerequisiti

- Stack dev avviato: `make up`
- Variabili in `.env`: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `BACKUP_ENCRYPTION_KEY` (prod)

## Test locale (dev)

```bash
# 1. Dump dal container dev
docker compose exec -T db mysqldump -ufinanzamente --password=finanzamente \
  --single-transaction --set-gtid-purged=OFF --no-tablespaces \
  finanzamente \
  | gzip > /tmp/finanzamente-test-backup.sql.gz

# 2. Conta righe prima (esempio)
docker compose exec -T db mysql -ufinanzamente --password=finanzamente finanzamente \
  -e "SELECT COUNT(*) AS users FROM users; SELECT COUNT(*) AS articles FROM magazine_articles;"

# 3. Ripristino su DB temporaneo
docker compose exec -T db mysql -uroot --password=root \
  -e "DROP DATABASE IF EXISTS finanzamente_restore_test; CREATE DATABASE finanzamente_restore_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

gunzip -c /tmp/finanzamente-test-backup.sql.gz \
  | docker compose exec -T db mysql -uroot --password=root finanzamente_restore_test

# 4. Verifica row count identico
docker compose exec -T db mysql -uroot --password=root finanzamente_restore_test \
  -e "SELECT COUNT(*) AS users FROM users; SELECT COUNT(*) AS articles FROM magazine_articles;"

# 5. Pulizia
docker compose exec -T db mysql -uroot --password=root \
  -e "DROP DATABASE finanzamente_restore_test;"
rm -f /tmp/finanzamente-test-backup.sql.gz
```

Automazione: `make db-backup-restore-test` (vedi [`Makefile`](../../Makefile)).

## Produzione (backup cifrato)

Backup automatico ogni 24h nel volume `backups`. Ripristino:

```bash
openssl enc -d -aes-256-cbc -pbkdf2 -pass pass:"$BACKUP_ENCRYPTION_KEY" \
  -in /path/to/backup.sql.gz.enc \
  | gunzip \
  | docker compose -f docker-compose.prod.yml exec -T db \
      mysql -u"$DB_USERNAME" --password="$DB_PASSWORD" "$DB_DATABASE"
```

Dettaglio completo: [`docs/HETZNER_SETUP.md`](../HETZNER_SETUP.md) sezione rollback database.

## Checklist post-restore

- [ ] Row count tabelle critiche: `users`, `transactions`, `magazine_articles`, `accounts`
- [ ] Login utente demo
- [ ] Dashboard carica analytics mensili
- [ ] Articolo magazine pubblicato visibile su `/magazine/{slug}`
- [ ] `php artisan migrate:status` — nessuna migration pending inattesa

## Frequenza consigliata

- **Dev**: dopo modifiche a script backup/restore
- **Prod**: almeno **trimestrale** (dry-run su staging con dump prod anonimizzato)
