# Audit dipendenze e configurazioni sensibili (W2-D13)

Data riferimento: 2026-04-27.

## Sintesi

- **Segreti**: chiavi API e token devono restare solo in variabili d’ambiente (`APP_KEY`, `MOLLIE_*`, `TELEGRAM_*`, `BREVO_*`, chiavi DB, ecc.). Il repository espone solo `.env.example` senza valori reali.
- **Frontend**: eseguito `npm audit fix --omit=dev`; audit successivo riporta **0 vulnerabilità** sul tree corrente.
- **Composer**: sul runner locale il comando `composer audit` non è disponibile (versione Composer vecchia). **Azione consigliata**: aggiornare Composer e periodicamente eseguire `composer audit` in CI o in locale.

## Config applicativa

- `config/services.php` legge segreti da `env()`; nessuna chiave hardcoded nel codice applicativo rilevante ai task audit.
- Webhook Telegram/Mollie: verifica header opzionale documentata in README / `.env.example`; idempotenza lato controller già implementata.

## Remediation / follow-up

| Priorità | Azione |
|----------|--------|
| Media | Aggiornare Composer e aggiungere `composer audit` (o equivalente) alla pipeline se compatibile con il progetto. |
| Bassa | Ripetere `npm audit` prima di ogni release; aggiornare dipendenze dirette (`axios`, ecc.) quando escono advisory nuovi. |
| Bassa | Verificare su server/produzione che `.env` non sia versionato e che backup non esponga segreti in chiaro. |
