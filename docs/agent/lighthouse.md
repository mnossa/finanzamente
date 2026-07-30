# Lighthouse — audit performance e SEO

## Dashboard autenticata (`/dashboard`)

La dashboard è **noindex** (`app.blade.php` + `robots.txt`). Un punteggio SEO basso su `/dashboard` è **atteso** e non va “corretto” rimuovendo `noindex`.

Metriche da monitorare in produzione (desktop, utente loggato):

| Metrica | Target |
|---------|--------|
| Performance | ≥ 85 |
| TBT | < 300 ms |
| TTI | < 3 s |
| Accessibilità | 100 |
| CLS | < 0.1 |

Ottimizzazioni implementate (2026-06):

- `@dnd-kit` caricato solo in modalità modifica layout (`arrayMove` inline in `useDashboardLayout`)
- Recharts solo in `FormulaChartWidget` (lazy); KPI/progress in `FormulaKpiWidget` senza recharts
- Payload Inertia alleggerito (user DTO, notifiche e widget pesanti differiti)
- SSR priority: solo widget formula KPI/progress (max 4); chart solo via fetch async
- Tipi drag locali (`types/dashboardDrag.ts`): `@dnd-kit` solo nel chunk lazy di edit layout
- Chart formula e widget Recharts: `DeferredMount` + `scheduleIdle` (viewport + idle)
- Fetch widget formula in batch parallelo
- Registrazione SW post-idle (`PwaUpdatePrompt`)
- Prefetch Vite ridotto (`concurrency: 1`)
- Ziggy: `config/ziggy.php` esclude landing/webhook dal payload inline
- Font Figtree 400+600 con `display=swap`

## Pagine pubbliche (SEO reale)

Eseguire Lighthouse su:

- `/` (landing)
- `/simulazioni` (landing simulazioni)
- Pagine Blade con `SEOMeta` (`guest.blade.php`)

Comando esempio (Chrome CLI):

```bash
npx lighthouse https://finanzamente.it/ --only-categories=performance,accessibility,best-practices,seo --output=json --output-path=./lighthouse-home.json
```

Non usare il punteggio SEO della dashboard come KPI di indicizzazione.
