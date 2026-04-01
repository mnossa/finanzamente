# Piano Base vs Pro — Finanzamente

> **Principio guida:** il piano Base deve essere abbastanza ricco da trattenere utenti attivi a lungo termine; il piano Pro deve essere abbastanza specifico e potente da giustificare il costo mensile per chi ha quei bisogni. La conversione ideale avviene quando l'utente sente un limite naturale — non una frustrazione artificiale.
>
> Contesto: 1 sviluppatore, nessun supporto diretto, FAQ come unico canale di aiuto, target utenti italiani 18–45 anni.

---

## 1. Suddivisione attuale dei piani

### Piano Base (gratuito)

| Funzionalità | Limite |
|---|---|
| Dashboard e panoramica finanziaria | Illimitata |
| Conti bancari | **Max 5** |
| Transazioni | Illimitate |
| Categorie personalizzate | Illimitate |
| Tag per transazioni | Illimitati |
| Trasferimenti tra conti | Stessa household |
| Budget mensile | Per categoria |
| Import CSV/XLS con layout salvati | Illimitato |
| Transazioni ricorrenti | **Max 5 attive** |
| Rimborsi | **Max 10 attivi** |
| Debiti e crediti | **Max 5 attivi** |
| Obiettivi finanziari | **Max 1 attivo** |
| Household | 1 sola, nessun membro invitabile |

### Piano Pro (2,99 €/mese — sconto 20% annuale)

| Funzionalità | Note |
|---|---|
| Tutto il Base, senza limiti quantitativi | |
| Conti bancari illimitati | |
| Household illimitate con membri | Inviti, ruoli, permessi |
| Trasferimenti tra household | Cross-household |
| Transazioni ricorrenti illimitate | |
| Rimborsi illimitati | |
| Debiti e crediti illimitati | |
| Obiettivi finanziari illimitati | |
| Investimenti + portafoglio | Richiede `tracks_investments` |
| Asset Allocation | |
| Asset gestiti manualmente | Inserimento ISIN/ticker |
| Analisi investimenti | Dashboard avanzata |
| Simulazioni finanziarie | Proiezioni scenari |
| Integrazione Telegram + Inbox | |
| Detrazioni fiscali / 730 | Solo se detrazioni presenti |
| Gestione IVA | Solo Partita IVA |
| Lifestyle Inflation Score | |
| Export PDF e XLS avanzati | *(prossimamente)* |

---

## 2. Perché questa suddivisione funziona

### Il piano Base è davvero usabile

Un utente Base può tenere traccia di **tutte le entrate e uscite**, categorizzare liberamente, importare dal CSV della propria banca e seguire budget mensili. Le 5 ricorrenti coprono affitto, utenze, abbonamenti principali. I 10 rimborsi e 5 debiti/crediti coprono la gestione quotidiana di spese condivise. Il limite di 5 conti copre il 90%+ degli utenti 18–45 anni.

**Risultato:** l'utente può fare finanza personale seria gratuitamente — questo genera fiducia, adozione e passaparola.

### Il Pro ha massa critica

Il Pro offre una suite completa: limiti illimitati su tutte le aree Base, più funzionalità esclusive per segmenti specifici (investitori, freelance, famiglie, tech-savvy). Chi usa già il Base e ha bisogno di più trova nel Pro una risposta diretta e completa.

### Limiti soft anziché hard paywall

I limiti Base non bloccano l'uso quotidiano — si attivano solo quando l'utente cresce. Questo è il momento naturale per la conversione: il bisogno è reale, non artificiale.

---

## 3. Funzionalità esclusivamente Pro

Queste non andrebbero mai spostate nel Base — il loro valore è specifico e spinge alla conversione chi ne ha davvero bisogno:

| Funzionalità | Perché rimane Pro | Target |
|---|---|---|
| Investimenti + Asset Allocation | Complessità alta, nicchia ad alto reddito | Investitore |
| Analisi investimenti | Dati storici e mercato | Investitore |
| Simulazioni finanziarie | Feature "wow", differenziante | Pianificatore avanzato |
| Household multi-membro + inviti | Uso familiare/coppia | Famiglia / coppia |
| Trasferimenti inter-household | Solo per chi ha più household | Famiglia estesa |
| Telegram + Inbox | Integrazione tecnica avanzata | Tech-savvy |
| Detrazioni fiscali / 730 | Valore alto, giustifica costo da sola | Lavoratore dipendente |
| Gestione IVA | Partita IVA only, altissimo valore specifico | Freelance / P.IVA |
| Lifestyle Inflation Score | Feature originale, difficile da copiare | Orientati alla crescita |
| Ricorrenti illimitate | Salto naturale dal limite di 5 | Chi ha molti abbonamenti |
| Obiettivi illimitati | Salto naturale da 1 Base | Pianificatore attivo |
| Rimborsi illimitati | Salto naturale dal limite di 10 | Chi gestisce team/gruppo |

---

## 4. La psicologia del momento upgrade

L'app mostra le voci Pro nel menu con un badge dorato e redirige all'upgrade. L'utente **sa cosa esiste** e può desiderarlo — il badge crea aspirazione, non frustrazione.

**Momento 1 — Limite Base raggiunto**
Quando l'utente tenta di superare un limite, appare un banner contestuale nella pagina di creazione. Implementato in: `Accounts/Create.tsx`, `RecurringTransactions/Create.tsx`, `Refunds/Create.tsx`, `DebtsCredits/Create.tsx`, `FinancialGoals/Create.tsx`.

**Momento 2 — Voce Pro cliccata nel menu**
L'utente viene rediretto a `/profile/subscription?from=<moduleId>`. La pagina mostra un banner contestuale amber con il nome e la descrizione della feature specifica che cercava.

Moduli con redirect contestuale: `simulations`, `inter_household_transfers`, `inbox`, `tax_refund_730`, `investments`, `asset_allocation`, `investment_assets`, `investment_analyses`, `lifestyle_score`.

---

## 5. Anti-pattern da evitare

| Anti-pattern | Perché è sbagliato | Soluzione applicata |
|---|---|---|
| Nascondere le voci Pro nel menu | L'utente non sa cosa esiste | Visibili con badge dorato ✅ |
| Paywall senza contesto | "Upgrade" senza spiegare perché → abbandono | Banner contestuale `?from=` ✅ |
| Troppi limiti artificiali | L'utente si sente manipolato | Limiti dove il bisogno di più è genuinamente avanzato ✅ |
| Promettere feature non implementate | Crea delusione e churn immediato | Export etichettato "prossimamente" ✅ |

---

## 6. Prezzo e posizionamento competitivo

| App | Free tier | Pro/Plus |
|---|---|---|
| **Finanzamente** | Base con limiti morbidi | **2,99 €/mese** |
| Toshl Finance | Limitato (3 conti) | 2,99 €/mese |
| Spendee | Limitato | 2,99 €/mese |
| Wallet by BudgetBakers | Limitato | 2,99 €/mese |
| YNAB | No free | 14,99 $/mese |

2,99 €/mese allinea Finanzamente ai competitor di nicchia. La differenziazione si gioca su:
- UX pensata per l'utente italiano
- Feature verticali (IVA, 730, import bancari italiani, Telegram)
- La storia dell'upgrade: contesto e bisogno reale, non lista di feature

---

## 7. Rischi operativi aperti

| Rischio | Urgenza | Azione |
|---|---|---|
| FAQ assenti per Telegram (setup non banale) | Alta | Tutorial con screenshot e comandi esatti |
| FAQ assenti per IVA (errori hanno impatto reale) | Alta | Guida con esempi pratici italiani |
| Template CSV bancari assenti | Media | Precompilati per Intesa, Fineco, UniCredit, N26, BancaSella |
| Export PDF/XLS non sviluppato | Media | Etichettato "prossimamente" — sviluppare quando possibile |

Con 2,99 €/mese ogni utente Pro vale ~119 €/anno. Un utente che non riesce ad usare una feature e non trova risposta in FAQ cancella entro 30 giorni.

---

## 8. Decisioni chiave

| Decisione | Stato |
|---|---|
| Limite conti Base: **5** (era 3) | ✅ |
| Ricorrenti nel Base (max 5) | ✅ |
| Rimborsi nel Base (max 10) | ✅ |
| Debiti/Crediti nel Base (max 5) | ✅ |
| 1 Obiettivo nel Base | ✅ |
| Export etichettato "prossimamente" | ✅ |
| Prezzo Pro: **2,99 €/mese** (era 9,90 €) | ✅ |
| Upgrade contestuale con `?from=moduleId` | ✅ |
| **2 piani soltanto (Base + Pro)** | ✅ decisione definitiva |
| FAQ Telegram / IVA / CSV bancari | 🔴 da fare |

---

*Ultimo aggiornamento: 02/04/2026*
