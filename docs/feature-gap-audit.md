# Audit coerenza feature: dichiarato vs implementato

Data: 2026-04-28

## Scopo

Confrontare feature dichiarate in documentazione/regole con feature realmente presenti nel codice.

Fonti dichiarato:

- `README.md`
- `.cursorrules`
- `docs/features/*.md`
- `docs/servizi-integrati.md`

Fonti implementato:

- `routes/web/*.php`
- `app/Http/Controllers/*`
- `app/Services/*`
- `app/Models/*`
- `database/migrations/*`
- `resources/js/Pages/*`
- `resources/views/*`
- `tests/Feature/*`, `tests/Unit/*`, `e2e/**/*.spec.ts`

## Coerenza alta (feature dichiarate e implementate)

- Autenticazione completa (login/registrazione/verify/reset) con test Feature + E2E.
- Household (selezione/creazione/inviti/membri) con test Feature + E2E.
- Conti, transazioni, categorie, budget, obiettivi finanziari con UI Inertia/React e test.
- Debiti/crediti e collegamento a transazioni con test dedicati.
- Ricorrenze/spese fisse/rilevamento ricorrenze con service e test.
- Investimenti (asset, analisi, import, prezzi) presenti lato backend/frontend con test Feature/Unit.
- Dashboard personalizzabile (layout widget) con test Feature ed E2E dashboard.
- Magazine pubblico + admin implementato con test Feature.
- Subscription/waitlist/webhook pagamenti presenti.
- Telegram webhook + linking + inbox presenti.
- Rate limiting avanzato e uso `ADV_THROTTLE_SALT` presenti.
- Pipeline CI/CD e deploy Docker presenti (`.github/workflows/deploy.yml`, `docker-compose.prod.yml`).

## Gap reali (manca o non allineato)

### 1) Copertura E2E incompleta su moduli già implementati

Stato:

- E2E presenti per auth/dashboard/household/accounts/transactions/categories/budgets/goals/profile/public.
- E2E non trovati per moduli importanti già presenti:
  - Investimenti
  - Debiti/Crediti
  - Telegram/Inbox
  - Inter-household transfers
  - Magazine admin
  - Subscription/billing

Impatto:

- Regressioni UI/flussi business su moduli core/pro meno intercettate in CI.

### 2) Dominio consensi/privacy GDPR dichiarato ma poco evidente lato modello dati/CRUD

Stato:

- Documentazione dichiara gestione consensi/privacy retention/cancellazione.
- Non emergono migrazioni/entità dedicate a consensi o lifecycle retention esplicito.
- Presenti pagine legali e policy, ma non chiara implementazione applicativa dei consensi granulari.

Impatto:

- Rischio mismatch tra promessa prodotto e comportamento reale.

### 3) Possibile codice orfano: modulo charts

Stato:

- (Storico) Esistevano `app/Http/Controllers/ChartsController.php` e `resources/js/Pages/Charts/Index.tsx`.
- Nessuna route `charts` trovata in `routes/`.

Impatto:

- Codice non raggiungibile, manutenzione inutile, rischio confusione.

Esito remediation (2026-04-28):

- Decisione presa: **opzione B (legacy)**.
- Azione eseguita: rimozione di controller/pagina charts non instradati.
- Stato: gap chiuso.

## Migliorabile (coerente ma da rafforzare)

### 1) Tracciabilità feature matrix

Problema:

- Feature dichiarate distribuite tra `README`, `.cursorrules`, `docs/features`.
- Manca matrice unica "feature -> route/controller/page/test".

Miglioria:

- Aggiungere file singolo (es. `docs/feature-matrix.md`) con stato:
  - dichiarata
  - implementata backend
  - implementata frontend
  - test Unit/Feature
  - test E2E

### 2) Allineamento claim "future" vs "attive"

Problema:

- Alcuni punti in docs sembrano capability attive ma in parte roadmap.

Miglioria:

- Marcare ogni feature con etichetta chiara: `Attiva`, `Parziale`, `Roadmap`.

### 3) Quality gate locale/CI

Stato:

- Migliorato con `make test-ci` (Pint + test).

Miglioria:

- Consigliare in CONTRIBUTING: pre-push obbligatorio `make test-ci` + eventualmente subset E2E smoke.

## Priorità consigliata

P0:

- Aggiungere E2E per moduli ad alto rischio regressione (investimenti, subscription, inter-household transfers).

P1:

- Chiarire e/o implementare modello consensi GDPR (se scope attivo prodotto).
- [Risolto] Modulo charts legacy non instradato rimosso dal codice applicativo.

P2:

- Introdurre feature matrix unica e marcatura stato attiva/roadmap in docs.

## Backlog azionabile (prossimi task)

1. Creare smoke E2E investimenti (index + create + import base).
2. Creare smoke E2E subscription (pagina piano, start checkout mocked, return).
3. Creare smoke E2E inter-household transfer (create + list + dettaglio).
4. Audit GDPR tecnico:
    - definire tabella consensi
    - traccia eventi consenso/revoca
    - policy retention minima documentata
5. Decisione su modulo charts:
    - [completato] scelta legacy: codice morto rimosso.
