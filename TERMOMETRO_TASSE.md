# Widget "Termometro Tasse" - Documentazione

## Panoramica

Il widget **Termometro Tasse** è un componente interattivo per la Dashboard che aiuta gli utenti con Partita IVA a monitorare l'accantonamento fiscale necessario per imposta sostitutiva e contributi INPS.

## Funzionalità

### Calcolo Automatico
Il widget calcola automaticamente in tempo reale:
- **Entrate Lorde**: Calcolate automaticamente dalle transazioni positive (entrate) dell'anno corrente dell'utente
- **Imposta Sostitutiva**: Calcolata in base all'aliquota configurata nel profilo utente
- **Contributi INPS**: Calcolati in base alla percentuale configurata nel profilo utente
- **Margine Netto**: La somma disponibile dopo gli accantonamenti fiscali
- **Percentuale di Accantonamento**: La percentuale totale da mettere da parte

### Gauge Circolare Visivo
Un indicatore circolare SVG mostra la percentuale totale di accantonamento con colori dinamici:
- **Verde** (< 30%): Accantonamento contenuto
- **Arancione** (30-50%): Accantonamento medio
- **Rosso** (> 50%): Accantonamento elevato

### Configurazione nel Profilo Utente
Gli utenti configurano le aliquote fiscali nel loro profilo (sezione "Impostazioni di Profilazione"):
1. **Aliquota Imposta Sostitutiva** (%): Default 15% (regime forfettario), personalizzabile
2. **Aliquota Contributi INPS** (%): Default 26.23%, personalizzabile

Le entrate lorde sono calcolate automaticamente dalle transazioni e non richiedono input manuale.

## Visibilità

Il widget è visibile **SOLO** per utenti con Partita IVA, determinato da:
- `profile_settings.has_vat === true` OPPURE
- `user_type === 'partita_iva'`

## Architettura

### Componenti

#### 1. `TaxThermometer.tsx`
Il componente principale del widget. Caratteristiche:
- Accetta props: `grossIncome`, `taxRate`, `inpsRate` e opzionale `className`
- Usa `clsx` per classi condizionali
- Formattazione valute in italiano (Intl.NumberFormat)
- Gauge SVG animato con transizioni fluide
- Visualizza dati configurati in sola lettura (entrate, aliquote)
- Non permette modifiche interattive, solo consultazione

#### 2. `useTaxCalculator.ts`
Hook personalizzato per i calcoli fiscali:
```typescript
interface TaxCalculation {
    grossIncome: number;
    taxRate: number;
    inpsRate: number;
    taxAmount: number;
    inpsAmount: number;
    netMargin: number;
    totalSetAside: number;
    setAsidePercentage: number;
}

// L'hook accetta i valori come parametri e restituisce solo i calcoli
function useTaxCalculator(
    grossIncome: number,
    taxRate: number,
    inpsRate: number
): { calculation: TaxCalculation }
```

#### 3. `DashboardController.php`
Il controller backend prepara i dati per il widget:
- Metodo `getTaxThermometerData()` calcola le entrate lorde annue dalle transazioni
- Recupera `tax_rate` e `inps_rate` da `profile_settings`
- Passa i dati come prop `taxThermometerData` alla Dashboard

#### 4. Profilo Utente (`ProfileQuizController.php`)
Gestisce la configurazione delle aliquote fiscali:
- Validazione degli input: `tax_rate` e `inps_rate` (0-100)
- Salvataggio in `profile_settings` come array JSON
- Campi disponibili nella pagina di modifica profilo

### Integrazione Dashboard

Il widget è integrato nella Dashboard principale (`resources/js/Pages/Dashboard.tsx`):

```typescript
{hasVat && (
    <TaxThermometer 
        grossIncome={taxThermometerData.gross_income}
        taxRate={taxThermometerData.tax_rate}
        inpsRate={taxThermometerData.inps_rate}
    />
)}
```

I dati vengono forniti dal backend tramite il `DashboardController`:
```php
private function getTaxThermometerData(\App\Models\User $user): array
{
    $settings = $user->profile_settings ?? [];
    $hasVat = $settings['has_vat'] ?? false;

    if (!$hasVat) {
        return [
            'has_vat' => false,
            'gross_income' => 0,
            'tax_rate' => 15,
            'inps_rate' => 26.23,
        ];
    }

    // Calcola entrate lorde annue dalle transazioni positive
    $grossIncome = Transaction::whereHas('account', ...)
        ->where('user_id', $user->id)
        ->where('amount', '>', 0)
        ->whereBetween('date', [$startOfYear, $endOfYear])
        ->sum('amount');

    return [
        'has_vat' => true,
        'gross_income' => $grossIncome,
        'tax_rate' => $settings['tax_rate'] ?? 15,
        'inps_rate' => $settings['inps_rate'] ?? 26.23,
    ];
}
```

## Formule di Calcolo

### Imposta Sostitutiva
```
imposta = (entrateLorde * aliquota) / 100
```

### Contributi INPS
```
contributi = (entrateLorde * percentualeINPS) / 100
```

### Margine Netto
```
margineNetto = entrateLorde - imposta - contributi
```

### Percentuale di Accantonamento
```
percentualeAccantonamento = ((imposta + contributi) / entrateLorde) * 100
```

## Test

### Test Unitari (`TaxCalculatorLogicTest.php`)
Verificano la correttezza dei calcoli fiscali:
- Calcolo imposta sostitutiva
- Calcolo contributi INPS
- Calcolo margine netto
- Calcolo percentuale di accantonamento
- Casi edge (entrate zero, aliquote personalizzate)

### Test di Feature (`TaxThermometerVisibilityTest.php`)
Verificano la visibilità corretta del widget e il passaggio dei dati:
- Utente con Partita IVA: verifica che `taxThermometerData.has_vat === true` e le aliquote configurate
- Utente senza Partita IVA: verifica che `taxThermometerData.has_vat === false`
- Verifica di entrambi i metodi di determinazione (has_vat e user_type)
- Test tramite Inertia props invece di ricerca testuale nell'HTML

## Esempi di Utilizzo
Gli esempi mostrano i calcoli automatici basati sulle entrate annue dell'utente:

### Regime Forfettario (15%)
- Entrate annue: €10.000 (calcolate automaticamente)
- Imposta configurata: 15%
- INPS configurata: 26.23%
- **Accantonamento**: 41.23% (€4.123)
- **Netto**: €5.877

### Regime Forfettario Start-up (5%)
- Entrate annue: €20.000 (calcolate automaticamente)
- Imposta configurata: 5%
- INPS configurata: 26.23%
- **Accantonamento**: 31.23% (€6.246)
- **Netto**: €13.754

### Regime Ordinario (23%)
- Entrate annue: €30.000 (calcolate automaticamente)
- Imposta configurata: 23%
- INPS configurata: 26.23%
- **Accantonamento**: 49.23% (€14.769)
- **Netto**: €15.231

**Nota**: Le entrate sono calcolate automaticamente sommando tutte le transazioni positive (amount > 0) dell'anno corrente associate all'utente.: 49.23% (€14.769)
- **Netto**: €15.231

## Styling e UX

### Design System
- Tailwind CSS per tutto lo styling
- Dark mode supportato
- Responsive design (mobile-first)
- Animazioni fluide (transition-all duration-500)

### Accessibilità
- Label descrittivi per screen reader
- Placeholder informativi
- Help text contestuale
- Contrasto colori WCAG 2.1 compliant

### Formattazione Italiana
- Valuta: € (Euro)
- Separatore decimale: `,`
- Separatore migliaia: `.`
- EsFile Creati Inizialmente
1. `resources/js/Components/TaxThermometer.tsx` - Componente principale
2. `resources/js/hooks/useTaxCalculator.ts` - Hook di calcolo
3. `tests/Feature/TaxThermometerVisibilityTest.php` - Test visibilità
4. `tests/Unit/TaxCalculatorLogicTest.php` - Test calcoli

### File Modificati nella Revisione
1. `app/Http/Controllers/DashboardController.php` - Aggiunto metodo `getTaxThermometerData()`
2. `app/Http/Controllers/ProfileQuizController.php` - Aggiunta validazione per `tax_rate` e `inps_rate`
3. `resources/js/Pages/Dashboard.tsx` - Aggiunta interfaccia `TaxThermometerData` e passaggio props
4. `resources/js/Pages/ProfileQuiz/Edit.tsx` - Aggiunta sezione aliquote fiscali
5. `resources/js/Components/TaxThermometer.tsx` - Rimossi input, aggiunti props per dati dal backend
6. `resources/js/hooks/useTaxCalculator.ts` - Rimosso state management, solo calcoli
7. **Storico accantonamenti**: Grafici di andamento mensile/trimestrale degli accantonamenti
2. **Confronto periodi**: Confrontare accantonamenti anno corrente vs anno precedente
3. **Proiezioni**: Proiezione accantonamento a fine anno basato su trend attuale
4. **Export dati**: Export calcoli fiscali per commercialista (PDF/CSV)
5. **Reminder pagamenti**: Notifiche per scadenze F24 trimestrali/annuali
6. **Integrazione fatturazione**: Se implementato modulo fatturazione, calcolare entrate da fatture emesse
7. **Multi-regime**: Supporto per cambi di regime fiscale durante l'anno
8. **Spese deducibili**: Integrare calcolo spese deducibili per ridurre base imponibile

### Note per Sviluppatori
- Il componente recupera tutti i dati dal backend via props, nessuna logica di fetch client-side
- I calcoli sono eseguiti sia lato server (per i dati) che lato client (per la visualizzazione reattiva)
- Le entrate sono calcolate su base annuale (1 gennaio - 31 dicembre)
- Modificare le aliquote fiscali richiede un refresh della dashboard per vedere i nuovi calcoli
- Il widget è completamente isolato e non modifica lo stato globale dell'applicazion
3. Grafici storici degli accantonamenti
4. Export dati per commercialista
5. Reminder pagamenti fiscali
6. Integrazione con modulo IVA (quando implementato)

### Note per Sviluppatori
- Il componente è completamente standalone e riutilizzabile
- Non richiede modifiche backend (solo frontend)
- Tutti i calcoli sono lato client per reattività istantanea
- Facilmente estendibile per nuove tipologie di calcolo fiscale

## Conformità

✅ Tutte le interfacce in italiano
✅ Usa esclusivamente Tailwind CSS
✅ Componente riutilizzabile con prop className
✅ Usa clsx per classi condizionali
✅ Mobile-first responsive
✅ Accessibilità WCAG 2.1
✅ Test unitari e di feature
✅ Zero dipendenze esterne aggiuntive
