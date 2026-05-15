# Deploy Dry Run Checklist

Checklist operativa per validare il flusso di rilascio senza impattare la produzione.

## 1) Preflight locale

- Eseguire `make deploy-dry-run`
- Verificare build immagine `Dockerfile.prod` completata
- Verificare `docker compose -f docker-compose.prod.yml config` senza errori

## 2) Preflight CI

- Workflow `deploy.yml` verde su branch di test/staging
- Build frontend + Pint + test backend completati
- Nessun warning bloccante su quality gate

## 3) Preflight server (manuale)

- Spazio disco sufficiente su host deploy
- Presenza variabili ambiente richieste (`.env` server)
- Accesso al registry e permessi pull immagine
- Dopo ogni release produzione: la pipeline esegue `php artisan view:clear` sul container app (cache Blade compilata); in intervento manuale usare lo stesso comando se aggiorni viste senza workflow.

## 4) Simulazione rollback (manuale)

- Verificare che `IMAGE_TAG` precedente sia presente in `.env` server
- Verificare procedura rollback documentata nel job deploy
- Confermare comandi di ripristino container (`docker compose ... up -d`)

## 5) Go/No-Go

- Se tutti i check passano: procedere al deploy reale
- In caso di fallimento di un check: bloccare release e aprire issue di remediation

## Note (W2-D14)

La checklist è pensata per essere completata una volta **su staging** e una volta **su produzione** (SSH): il repository fornisce `make deploy-dry-run` per validazione locale dell’immagine; la verifica rollback sul server richiede accesso effettivo all’host di deploy.

## Template evidenza (incolla in issue dopo esecuzione su server)

- Ambiente: staging / produzione (data):
- `IMAGE_TAG` prima del deploy:
- `IMAGE_TAG` dopo deploy riuscito:
- Esito health check app (healthy / unhealthy):
- Rollback provato (sì/no): se sì, tag ripristinato e esito health:
- Note / anomalie:
