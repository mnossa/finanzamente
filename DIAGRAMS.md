# Diagramma delle Entità Principali

```mermaid
erDiagram
    USERS ||--o{ HOUSEHOLDS : "membri"
    HOUSEHOLDS ||--o{ HOUSEHOLD_USER : "associazione"
    USERS ||--o{ HOUSEHOLD_USER : "ruoli e permessi"
    HOUSEHOLDS ||--o{ ACCOUNTS : "conti"
    ACCOUNTS ||--o{ TRANSACTIONS : "transazioni"
    TRANSACTIONS ||--o{ TAGS : "tag"
    TRANSACTIONS ||--o{ ATTACHMENTS : "allegati"
    HOUSEHOLDS ||--o{ CATEGORIES : "categorie"
    USERS ||--o{ TRANSACTIONS : "autore"
    HOUSEHOLDS ||--o{ INVESTMENTS : "investimenti"
    INVESTMENTS ||--o{ INVESTMENT_ASSETS : "asset"
    INVESTMENTS ||--o{ ATTACHMENTS : "allegati"
    HOUSEHOLDS ||--o{ BUDGETS : "budget"
    HOUSEHOLDS ||--o{ DEBTS_CREDITS : "debiti/crediti"
    HOUSEHOLDS ||--o{ FINANCIAL_GOALS : "obiettivi"
    USERS ||--o{ NOTIFICATIONS : "notifiche"
    USERS ||--o{ ACCESS_LOGS : "log accessi"
```

---

# Diagramma Flusso Utente (Registrazione e Gestione Household)

```mermaid
flowchart TD
    A[Registrazione Utente] --> B[Validazione Email]
    B --> C[Creazione Household]
    C --> D[Invito Membri]
    D --> E[Gestione Permessi]
    E --> F[Gestione Conti e Transazioni]
    F --> G[Gestione Investimenti]
    F --> H[Gestione Budget]
    F --> I[Gestione Debiti/Crediti]
    F --> J[Gestione Obiettivi]
    F --> K[Gestione Tag e Allegati]
    F --> L[Gestione Privacy]
    L --> M[Supervisione Familiare]
```

---

> I diagrammi sono generati con sintassi Mermaid e possono essere visualizzati direttamente su GitHub o tramite plugin VS Code.
