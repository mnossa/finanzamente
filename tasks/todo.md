# Split payment: saldo rimanente

## Goal
Link «Saldo rimanente» dal 2° conto; blocca «Aggiungi conto» a totale raggiunto.

## Plan
- [x] SplitPaymentSection UX
- [x] E2E transactions.spec
- [x] make build + playwright (focused)

## Review
### Cosa
- Dal 2° conto: link «Saldo rimanente» → fill importo = totale − altre righe
- A totale raggiunto: nasconde «+ Aggiungi conto» + messaggio

### Verifica
- `make build` OK
- Playwright `pagamento su più conti` desktop+mobile: passed
