# Widget "Termometro Tasse" - Documentazione

## Panoramica

Il widget **Termometro Tasse** è un componente interattivo per la Dashboard che aiuta gli utenti con Partita IVA a calcolare l'accantonamento fiscale necessario per imposta sostitutiva e contributi INPS.

## Funzionalità

### Calcolo Istantaneo
Il widget calcola in tempo reale:
- **Imposta Sostitutiva**: Calcolata in base all'aliquota configurabile dall'utente
- **Contributi INPS**: Calcolati in base alla percentuale configurabile dall'utente
- **Margine Netto**: La somma disponibile dopo gli accantonamenti fiscali
- **Percentuale di Accantonamento**: La percentuale totale da mettere da parte

### Gauge Circolare Visivo
Un indicatore circolare SVG mostra la percentuale totale di accantonamento con colori dinamici:
- **Verde** (< 30%): Accantonamento contenuto
- **Arancione** (30-50%): Accantonamento medio
- **Rosso** (> 50%): Accantonamento elevato

### Input Configurabili
L'utente può personalizzare:
1. **Entrate Lorde** (€): L'importo totale delle entrate
2. **Aliquota Imposta Sostitutiva** (%): Default 15% (regime forfettario), personalizzabile
3. **Percentuale Contributi INPS** (%): Default 26.23%, personalizzabile

## Visibilità

Il widget è visibile **SOLO** per utenti con Partita IVA, determinato da:
- `profile_settings.has_vat === true` OPPURE
- `user_type === 'partita_iva'`

## Architettura

### Componenti

#### 1. `TaxThermometer.tsx`
Il componente principale del widget. Caratteristiche:
- Accetta prop `className` per personalizzazione
- Usa `clsx` per classi condizionali
- Formattazione valute in italiano (Intl.NumberFormat)
- Gauge SVG animato con transizioni fluide
- Form con input numerici validati

#### 2. `useTaxCalculator.ts`
Hook personalizzato per la gestione dello stato e dei calcoli:
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
```

### Integrazione Dashboard

Il widget è stato integrato nella Dashboard principale (`resources/js/Pages/Dashboard.tsx`) subito dopo le statistiche mensili:

```typescript
{hasVat && (
    <TaxThermometer />
)}
```

La verifica `hasVat` controlla entrambi i campi:
```typescript
const hasVat = auth.user.profile_settings?.has_vat === true 
    || auth.user.user_type === 'partita_iva';
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
Verificano la visibilità corretta del widget:
- Utente con Partita IVA vede il widget
- Utente senza Partita IVA non vede il widget
- Verifica di entrambi i metodi di determinazione (has_vat e user_type)

## Esempi di Utilizzo

### Regime Forfettario (15%)
- Entrate: €10.000
- Imposta: 15%
- INPS: 26.23%
- **Accantonamento**: 41.23% (€4.123)
- **Netto**: €5.877

### Regime Forfettario Start-up (5%)
- Entrate: €20.000
- Imposta: 5%
- INPS: 26.23%
- **Accantonamento**: 31.23% (€6.246)
- **Netto**: €13.754

### Regime Ordinario (23%)
- Entrate: €30.000
- Imposta: 23%
- INPS: 26.23%
- **Accantonamento**: 49.23% (€14.769)
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
- Esempio: €1.500,00

## File Modificati/Creati

### Nuovi File
1. `resources/js/Components/TaxThermometer.tsx` - Componente principale
2. `resources/js/hooks/useTaxCalculator.ts` - Hook di calcolo
3. `tests/Feature/TaxThermometerVisibilityTest.php` - Test visibilità
4. `tests/Unit/TaxCalculatorLogicTest.php` - Test calcoli

### File Modificati
1. `resources/js/Pages/Dashboard.tsx` - Integrazione widget
2. `resources/js/types/index.d.ts` - Aggiunto campo `user_type` all'interfaccia User

## Manutenzione Futura

### Possibili Estensioni
1. Persistenza delle impostazioni utente (salvare aliquote preferite)
2. Calcolo su base annuale/trimestrale
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
