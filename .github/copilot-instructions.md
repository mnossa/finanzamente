# copilot-instructions.md
# Ambiente di Sviluppo
Tutto lo sviluppo e il deploy avverranno tramite Docker, per garantire coerenza tra ambienti, facilità di setup e portabilità. Il progetto includerà file di configurazione Docker per tutti i servizi necessari (PHP, MySQL, Node, ecc.).

**Web server consigliato:** Nginx è preferibile rispetto ad Apache per progetti Laravel moderni, grazie a migliori performance, semplicità di configurazione e minore consumo di risorse. Tuttavia, Apache può essere usato se richiesto da specifiche esigenze o compatibilità. La configurazione di default sarà con Nginx.

## Obiettivo del Progetto
Webapp di gestione finanziaria personale, rivolta a utenti residenti in Italia tra i 18 e i 45 anni. L'applicazione deve essere full responsive, mobile first, in lingua italiana, con un database MySQL ottimizzato e facilmente scalabile.

## Linee Guida Generali
- **Mobile First**: Progettare e sviluppare prima per dispositivi mobili, poi adattare a desktop.
- **Lingua**: Tutte le interfacce, messaggi e documentazione devono essere in italiano.
- **UI/UX**: Pagine reattive, fluide, con animazioni leggere e colori che incentivino un uso consapevole e positivo dell'app.
- **Codice**: Evitare duplicazioni e ridondanze. Utilizzare componenti riutilizzabili e seguire le best practice di sviluppo.
- **Performance**: Ottimizzare caricamento e rendering delle pagine. Minimizzare le query al database e utilizzare lazy loading dove possibile.

## Database
**MySQL**: Strutturare il database per essere scalabile e performante.
**Espandibilità**: Progettare tabelle e relazioni in modo da permettere future implementazioni senza modifiche invasive.
**Nomenclatura**: Usare nomi chiari, in inglese e in minuscolo, separati da underscore. La UI e i contenuti rivolti all’utente restano in italiano, ma la struttura dati (tabelle, colonne, API) deve essere in inglese per favorire interoperabilità e standardizzazione.
**Migrazioni**: Le tabelle verranno create tramite le migration di Laravel, seguendo le best practice del framework.

## Frontend
- **Responsività**: Utilizzare framework CSS (es. Tailwind, Bootstrap) o soluzioni custom per garantire responsività.
- **Componenti**: Sviluppare componenti riutilizzabili per UI comuni (bottoni, card, modali, ecc.).
- **Animazioni**: Implementare animazioni leggere e non invasive.
- **Accessibilità**: Seguire le linee guida WCAG 2.1 per garantire accessibilità a tutti gli utenti.
- **DOM validation**: Assicurarsi che il codice HTML generato sia valido e conforme agli standard W3C.

## Backend
- L’intero backend sarà sviluppato in Laravel, adottando le best practice e gli helpers forniti dal framework.
- **Rotte Web e Controller**: La dashboard autenticata e le funzionalità principali useranno rotte web e controller Laravel tradizionali, sfruttando Blade e Inertia.js per la presentazione. L'autenticazione sarà gestita tramite le sessioni Laravel.
- **API RESTful**: Da utilizzare solo se necessario per integrazioni esterne o funzionalità future. Per ora, evitare la creazione di API RESTful dedicate.
- **Sicurezza**: Gestire autenticazione, autorizzazione e validazione input tramite i meccanismi standard di Laravel (sessioni, middleware, policy).
- **Ottimizzazione**: Scrivere query efficienti e utilizzare Eloquent ORM e gli strumenti Laravel dove opportuno.

## Best Practice
- **Documentazione**: Commentare il codice dove necessario e mantenere aggiornata la documentazione.
- **Testing**: Implementare test automatici per le funzionalità principali.
- **CI/CD**: Integrare pipeline di build, test e deploy.

## Estendibilità
- Progettare il sistema per facilitare l'aggiunta di nuove funzionalità (es. reportistica avanzata, notifiche, integrazione con servizi esterni, gestione di household multipli, supporto multi-currency, budgeting, gestione debiti/crediti, obiettivi finanziari, tagging, allegati multipli, ecc.).
- Ogni utente deve poter selezionare quale household sia attiva in ogni momento.
- Al momento della registrazione, l’email dell’utente deve essere validata; di default, ogni nuova email sarà non validata.

## Specifiche Avanzate
- **Accessibilità**: Garantire la conformità alle linee guida WCAG 2.1, con attenzione a contrasto, navigazione da tastiera e supporto screen reader.
- **Logging e Monitoraggio**: Implementare logging centralizzato e strumenti di monitoraggio sia per backend che frontend.
- **Privacy e GDPR**: Gestire i dati personali secondo il GDPR, con informative, consensi e gestione trasparente dei dati utente.
- **Backup e Disaster Recovery**: Definire strategie di backup automatico e procedure di ripristino dei dati.
- **Rate Limiting e Protezione API**: Applicare limiti di richiesta e protezioni contro abusi e attacchi sulle API.
- **Modularità**: Organizzare il codice in moduli e servizi separati per facilitare manutenzione ed estendibilità.
- **DevOps**: Documentare procedure di deploy, rollback e gestione degli ambienti (dev, staging, produzione).
- **Analisi e Metriche**: Integrare strumenti di analytics per tracciare l’uso dell’applicazione in modo privacy-friendly.
- **Supporto PWA**: Valutare la trasformazione in Progressive Web App per migliorare l’esperienza mobile e l’accessibilità offline.

## Ottimizzazione per Agenti e Qualità del Codice
- **Revisione del Codice**: Ogni modifica rilevante deve essere sottoposta a code review, anche automatizzata, per individuare errori e migliorare la qualità.
- **Linting e Formattazione**: Utilizzare strumenti di linting e formatter (es. ESLint, Prettier, PHP_CodeSniffer) per mantenere uno stile di codice uniforme e prevenire errori comuni.
- **Principi DRY e KISS**: Applicare i principi DRY (Don't Repeat Yourself) e KISS (Keep It Simple, Stupid) per ridurre la complessità e la ridondanza.
- **Gestione Errori**: Implementare una gestione centralizzata e consistente degli errori sia lato frontend che backend.
- **Esempi di Naming**: Fornire esempi di nomenclatura per variabili, funzioni, tabelle e componenti per favorire la coerenza (es: `utente_id`, `transazione_annuale`, `getSaldoAttuale`).
- **Checklist di Qualità**: Prima di ogni merge, verificare: test superati, assenza di duplicazioni, performance accettabili, documentazione aggiornata, sicurezza rispettata.
- **React & Tailwind**: Nei componenti React, utilizzare esclusivamente TailwindCSS per lo styling. Per la dashboard e i componenti riutilizzabili (tabelle, bottoni, header, form, select, datepicker, ecc.), adottare la libreria `clsx` per gestire classi condizionali e varianti in modo pulito e scalabile. Ogni componente deve accettare la prop `className` per permettere override e personalizzazioni.
- **Componenti e Librerie**: Preferire l’uso di componenti e librerie consolidate e ben documentate rispetto a soluzioni custom, salvo necessità specifiche.
- **Commenti e TODO**: Usare commenti chiari e TODO solo dove strettamente necessario, evitando di lasciare codice morto o non utilizzato.

> Seguire queste istruzioni per mantenere coerenza, qualità e scalabilità nel progetto. Aggiornare questo file in caso di modifiche rilevanti all'architettura o alle linee guida.

## Stack Tecnologico Consigliato
- **Backend**: Laravel per autenticazione, sicurezza, rotte web e controller tradizionali, migrazioni, validazione, gestione utenti/household e logiche di business. Utilizzare Eloquent ORM, Service Layer, Policy, Request Validation e tutte le best practice del framework. Le API RESTful sono da implementare solo se richieste da future estensioni o integrazioni esterne.
- **Frontend pubblico/SSR**: Blade per pagine pubbliche, SEO, SSR e caricamento veloce, con possibilità di integrare componenti React dove necessario.

## Best Practice di Architettura
- Separare chiaramente la parte pubblica (Blade/SSR) da quella autenticata (React/Inertia).
- Gestire la documentazione e i test per entrambi i layer (backend e frontend).
- Adottare una struttura modulare e scalabile, favorendo la riusabilità dei componenti.
- Seguire le best practice di Laravel e React/TypeScript per sicurezza, performance e manutenibilità.
- Integrare strumenti di linting, formatter e CI/CD per entrambi gli stack.
- Documentare le scelte architetturali e tecnologiche nel repository.

## Linee Guida per Frontend Open (Blade)
- Utilizzare Blade esclusivamente per la parte pubblica/non autenticata e per le pagine SSR.
- Adottare convenzioni di naming chiare e coerenti per i file Blade (es. kebab-case, suffisso .blade.php).
- Utilizzare layout Blade per la struttura base e componenti Blade per elementi riutilizzabili (header, footer, alert, ecc.).
- Applicare TailwindCSS anche nelle viste Blade per garantire coerenza visiva con la dashboard React.
- Separare la logica dalla presentazione: evitare PHP diretto nelle viste, delegare la logica ai controller/view model.
- Garantire accessibilità (WCAG 2.1), SEO e performance nelle pagine pubbliche.
- Gestire le traduzioni e i messaggi utente tramite il sistema di localizzazione Laravel.
- Commentare il codice Blade solo dove strettamente necessario e mantenere la struttura pulita.
- Evitare duplicazioni e favorire la riusabilità dei componenti Blade.

## Linee Guida per Frontend Autenticato (React/Inertia)
- Utilizzare React con Inertia.js esclusivamente per la parte autenticata della webapp.
- Adottare TypeScript per robustezza, tipizzazione e manutenibilità.
- Utilizzare TailwindCSS come unico sistema di styling per tutti i componenti.
- Gestire le classi condizionali e varianti tramite la libreria `clsx`.
- Ogni componente deve accettare la prop `className` per permettere override e personalizzazioni.
- Sviluppare componenti riutilizzabili per UI comuni (tabelle, bottoni, header, form, select, datepicker, ecc.).
- Separare la logica di business dalla presentazione, utilizzando hooks e service layer.
- Gestire lo stato globale con strumenti come React Context o librerie dedicate (es. Zustand, Redux solo se necessario).
- Garantire accessibilità (WCAG 2.1) e performance anche nella dashboard.
- Documentare i componenti e mantenere una struttura modulare e scalabile.
- Integrare test automatici (unitari e di integrazione) per i componenti principali.

## Localizzazione e Lingua
- **Lingua dell'interfaccia**: Tutti i testi visibili all'utente (label, bottoni, messaggi, titoli, placeholder, notifiche, email) devono essere in **italiano**.
- **Codice e struttura dati**: Nomi di variabili, funzioni, tabelle, colonne, rotte e API devono essere in **inglese** per favorire interoperabilità e standard internazionali.
- **Messaggi di errore e validazione**: Configurare Laravel per restituire messaggi di validazione in italiano (file `lang/it/validation.php`).
- **Date e numeri**: Formattare date, orari e numeri secondo le convenzioni italiane (es. `dd/mm/yyyy`, separatore decimale `,`, separatore migliaia `.`).
- **Valuta**: Usare Euro (€) come valuta predefinita, con possibilità di supporto multi-currency in futuro.
- **Nessun sistema i18n complesso**: Non è previsto supporto multilingua. L'app sarà esclusivamente in italiano.

## Convenzioni di Nomenclatura
- **Variabili e Funzioni**: Utilizzare `camelCase` per nomi di variabili e funzioni (es. `userName`, `getTransactionList`).
- **Classi e Componenti**: Utilizzare `PascalCase` per nomi di classi e componenti (es. `UserProfile`, `TransactionTable`).
- **Tabelle e Colonne**: Utilizzare `snake_case` in minuscolo per nomi di tabelle e colonne nel database (es. `user_profiles`, `transaction_date`).
- **Rotte**: Utilizzare `kebab-case` per nomi di rotte web e API (es. `/user-profile`, `/transaction-list`).
- **File e Cartelle**: Utilizzare `kebab-case` per nomi di file e cartelle (es. `user-profile.js`, `transaction-list.css`).
- **Prefissi e Suffissi**: Usare prefissi/suffissi chiari per indicare il tipo o lo scopo (es. `is` per booleani: `isActive`, `get` per funzioni che restituiscono valori: `getUserData`).
- **Acronimi**: Scrivere gli acronimi in maiuscolo (es. `APIClient`, `HTMLParser`).
- **Esempi**:
  - Variabile: `totalAmount`
  - Funzione: `calculateMonthlyBudget()`
  - Classe: `FinancialReport`
  - Tabella: `financial_reports`
  - Colonna: `report_date`
  - Rotta: `/financial-report`
  - File: `financial-report.component.tsx`

## Funzionalità Future da Considerare
- Usa i metodi isDebtBalancingMode() e isSharedWalletMode() nelle future funzionalità di calcolo del saldo, per gestire modalità specifiche come il bilanciamento debiti e portafogli condivisi.