# Struttura Database - Finanzamente

Di seguito la struttura delle principali tabelle del database, con descrizione di ogni campo (descrizioni in italiano).

---

## users
| Campo                | Tipo         | Descrizione                                                      |
|----------------------|-------------|------------------------------------------------------------------|
| id                   | BIGINT PK   | Identificativo univoco utente                                    |
| active_household_id  | BIGINT FK   | Household attualmente selezionata dall’utente                    |
| first_name           | VARCHAR     | Nome                                                             |
| last_name            | VARCHAR     | Cognome                                                          |
| email                | VARCHAR     | Email (univoca)                                                  |
| email_verified_at    | TIMESTAMP   | Data/ora di validazione email (null se non validata)             |
| password             | VARCHAR     | Hash della password                                              |
| birth_date           | DATE        | Data di nascita                                                  |
| status               | ENUM        | Stato utente (active, suspended, deleted)                        |
| preferences          | JSON        | Preferenze utente (tema, notifiche, layout, ecc)                 |
| created_at/updated_at| TIMESTAMP   | Timestamps Laravel                                               |
| deleted_at           | TIMESTAMP   | Soft delete (opzionale)                                          |

## households
| Campo         | Tipo      | Descrizione                                 |
|--------------|------------|---------------------------------------------|
| id           | BIGINT PK  | Identificativo univoco household            |
| name         | VARCHAR    | Nome della household                        |
| owner_user_id| BIGINT FK  | Utente proprietario                         |
| created_at/updated_at| TIMESTAMP | Timestamps Laravel                    |

## household_user
| Campo        | Tipo       | Descrizione                                 |
|-------------|------------|---------------------------------------------|
| household_id| BIGINT FK  | Household di appartenenza                   |
| user_id     | BIGINT FK  | Utente associato                            |
| role        | ENUM       | Ruolo (owner, member, guest)                |
| permissions | JSON/ENUM  | Permessi specifici (view_only, manage, supervise, private, ecc.) |
| created_at/updated_at| TIMESTAMP | Timestamps Laravel                    |
| deleted_at  | TIMESTAMP  | Soft delete (opzionale)                     |
| (PK: household_id + user_id) |   | Chiave primaria composta                |

## accounts
| Campo         | Tipo         | Descrizione                                 |
|--------------|--------------|---------------------------------------------|
| id           | BIGINT PK    | Identificativo univoco account              |
| household_id | BIGINT FK    | Household di appartenenza                   |
| name         | VARCHAR      | Nome account                                |
| type         | ENUM         | Tipo (bank, cash, card, ecc)                |
| initial_balance| DECIMAL(12,2)| Saldo iniziale                            |
| currency_code| VARCHAR FK   | Codice valuta (EUR, USD, ecc)               |
| active       | BOOLEAN      | Account attivo o meno                       |
| is_private   | BOOLEAN      | Se true, solo il proprietario vede i dettagli, altri membri vedono solo il totale o una voce generica |
| created_at/updated_at| TIMESTAMP | Timestamps Laravel                    |
| deleted_at   | TIMESTAMP    | Soft delete (opzionale)                     |

## currencies
| Campo      | Tipo      | Descrizione                                  |
|------------|-----------|----------------------------------------------|
| code       | VARCHAR PK| Codice valuta (EUR, USD, ecc)                |
| name       | VARCHAR   | Nome valuta                                  |
| symbol     | VARCHAR   | Simbolo valuta                               |

## categories
| Campo         | Tipo         | Descrizione                                 |
|--------------|--------------|---------------------------------------------|
| id           | BIGINT PK    | Identificativo univoco categoria            |
| household_id | BIGINT FK    | Household di appartenenza (null per globali)|
| name         | VARCHAR      | Nome categoria                              |
| type         | ENUM         | Tipo (income/expense)                       |
| color        | VARCHAR      | Colore associato                            |
| icon         | VARCHAR      | Icona associata                             |
| created_at/updated_at| TIMESTAMP | Timestamps Laravel                    |
| deleted_at   | TIMESTAMP    | Soft delete (opzionale)                     |

## transactions
| Campo         | Tipo         | Descrizione                                 |
|--------------|--------------|---------------------------------------------|
| id           | BIGINT PK    | Identificativo univoco transazione          |
| user_id      | BIGINT FK    | Utente che ha creato la transazione         |
| account_id   | BIGINT FK    | Account associato                           |
| category_id  | BIGINT FK    | Categoria associata                         |
| amount       | DECIMAL(12,2)| Importo                                     |
| currency_code| VARCHAR FK   | Codice valuta                               |
| date         | DATE         | Data della transazione                      |
| description  | TEXT         | Descrizione                                 |
| recurring    | BOOLEAN      | Se la transazione è ricorrente              |
| recurring_transaction_id | BIGINT FK nullable | Collegamento a ricorrenza (se presente) |
| is_private   | BOOLEAN      | Se true, solo il proprietario vede i dettagli, altri membri vedono solo il totale o una voce generica |
| created_at/updated_at| TIMESTAMP | Timestamps Laravel                    |
| deleted_at   | TIMESTAMP    | Soft delete (opzionale)                     |
| transfer_id  | BIGINT FK    | Collegamento opzionale a `transfers` (se la transazione fa parte di un transfer) |
| refund_id    | BIGINT FK    | Collegamento opzionale a `refunds` (se la transazione è un rimborso) |

## transfers
| Campo               | Tipo           | Descrizione                                                                 |
|---------------------|----------------|-----------------------------------------------------------------------------|
| id                  | BIGINT PK      | Identificativo univoco transfer                                              |
| uuid                | VARCHAR UNIQUE | UUID pubblico per idempotency / riferimento esterno                          |
| source_account_id   | BIGINT FK      | Account sorgente del trasferimento                                           |
| destination_account_id | BIGINT FK   | Account destinazione del trasferimento                                       |
| source_amount       | DECIMAL(18,8)  | Importo in valuta sorgente (absolute)                                        |
| source_currency     | VARCHAR FK     | Codice valuta sorgente (es. EUR)                                             |
| dest_amount         | DECIMAL(18,8)  | Importo in valuta destinazione (absolute)                                    |
| dest_currency       | VARCHAR FK     | Codice valuta destinazione (es. USD)                                         |
| exchange_rate       | DECIMAL(28,12) | Tasso di cambio usato (nullable)                                             |
| fee                 | DECIMAL(18,8)  | Commissione applicata (nullable)                                             |
| user_id             | BIGINT FK      | Utente che ha iniziato/possiede il trasferimento (nullable)                 |
| status              | ENUM           | Stato (completed, cancelled) — per tracking; default completed               |
| created_at/updated_at| TIMESTAMP     | Timestamps Laravel                                                           |
| deleted_at          | TIMESTAMP      | Soft delete (opzionale)                                                      |

## refunds
| Campo                    | Tipo           | Descrizione                                                              |
|--------------------------|----------------|--------------------------------------------------------------------------|
| id                       | BIGINT PK      | Identificativo univoco rimborso                                          |
| uuid                     | VARCHAR UNIQUE | UUID pubblico per riferimento esterno                                    |
| original_transaction_id  | BIGINT FK      | Transazione originale di spesa che viene rimborsata                      |
| user_id                  | BIGINT FK      | Utente che ha creato il rimborso (nullable)                              |
| amount                   | DECIMAL(18,8)  | Importo del rimborso (valore assoluto)                                   |
| currency_code            | VARCHAR FK     | Codice valuta del rimborso                                               |
| status                   | ENUM           | Stato (pending, completed, cancelled) — default completed                |
| description              | TEXT           | Descrizione del rimborso (nullable)                                      |
| created_at/updated_at    | TIMESTAMP      | Timestamps Laravel                                                       |
| deleted_at               | TIMESTAMP      | Soft delete (opzionale)                                                  |

> **Nota**: Un rimborso collega una transazione di spesa (original_transaction_id) a una nuova transazione di entrata (refund_id in transactions). Questo permette di tracciare quanto è stato rimborsato per ogni spesa e mantenere un bilancio accurato.

## inter_household_transfers
| Campo                    | Tipo           | Descrizione                                                              |
|--------------------------|----------------|--------------------------------------------------------------------------|
| id                       | BIGINT PK      | Identificativo univoco trasferimento inter-household                     |
| uuid                     | VARCHAR UNIQUE | UUID pubblico per riferimento esterno                                    |
| source_household_id      | BIGINT FK      | Household sorgente (chi invia)                                           |
| source_account_id        | BIGINT FK      | Account sorgente del trasferimento                                       |
| source_user_id           | BIGINT FK      | Utente che ha creato il trasferimento                                    |
| dest_household_id        | BIGINT FK      | Household destinataria (chi riceve)                                      |
| dest_account_id          | BIGINT FK      | Account destinazione del trasferimento                                   |
| dest_user_id             | BIGINT FK      | Utente destinatario (nullable)                                           |
| source_amount            | DECIMAL(18,8)  | Importo in valuta sorgente                                               |
| source_currency          | VARCHAR        | Codice valuta sorgente                                                   |
| dest_amount              | DECIMAL(18,8)  | Importo in valuta destinazione                                           |
| dest_currency            | VARCHAR        | Codice valuta destinazione                                               |
| exchange_rate            | DECIMAL(28,12) | Tasso di cambio usato (nullable)                                         |
| fee                      | DECIMAL(18,8)  | Commissione applicata (nullable)                                         |
| description              | TEXT           | Descrizione del trasferimento (nullable)                                 |
| notes                    | TEXT           | Note aggiuntive (nullable)                                               |
| transfer_date            | DATE           | Data del trasferimento                                                   |
| status                   | ENUM           | Stato (pending, approved, rejected, cancelled, completed)                |
| source_transaction_id    | BIGINT FK      | Transazione di uscita creata (nullable, solo se approved)                |
| dest_transaction_id      | BIGINT FK      | Transazione di entrata creata (nullable, solo se approved)               |
| approved_at              | TIMESTAMP      | Data/ora approvazione (nullable)                                         |
| approved_by              | BIGINT FK      | Utente che ha approvato (nullable)                                       |
| rejected_at              | TIMESTAMP      | Data/ora rifiuto (nullable)                                              |
| rejected_by              | BIGINT FK      | Utente che ha rifiutato (nullable)                                       |
| rejection_reason         | TEXT           | Motivo del rifiuto (nullable)                                            |
| created_at/updated_at    | TIMESTAMP      | Timestamps Laravel                                                       |
| deleted_at               | TIMESTAMP      | Soft delete (opzionale)                                                  |

> **Nota**: I trasferimenti inter-household consentono di trasferire fondi tra account appartenenti a households diverse. Richiedono approvazione dalla household destinataria. Solo quando approvati vengono create le transazioni collegate (source_transaction_id e dest_transaction_id). Lo stato 'pending' indica che è in attesa di approvazione, 'approved'/'completed' che è stato approvato e le transazioni sono state create, 'rejected' che è stato rifiutato, 'cancelled' che è stato annullato dalla household sorgente.

## recurring_transactions
| Campo         | Tipo         | Descrizione                                 |
|--------------|--------------|---------------------------------------------|
| id           | BIGINT PK    | Identificativo univoco                      |
| user_id      | BIGINT FK    | Utente associato                            |
| category_id  | BIGINT FK    | Categoria associata                         |
| account_id   | BIGINT FK    | Account associato                           |
| amount       | DECIMAL(12,2)| Importo                                     |
| currency_code| VARCHAR FK   | Codice valuta                               |
| frequency    | ENUM         | Frequenza (monthly, weekly, ecc)            |
| start_date   | DATE         | Data inizio                                 |
| end_date     | DATE         | Data fine (null se indefinita)              |
| description  | TEXT         | Descrizione                                 |
| created_at/updated_at| TIMESTAMP | Timestamps Laravel                    |
| deleted_at   | TIMESTAMP    | Soft delete (opzionale)                     |

## attachments
| Campo      | Tipo       | Descrizione                                  |
|------------|------------|----------------------------------------------|
| id         | BIGINT PK  | Identificativo univoco allegato              |
| file_path  | VARCHAR    | Percorso file                                |
| uploaded_at| TIMESTAMP  | Data upload                                  |
| uploaded_by| BIGINT FK  | Utente che ha caricato l’allegato            |
| deleted_at | TIMESTAMP  | Soft delete (opzionale)                      |

## tags
| Campo         | Tipo       | Descrizione                                 |
|--------------|------------|---------------------------------------------|
| id           | BIGINT PK  | Identificativo univoco tag                  |
| household_id | BIGINT FK  | Household di appartenenza                   |
| name         | VARCHAR    | Nome tag                                    |
| color        | VARCHAR    | Colore tag                                  |
| created_at/updated_at| TIMESTAMP | Timestamps Laravel                    |
| deleted_at   | TIMESTAMP  | Soft delete (opzionale)                     |

## transaction_tag
| Campo         | Tipo       | Descrizione                                 |
|--------------|------------|---------------------------------------------|
| transaction_id| BIGINT FK  | Transazione                                 |
| tag_id       | BIGINT FK  | Tag associato                               |
| (PK: transaction_id + tag_id) |        | Chiave primaria composta              |

## budgets
| Campo         | Tipo         | Descrizione                                 |
|--------------|--------------|---------------------------------------------|
| id           | BIGINT PK    | Identificativo univoco budget               |
| household_id | BIGINT FK    | Household di appartenenza                   |
| category_id  | BIGINT FK    | Categoria associata                         |
| amount       | DECIMAL(12,2)| Importo budget                              |
| currency_code| VARCHAR FK   | Codice valuta                               |
| period_start | DATE         | Inizio periodo                              |
| period_end   | DATE         | Fine periodo                                |
| description  | TEXT         | Descrizione                                 |
| created_at/updated_at| TIMESTAMP | Timestamps Laravel                    |
| deleted_at   | TIMESTAMP    | Soft delete (opzionale)                     |

## debts_credits
| Campo         | Tipo         | Descrizione                                 |
|--------------|--------------|---------------------------------------------|
| id           | BIGINT PK    | Identificativo univoco                      |
| household_id | BIGINT FK    | Household di appartenenza                   |
| user_id      | BIGINT FK    | Utente associato                            |
| counterparty | VARCHAR      | Controparte                                 |
| amount       | DECIMAL(12,2)| Importo                                     |
| currency_code| VARCHAR FK   | Codice valuta                               |
| type         | ENUM         | Tipo (debt/credit)                          |
| due_date     | DATE         | Data scadenza                               |
| status       | ENUM         | Stato (open, closed, overdue)               |
| description  | TEXT         | Descrizione                                 |
| created_at/updated_at| TIMESTAMP | Timestamps Laravel                    |
| deleted_at   | TIMESTAMP    | Soft delete (opzionale)                     |

## financial_goals
| Campo         | Tipo         | Descrizione                                 |
|--------------|--------------|---------------------------------------------|
| id           | BIGINT PK    | Identificativo univoco obiettivo            |
| household_id | BIGINT FK    | Household di appartenenza                   |
| name         | VARCHAR      | Nome obiettivo                              |
| target_amount| DECIMAL(12,2)| Importo target                              |
| currency_code| VARCHAR FK   | Codice valuta                               |
| target_date  | DATE         | Data target                                 |
| status       | ENUM         | Stato (in_progress, reached, cancelled)      |
| created_at/updated_at| TIMESTAMP | Timestamps Laravel                    |
| deleted_at   | TIMESTAMP    | Soft delete (opzionale)                     |

## notifications
| Campo      | Tipo       | Descrizione                                  |
|------------|------------|----------------------------------------------|
| id         | BIGINT PK  | Identificativo univoco notifica              |
| user_id    | BIGINT FK  | Utente destinatario                          |
| title      | VARCHAR    | Titolo notifica                              |
| message    | TEXT       | Messaggio                                    |
| read       | BOOLEAN    | Notifica letta o meno                        |
| created_at | TIMESTAMP  | Data creazione                               |
| deleted_at | TIMESTAMP  | Soft delete (opzionale)                      |


## investment_assets
| Campo         | Tipo         | Descrizione                                                        |
|---------------|--------------|--------------------------------------------------------------------|
| id            | BIGINT PK    | Identificativo univoco asset                                       |
| type          | ENUM         | Tipo asset (crypto, etf, stock, index, commodity, insurance, etc.) |
| symbol        | VARCHAR      | Simbolo asset (es. BTC, AAPL, S&P500, XAU, ecc.)                  |
| name          | VARCHAR      | Nome completo asset                                                |
| currency_code | VARCHAR FK   | Valuta di riferimento (EUR, USD, ecc.)                             |
| extra_data    | JSON         | Dati aggiuntivi (es. ISIN, exchange, policy, ecc.)                 |

## investments
| Campo         | Tipo         | Descrizione                                                        |
|---------------|--------------|--------------------------------------------------------------------|
| id            | BIGINT PK    | Identificativo univoco investimento                                |
| user_id       | BIGINT FK    | Utente che ha effettuato l’investimento                            |
| household_id  | BIGINT FK    | Household di appartenenza                                          |
| account_id    | BIGINT FK    | Account di riferimento (es. broker, wallet, banca)                 |
| asset_id      | BIGINT FK    | Asset di investimento (collega a investment_assets)                |
| quantity      | DECIMAL(18,8)| Quantità posseduta                                                 |
| buy_price     | DECIMAL(18,8)| Prezzo di acquisto unitario (fino a 8 decimali, adatto alle crypto)|
| buy_date      | DATE         | Data di acquisto                                                   |
| sell_price    | DECIMAL(18,8)| Prezzo di vendita unitario (null se non venduto, 8 decimali)       |
| sell_date     | DATE         | Data di vendita (null se non venduto)                              |
| fees          | DECIMAL(12,2)| Commissioni totali (opzionale)                                     |
| notes         | TEXT         | Note aggiuntive                                                    |
| is_private    | BOOLEAN      | Se true, solo il proprietario vede i dettagli, altri membri vedono solo il totale o una voce generica |
| created_at/updated_at| TIMESTAMP | Timestamps Laravel                                         |
| deleted_at    | TIMESTAMP    | Soft delete (opzionale)                                            |
---

## Note su privacy e supervisione familiare
- La gestione familiare è implementata tramite household e household_user, con ruoli e permessi granulari.
- Il campo is_private su accounts, transactions, investments permette di mantenere la privacy dei singoli membri.
- Il campo permissions su household_user consente di definire cosa può vedere/fare ogni membro (es. padre supervisore, figlio con area privata).
- Le policy Laravel devono gestire la visibilità dei dati in base a ruolo, permessi e flag privacy.

---

### Note aggiuntive di miglioramento
- Tutte le FK devono essere indicizzate e unsigned.
- Per i campi DECIMAL, si usa DECIMAL(12,2) per maggiore precisione.
- Per i campi status, type, frequency, role, si consiglia l’uso di ENUM o costanti Laravel.
- Le tabelle principali supportano soft delete tramite deleted_at.
- La tabella household_user usa una chiave primaria composta per evitare duplicati.
- Considerare vincoli unique dove necessario (es. nome account/categoria/tag per household).
- Valutare una tabella di audit log per tracciare modifiche critiche.

---

Tutte le FK devono essere indicizzate. Le tabelle sono pensate per essere compatibili con le migration di Laravel e facilmente estendibili.