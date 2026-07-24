# Alert spese → semaforo

- [x] Progress `variant: traffic_light` + soglia numerica (`number` param / `threshold_amount`)
- [x] UI semaforo (verde/arancio/rosso) — niente più 0/1 €
- [x] Scenario + migration one-shot legacy IF→MAX + widget progress
- [x] `make test` + `make pint-check` + `make build` + E2E formula-widgets green

## Review
- Bande default: warn 70%, danger 100%
- Soglia editabile in wizard avanzato e in dashboard runtime
- Recipe «Obiettivo / soglia» + card Alert → progress semaforo automatico
