
# Finanzamente - Documentazione Progetto

## Descrizione Generale
Finanzamente è una webapp di gestione finanziaria personale e familiare, pensata per utenti residenti in Italia tra i 18 e i 45 anni. L’applicazione è mobile first, full responsive, in lingua italiana, e offre strumenti avanzati per la gestione di finanze, investimenti, budgeting, household multipli e privacy.

## Aree Funzionali

### 1. Gestione Household/Famiglia
- Creazione e gestione di household (famiglie, gruppi, team)
- Invito e gestione membri con ruoli e permessi granulari (owner, member, guest, supervise, private)
- Supervisione familiare: il genitore può vedere e monitorare le finanze del figlio, che mantiene aree private

### 2. Conti e Transazioni
- Gestione di conti bancari, carte, contanti, wallet crypto
- Transazioni categorizzate, ricorrenti, taggate, con allegati multipli
- Possibilità di rendere conti e transazioni privati o condivisi

### 3. Investimenti
- Supporto per crypto, azioni, ETF, indici, materie prime, assicurazioni
- Gestione di acquisti, vendite, movimenti, allegati e privacy

### 4. Budgeting e Obiettivi Finanziari
- Definizione di budget per categorie, household e periodo
- Monitoraggio avanzato e alert
- Gestione obiettivi finanziari personali e di gruppo

### 5. Debiti e Crediti
- Gestione di prestiti, debiti e crediti tra utenti, household o con terzi
- Tracciamento stato, scadenze, pagamenti e notifiche

### 6. Notifiche e Log
- Sistema di notifiche per utenti e household
- Log accessi, modifiche e audit per sicurezza e trasparenza

### 7. Tagging e Allegati
- Associazione di tag e allegati multipli a transazioni, investimenti, documenti

### 8. Privacy e Supervisione
- Policy granulari per la visibilità dei dati
- Flag privacy su ogni entità (conti, transazioni, investimenti)
- Audit log delle azioni di supervisione

### 9. Spese Condivise
- Gestione di spese condivise tra più utenti
- Suddivisione automatica e tracciamento pagamenti

### 10. Consensi e GDPR
- Gestione consensi privacy, policy di data retention e cancellazione

## Flussi Utente Principali
- Registrazione e validazione email
- Creazione/adesione a household
- Gestione conti, transazioni, investimenti
- Impostazione privacy e permessi
- Supervisione familiare e audit
- Gestione notifiche e consensi

## Struttura delle Entità Principali
- users, households, household_user, accounts, categories, transactions, investments, budgets, debts_credits, financial_goals, notifications, tags, attachments, access_logs, investment_assets

## Architettura Tecnologica (vedi copilot-instructions.md per dettagli tecnici)
- Backend: Laravel (PHP), API RESTful, sicurezza, migrazioni, validazione
- Frontend autenticato: React + Inertia.js, TypeScript
- Frontend pubblico/SSR: Blade
- Database: MySQL, tabelle in inglese, espandibile

## Accessibilità e Best Practice
- Mobile first, UI/UX reattiva e accessibile (WCAG 2.1)
- Codice DRY/KISS, componenti riutilizzabili, linting e formattazione
- Testing automatico, CI/CD, documentazione aggiornata
- Gestione privacy, consensi, GDPR, backup e disaster recovery
- Logging, monitoraggio, analytics privacy-friendly

## Aggiornamento e Estendibilità
- La documentazione va aggiornata ad ogni evoluzione architetturale o funzionale
- Il sistema è progettato per supportare nuove funzionalità (reportistica, automazioni, integrazioni, ecc.)

---

> Per linee guida tecniche, convenzioni di sviluppo e best practice, consultare il file `.github/copilot-instructions.md`.