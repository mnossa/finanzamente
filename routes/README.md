# Routing Structure

Per mantenere il routing leggibile e reviewabile, usa questi file:

- `web/public.php`: rotte pubbliche, webhook server-to-server, waitlist/prelaunch.
- `web/authenticated.php`: rotte area autenticata (con e senza household attiva).
- `auth.php`: rotte standard di autenticazione Laravel/Breeze.
- `web.php`: entrypoint minimale che include i file sopra tramite `require`, più catch-all 404 per URL legacy rimossi.

Le definizioni delle rotte stanno **solo** sotto `routes/web/`; non esistono più file duplicati tipo `web_public.php` nella radice di `routes/`.

## Regole pratiche

- Aggiungi nuove rotte nel file di dominio corretto, non direttamente in `web.php`.
- Mantieni i middleware vicino alle route (o al gruppo) per chiarezza.
- Per endpoint webhook esterni, documenta sempre requisiti di sicurezza (firma/header/idempotenza).
